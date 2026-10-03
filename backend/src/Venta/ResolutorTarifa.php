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
 * tarifas sin empresa (comodín), que también aplican.
 */
final class ResolutorTarifa
{
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
        if ($clases === []) {
            return [];
        }
        $empresaId = $salida->getEmpresa()?->getId();
        $busId = $salida->getBus()?->getId();
        $busClaseId = $salida->getBus()?->getClase()?->getId();
        $ahora = $this->reloj->now();

        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, precio_monto, precio_moneda, clase, vigente_desde, empresa_id, trayecto_id, hora_desde, hora_hasta, bus_clase_id, bus_id
               FROM boleto_tarifa
              WHERE clase IN (:clases)
                AND vigente_desde <= :ahora
                AND (trayecto_id = :trayecto OR trayecto_id IS NULL)',
            [
                "clases" => array_values(array_unique($clases)),
                "ahora" => $ahora->format("Y-m-d H:i:s"),
                "trayecto" => $trayecto->getId(),
            ],
            ["clases" => ArrayParameterType::STRING],
        );

        $id = static fn(mixed $v) => $v !== null ? (int) $v : null;
        $hhmm = static fn(mixed $v) => $v !== null ? substr((string) $v, 0, 5) : null;
        $candidatos = array_map(
            static fn(array $f) => new CandidatoTarifa(
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
            ),
            $filas,
        );

        $hora = $salida->getFecha()->format("H:i");
        $resultado = [];
        foreach (array_unique($clases) as $clase) {
            $elegida = EspecificidadTarifa::elegir(
                $candidatos,
                $clase,
                $empresaId,
                (int) $trayecto->getId(),
                $hora,
                $busClaseId,
                $busId,
                $ahora,
            );
            if ($elegida !== null) {
                $resultado[$clase] = $elegida;
            }
        }

        return $resultado;
    }
}
