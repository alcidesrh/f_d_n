<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Bus;
use App\Entity\EsquemaSalida;
use App\Entity\Ruta;
use App\Entity\Salida;
use App\Entity\Usuario;
use App\Salida\Programacion\Momento;
use App\Salida\Programacion\Programacion;
use App\Ruta\Horarios;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Programador de salidas (ADR-024): despliega una `Programacion` en salidas
 * concretas y decide qué pasa con cada una antes de crearla:
 *
 * - `nueva`: se crea.
 * - `existe`: ya hay una salida igual (misma ruta, bus y hora); no se duplica.
 * - `conflicto`: el bus está en otro viaje a esa hora (según lo que dura la
 *   ruta con la clase de ese bus, `Ruta::duracionPara`).
 * - `pasada`: la hora ya pasó (solo puede ocurrir el día de hoy).
 *
 * La vista previa y la creación usan el mismo plan; la creación lo recalcula
 * dentro de la transacción y crea solo las nuevas. Su respuesta trae el plan
 * con el id de cada salida creada: lo que se omitió es lo que ya estaba
 * antes de crear, no lo recién creado.
 *
 * `desplegarEsquema` es el mismo plan para el programador de tareas
 * (ADR-044): sin usuario ni alcance, con los buses del esquema, que deben
 * seguir siendo de la empresa del esquema.
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
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{items: list<array<string, mixed>>, resumen: array<string, int>} */
    public function vistaPrevia(Programacion $p): array
    {
        $plan = $this->plan($p, $this->esquemas->buses($p->busIds()));

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
            $plan = $this->plan($p, $this->esquemas->buses($p->busIds()));
            $creadas = $this->crear($plan, $usuario);

            $esquema = null;
            if ($guardarComo !== null && trim($guardarComo) !== "") {
                $esquema = $this->esquemas->guardar(null, [
                    "nombre" => $guardarComo,
                    "rutaId" => $p->rutaId,
                    "intervaloDias" => $p->intervaloDias,
                    "momentos" => array_map(static fn(Momento $m) => $m->toArray(), $p->momentos),
                ], $usuario);
            }

            $items = [];
            foreach ($plan["items"] as $n => $i) {
                $items[] = self::item($i) + ["id" => isset($creadas[$n]) ? (int) $creadas[$n]->getId() : null];
            }

            return [
                "creadas" => count($creadas),
                "resumen" => self::resumen($plan["items"]),
                // Cada salida del plan; las creadas, con su id.
                "items" => $items,
                "omitidas" => array_values(array_filter($items, static fn(array $i) => $i["estado"] !== self::NUEVA)),
                "esquema" => $esquema,
            ];
        });
    }

    /**
     * Despliega un tramo de un esquema automático (ADR-044): crea las nuevas
     * y devuelve los conflictos (el bus ocupado a esa hora) para avisar.
     * Corre dentro de la transacción de quien llama (`HorizonteSalidas`).
     *
     * @return array{resumen: array<string, int>, conflictos: list<array<string, mixed>>}
     */
    public function desplegarEsquema(EsquemaSalida $esquema, Programacion $p): array
    {
        return $this->transaccion->ejecutar(function () use ($esquema, $p): array {
            $this->em->find(Ruta::class, $p->rutaId, LockMode::PESSIMISTIC_WRITE);
            $buses = [];
            foreach ($esquema->getMomentos() as $m) {
                $bus = $m->getBus();
                $empresa = $esquema->getEmpresa()?->getId();
                if ($empresa !== null && $bus->getEmpresa()?->getId() !== $empresa) {
                    throw new SalidaRechazada(sprintf("El bus %s ya no es de la empresa del esquema.", $bus->getCodigo()), "bus_otra_empresa");
                }
                $buses[(int) $bus->getId()] = $bus;
            }
            $plan = $this->plan($p, $buses);
            $this->crear($plan, null);

            return [
                "resumen" => self::resumen($plan["items"]),
                "conflictos" => array_values(array_map(self::item(...), array_filter($plan["items"], static fn(array $i) => $i["estado"] === self::CONFLICTO))),
            ];
        });
    }

    /**
     * @param array{ruta: Ruta, items: list<array{fecha: \DateTimeImmutable, bus: Bus, estado: string, choque: mixed}>} $plan
     *
     * @return array<int, Salida> las creadas, por su posición en el plan
     */
    private function crear(array $plan, ?Usuario $usuario): array
    {
        $creadas = [];
        foreach ($plan["items"] as $n => $i) {
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
            $creadas[$n] = $salida;
        }
        $this->em->flush();
        $this->horarios->fijar(...array_map(static fn(Salida $s) => (int) $s->getId(), $creadas));

        return $creadas;
    }

    /**
     * @param array<int, Bus> $buses los de la programación, por id
     *
     * @return array{ruta: Ruta, items: list<array{fecha: \DateTimeImmutable, bus: Bus, estado: string, choque: mixed}>}
     */
    private function plan(Programacion $p, array $buses): array
    {
        $ruta = $this->em->find(Ruta::class, $p->rutaId)
            ?? throw new SalidaRechazada("La ruta no existe.", "ruta_inexistente", 404);
        if (!$ruta->isActivo()) {
            throw new SalidaRechazada("La ruta " . VistaSalida::ruta($ruta) . " está inactiva.", "ruta_inactiva");
        }
        $agenda = $this->agendas->de($p->busIds(), $p->desde, $p->hasta->modify("+3 days"));
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());

        $items = [];
        foreach ($p->salidas() as ["fecha" => $fecha, "busId" => $busId]) {
            $choque = null;
            $duracion = $ruta->duracionPara($buses[$busId]->getClase());
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
