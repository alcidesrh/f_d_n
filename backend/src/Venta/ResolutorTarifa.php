<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use App\Entity\Salida;
use App\Entity\Trayecto;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Money\Currency;
use Money\Money;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Tarifa (`BoletoTarifa`) de un asiento en un salida para un trayecto
 * (`EspecificidadTarifa`): la más reciente vigente hoy que coincide con la
 * empresa, la clase de bus, la hora en que parte y el bus del salida,
 * relajando esos campos por prioridad si ninguna coincide.
 *
 * Trae todas las tarifas vigentes del trayecto (y las sin trayecto) aunque
 * fijen otra empresa, clase de bus o bus: la relajación puede necesitarlas.
 *
 * Lee con DBAL a propósito: el `TenantFilter` del ORM dejaría fuera las
 * tarifas sin empresa (comodín), que también aplican. Memoiza las tarifas
 * por trayecto durante la petición (se vacía en cada request, como
 * `Itinerarios`): los subtrayectos de una salida se resuelven con una
 * consulta y las salidas del mismo trayecto no vuelven a consultar.
 */
final class ResolutorTarifa implements ResetInterface
{
    /** @var array<int, list<CandidatoTarifa>> tarifas vigentes por id de trayecto */
    private array $porTrayecto = [];
    /** @var ?list<CandidatoTarifa> tarifas sin trayecto: valen para todos */
    private ?array $comodines = null;

    public function __construct(
        private readonly Connection $conexion,
        private readonly ClockInterface $reloj,
    ) {}

    public function precio(Salida $salida, Trayecto $trayecto, Asiento $asiento): ?Money
    {
        return $this->tarifa($salida, $trayecto, $asiento)?->precio;
    }

    public function tarifa(Salida $salida, Trayecto $trayecto, Asiento $asiento): ?CandidatoTarifa
    {
        return $this->porClase($salida, $trayecto, [$asiento->getClase()->value])[$asiento->getClase()->value] ?? null;
    }

    /**
     * Mejor tarifa por clase de asiento.
     *
     * @param list<string> $clases
     *
     * @return array<string, CandidatoTarifa>
     */
    public function porClase(Salida $salida, Trayecto $trayecto, array $clases): array
    {
        return $this->porTrayectos($salida, [(int) $trayecto->getId()], $clases)[(int) $trayecto->getId()];
    }

    /**
     * Mejor tarifa por clase de asiento de varios trayectos de la salida (el
     * suyo y sus subtrayectos): cada uno hereda de la salida la empresa, el
     * bus, la clase de bus y la hora en que parte.
     *
     * @param list<int>    $trayectoIds
     * @param list<string> $clases
     *
     * @return array<int, array<string, CandidatoTarifa>> por id de trayecto (vacío si no tiene tarifa)
     */
    public function porTrayectos(Salida $salida, array $trayectoIds, array $clases): array
    {
        $trayectoIds = array_values(array_unique($trayectoIds));
        $clases = array_values(array_unique($clases));
        $this->cargar($trayectoIds);

        $empresaId = $salida->getEmpresa()?->getId();
        $busId = $salida->getBus()?->getId();
        $busClaseId = $salida->getBus()?->getClase()?->getId();
        $hora = $salida->getFecha()->format("H:i");
        $ahora = $this->reloj->now();

        $resultado = [];
        foreach ($trayectoIds as $trayectoId) {
            $candidatos = [...$this->porTrayecto[$trayectoId], ...$this->comodines];
            $resultado[$trayectoId] = [];
            foreach ($clases as $clase) {
                $elegida = EspecificidadTarifa::elegir(
                    $candidatos,
                    $clase,
                    $empresaId,
                    $trayectoId,
                    $hora,
                    $busClaseId,
                    $busId,
                    $ahora,
                );
                if ($elegida !== null) {
                    $resultado[$trayectoId][$clase] = $elegida;
                }
            }
        }

        return $resultado;
    }

    public function reset(): void
    {
        $this->porTrayecto = [];
        $this->comodines = null;
    }

    /**
     * Trae las tarifas vigentes de los trayectos que faltan (aunque fijen
     * otra empresa, clase de bus o bus: la relajación puede necesitarlas) y,
     * la primera vez, las sin trayecto.
     *
     * @param list<int> $trayectoIds
     */
    private function cargar(array $trayectoIds): void
    {
        $faltan = array_values(array_filter($trayectoIds, fn(int $id) => !isset($this->porTrayecto[$id])));
        if ($faltan === [] && $this->comodines !== null) {
            return;
        }

        $condiciones = [];
        if ($faltan !== []) {
            $condiciones[] = "trayecto_id IN (:trayectos)";
        }
        if ($this->comodines === null) {
            $condiciones[] = "trayecto_id IS NULL";
        }
        $filas = $this->conexion->fetchAllAssociative(
            sprintf(
                'SELECT id, precio_monto, precio_moneda, clase, vigente_desde, empresa_id, trayecto_id, hora_desde, hora_hasta, bus_clase_id, bus_id
                   FROM boleto_tarifa
                  WHERE vigente_desde <= :ahora
                    AND (%s)',
                implode(" OR ", $condiciones),
            ),
            [
                "ahora" => $this->reloj->now()->format("Y-m-d H:i:s"),
                "trayectos" => $faltan,
            ],
            ["trayectos" => ArrayParameterType::INTEGER],
        );

        foreach ($faltan as $id) {
            $this->porTrayecto[$id] = [];
        }
        $this->comodines ??= [];
        foreach ($filas as $f) {
            $c = self::candidato($f);
            if ($c->trayectoId === null) {
                $this->comodines[] = $c;
            } else {
                $this->porTrayecto[$c->trayectoId][] = $c;
            }
        }
    }

    /** @param array<string, mixed> $f */
    private static function candidato(array $f): CandidatoTarifa
    {
        $id = static fn(mixed $v) => $v !== null ? (int) $v : null;
        $hhmm = static fn(mixed $v) => $v !== null ? substr((string) $v, 0, 5) : null;

        return new CandidatoTarifa(
            (int) $f["id"],
            new Money((int) $f["precio_monto"], new Currency($f["precio_moneda"])),
            $f["clase"],
            new \DateTimeImmutable((string) $f["vigente_desde"]),
            empresaId: $id($f["empresa_id"]),
            trayectoId: $id($f["trayecto_id"]),
            horaDesde: $hhmm($f["hora_desde"]),
            horaHasta: $hhmm($f["hora_hasta"]),
            busClaseId: $id($f["bus_clase_id"]),
            busId: $id($f["bus_id"]),
        );
    }
}
