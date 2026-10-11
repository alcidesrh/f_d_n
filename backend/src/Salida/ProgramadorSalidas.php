<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Bus;
use App\Entity\Ruta;
use App\Entity\Salida;
use App\Entity\Usuario;
use App\Salida\Programacion\Momento;
use App\Salida\Programacion\Programacion;
use App\Ruta\Horarios;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Programador de salidas (ADR-024): despliega una `Programacion` en salidas
 * concretas y decide qué pasa con cada una antes de crearla:
 *
 * - `nueva`: se crea.
 * - `existe`: ya hay una salida igual (misma ruta, bus y hora); no se duplica.
 * - `conflicto`: el bus está en otro viaje a esa hora (según la duración estimada de la ruta).
 * - `pasada`: la hora ya pasó (solo puede ocurrir el día de hoy).
 *
 * La vista previa y la creación usan el mismo plan; la creación lo recalcula
 * dentro de la transacción y crea solo las nuevas.
 */
final class ProgramadorSalidas
{
    public const NUEVA = "nueva";
    public const EXISTE = "existe";
    public const CONFLICTO = "conflicto";
    public const PASADA = "pasada";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgendaFlota $agendas,
        private readonly Esquemas $esquemas,
        private readonly Transaccion $transaccion,
        private readonly Horarios $horarios,
    ) {}

    /** @return array{items: list<array<string, mixed>>, resumen: array<string, int>} */
    public function vistaPrevia(Programacion $p): array
    {
        $plan = $this->plan($p);

        return ["items" => array_map(self::item(...), $plan["items"]), "resumen" => self::resumen($plan["items"])];
    }

    /**
     * Crea las salidas nuevas y, si se pide, guarda la configuración como esquema.
     *
     * @return array<string, mixed>
     */
    public function programar(Programacion $p, ?string $guardarComo, ?Usuario $usuario): array
    {
        return $this->transaccion->ejecutar(function () use ($p, $guardarComo, $usuario): array {
            // Serializa las programaciones de la misma ruta (dos usuarios a la vez no duplican).
            $this->em->find(Ruta::class, $p->rutaId, LockMode::PESSIMISTIC_WRITE);
            $plan = $this->plan($p);

            $creadas = [];
            foreach ($plan["items"] as $i) {
                if ($i["estado"] !== self::NUEVA) {
                    continue;
                }
                $salida = (new Salida())
                    ->setRuta($plan["ruta"])
                    ->setBus($i["bus"])
                    ->setEmpresa($i["bus"]->getEmpresa())
                    ->setCreatedBy($usuario)
                    ->setFecha(\DateTime::createFromImmutable($i["fecha"]));
                $this->em->persist($salida);
                $creadas[] = $salida;
            }
            $this->em->flush();
            $this->horarios->fijar(...array_map(static fn(Salida $s) => (int) $s->getId(), $creadas));

            $esquema = null;
            if ($guardarComo !== null && trim($guardarComo) !== "") {
                $esquema = $this->esquemas->guardar(null, [
                    "nombre" => $guardarComo,
                    "rutaId" => $p->rutaId,
                    "intervaloDias" => $p->intervaloDias,
                    "momentos" => array_map(static fn(Momento $m) => $m->toArray(), $p->momentos),
                ], $usuario);
            }

            return [
                "creadas" => count($creadas),
                "resumen" => self::resumen($plan["items"]),
                "omitidas" => array_values(array_map(
                    self::item(...),
                    array_filter($plan["items"], static fn(array $i) => $i["estado"] !== self::NUEVA),
                )),
                "esquema" => $esquema,
            ];
        });
    }

    /**
     * @return array{ruta: Ruta, items: list<array{fecha: \DateTimeImmutable, bus: Bus, estado: string, choque: mixed}>}
     */
    private function plan(Programacion $p): array
    {
        $ruta = $this->em->find(Ruta::class, $p->rutaId)
            ?? throw new SalidaRechazada("La ruta no existe.", "ruta_inexistente", 404);
        if (!$ruta->isActivo()) {
            throw new SalidaRechazada("La ruta " . VistaSalida::ruta($ruta) . " está inactiva.", "ruta_inactiva");
        }
        $buses = $this->esquemas->buses($p->busIds());
        $duracion = $ruta->getDuracionMinutos();
        $agenda = $this->agendas->de($p->busIds(), $p->desde, $p->hasta->modify("+3 days"));
        $ahora = new \DateTimeImmutable();

        $items = [];
        foreach ($p->salidas() as ["fecha" => $fecha, "busId" => $busId]) {
            $choque = null;
            if ($fecha < $ahora) {
                $estado = self::PASADA;
            } elseif (($choque = $agenda->choque($busId, $fecha, $duracion)) !== null) {
                $igual = ($choque["rutaId"] ?? null) === $ruta->getId() && ($choque["fecha"] ?? null) === $fecha->format(DATE_ATOM);
                $estado = $igual ? self::EXISTE : self::CONFLICTO;
            } else {
                $estado = self::NUEVA;
                $agenda->ocupar($busId, $fecha, $duracion, ["id" => null, "fecha" => $fecha->format(DATE_ATOM), "ruta" => "esta misma programación", "bus" => $buses[$busId]->getCodigo()]);
            }
            $items[] = ["fecha" => $fecha, "bus" => $buses[$busId], "estado" => $estado, "choque" => $choque];
        }

        return ["ruta" => $ruta, "items" => $items];
    }

    /** @param array{fecha: \DateTimeImmutable, bus: Bus, estado: string, choque: mixed} $i @return array<string, mixed> */
    private static function item(array $i): array
    {
        $choque = is_array($i["choque"]) ? array_diff_key($i["choque"], ["rutaId" => true]) : null;

        return [
            "fecha" => $i["fecha"]->format(DATE_ATOM),
            "busId" => $i["bus"]->getId(),
            "bus" => $i["bus"]->getCodigo(),
            "estado" => $i["estado"],
            "choque" => $choque,
        ];
    }

    /** @param list<array{estado: string}> $items @return array<string, int> */
    private static function resumen(array $items): array
    {
        $r = ["total" => count($items), self::NUEVA => 0, self::EXISTE => 0, self::CONFLICTO => 0, self::PASADA => 0];
        foreach ($items as $i) {
            ++$r[$i["estado"]];
        }

        return $r;
    }
}
