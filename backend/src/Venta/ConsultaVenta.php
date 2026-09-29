<?php

declare(strict_types=1);

namespace App\Venta;

use App\Croquis\CroquisBus;
use App\Entity\Asiento;
use App\Entity\BoletoAsiento;
use App\Entity\Enclave;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoRecorrido;
use App\Entity\Recorrido;
use App\Venta\Boleto\DatosBoleto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Lecturas de la pantalla de venta y de la página web: recorridos del día,
 * paradas, trayectos vendibles, croquis y ocupación. Solo lectura.
 */
final class ConsultaVenta
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Itinerarios $itinerarios,
        private readonly Disponibilidad $disponibilidad,
        private readonly ResolutorTarifa $tarifas,
        private readonly ReglasVenta $reglas,
        private readonly CroquisBus $croquis,
        private readonly HorasRecorrido $horas,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Recorridos del día que pasan por la estación (en cualquier parada salvo
     * la última): desde ahí se les puede vender. Sin estación, todos.
     *
     * @return list<array<string, mixed>>
     */
    public function recorridosDeEstacion(\DateTimeImmutable $dia, ?int $estacionId): array
    {
        $recorridos = $this->recorridosDelDia($dia);
        $itinerarios = $this->itinerarios->deTrayectos(array_map(static fn(Recorrido $r) => $r->getTrayecto(), $recorridos));

        $filas = [];
        foreach ($recorridos as $r) {
            $it = $itinerarios[$r->getTrayecto()->getId()];
            $pos = $estacionId !== null ? $it->posicion($estacionId) : 0;
            if ($pos === null || $pos >= count($it->paradas) - 1) {
                continue;
            }
            $filas[] = [$r, $it, $estacionId];
        }
        $ocupados = $this->boletosVivosPorRecorrido(array_map(static fn(array $f) => $f[0], $filas));
        $capacidad = $this->capacidadPorBus(array_map(static fn(array $f) => $f[0], $filas));

        return array_map(fn(array $f) => $this->resumen($f[0], $f[1], $f[2], $ocupados, $capacidad), $filas);
    }

    /**
     * Recorridos a la venta en línea entre dos paradas en un día: la web
     * necesita que exista el trayecto (o subtrayecto) origen→destino.
     *
     * @return list<array<string, mixed>>
     */
    public function recorridosEnLinea(\DateTimeImmutable $dia, int $origenId, int $destinoId): array
    {
        $ahora = $this->reloj->now();
        $recorridos = array_filter(
            $this->recorridosDelDia($dia),
            fn(Recorrido $r) => $r->getEstado() === EstadoRecorrido::PROGRAMADA
                && $r->getBus() !== null
                && $ahora < $this->reglas->cierreEnLinea($r),
        );
        $itinerarios = $this->itinerarios->deTrayectos(array_map(static fn(Recorrido $r) => $r->getTrayecto(), $recorridos));

        $resultado = [];
        foreach ($recorridos as $r) {
            $it = $itinerarios[$r->getTrayecto()->getId()];
            $trayectoId = $it->trayectoEntre($origenId, $destinoId);
            if ($trayectoId === null) {
                continue;
            }
            $tramo = $it->tramo($trayectoId);
            $estados = $this->disponibilidad->estados($r, $tramo);
            $asientos = $r->getBus()->getAsientos();
            $clases = array_values(array_unique(array_map(static fn(Asiento $a) => $a->getClase()->value, $asientos->toArray())));
            $trayecto = $this->em->getReference(\App\Entity\Trayecto::class, $trayectoId);
            $tarifas = $this->tarifas->porClase($r, $trayecto, $clases);
            if ($tarifas === []) {
                continue;
            }
            $precios = array_map(static fn(CandidatoTarifa $c) => $c->precio, $tarifas);
            usort($precios, static fn($a, $b) => $a->compare($b));

            $resultado[] = [
                "id" => $r->getId(),
                "trayecto" => $trayectoId,
                "salida" => $this->horaEn($r, $it, $origenId),
                "llegada" => $this->horaEn($r, $it, $destinoId),
                "salidaRecorrido" => $r->getFecha()->format(DATE_ATOM),
                "empresa" => $r->getEmpresa()?->getNombre(),
                "ruta" => sprintf("%s → %s", $r->getTrayecto()->getOrigen()->getNombre(), $r->getTrayecto()->getDestino()->getNombre()),
                "clases" => array_map(static fn(string $c, CandidatoTarifa $t) => ["clase" => $c, "precio" => DatosBoleto::importe($t->precio)], array_keys($tarifas), $tarifas),
                "desde" => DatosBoleto::importe($precios[0]),
                "disponibles" => $asientos->count() - count(array_filter(
                    $estados,
                    static fn(array $e) => $e["estado"] !== Ocupacion::PROPIO,
                )),
                "cierre" => $this->reglas->cierreEnLinea($r)->format(DATE_ATOM),
            ];
        }

        return $resultado;
    }

    /**
     * Paradas, trayectos vendibles y croquis de un recorrido.
     *
     * @return array<string, mixed>
     */
    public function detalle(Recorrido $recorrido): array
    {
        $it = $this->itinerarios->deTrayecto($recorrido->getTrayecto());
        $enclaves = $this->enclaves($it->paradas);

        $trayectos = [];
        foreach ($it->trayectos() as $id => $t) {
            $trayectos[] = [
                "id" => $id,
                "origen" => $t["origen"],
                "destino" => $t["destino"],
                "completo" => $id === $it->trayectoId,
            ];
        }
        usort($trayectos, static fn($a, $b) => [$it->posicion($a["origen"]), $it->posicion($a["destino"])] <=> [$it->posicion($b["origen"]), $it->posicion($b["destino"])]);

        return [
            ...$this->resumen($recorrido, $it, null),
            "paradas" => array_map(fn(int $id, int $pos) => [
                "id" => $id,
                "nombre" => $enclaves[$id]?->getNombre(),
                "direccion" => $enclaves[$id]?->getDireccion(),
                "posicion" => $pos,
                "hora" => $this->horaEn($recorrido, $it, $id),
            ], $it->paradas, array_keys($it->paradas)),
            "trayectos" => $trayectos,
            "croquis" => $recorrido->getBus() !== null ? $this->croquis->leer($recorrido->getBus())["elementos"] : [],
            "cierreEnLinea" => $this->reglas->cierreEnLinea($recorrido)->format(DATE_ATOM),
        ];
    }

    /**
     * Estado de los asientos ocupados para un tramo (los libres no aparecen).
     *
     * @return list<array{asiento: int, estado: string, canal: ?string}>
     */
    public function ocupacion(Recorrido $recorrido, Tramo $tramo, ?string $tokenPropio = null, bool $conCanal = true): array
    {
        $estados = $this->disponibilidad->estados($recorrido, $tramo, $tokenPropio);
        $lista = [];
        foreach ($estados as $asiento => $e) {
            $lista[] = ["asiento" => $asiento, "estado" => $e["estado"], "canal" => $conCanal ? $e["canal"] : null];
        }

        return $lista;
    }

    /**
     * Enclaves que son origen de algún trayecto activo (para la web).
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function estacionesEnLinea(): array
    {
        return array_map(
            static fn(array $f) => ["id" => (int) $f["id"], "nombre" => $f["nombre"]],
            $this->em->createQuery(
                "SELECT DISTINCT e.id, e.nombre FROM App\Entity\Trayecto t JOIN t.origen e WHERE t.activo = true ORDER BY e.nombre",
            )->getArrayResult(),
        );
    }

    /**
     * Destinos alcanzables desde un enclave por algún trayecto activo.
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function destinosDesde(int $origenId): array
    {
        return array_map(
            static fn(array $f) => ["id" => (int) $f["id"], "nombre" => $f["nombre"]],
            $this->em->createQuery(
                "SELECT DISTINCT e.id, e.nombre FROM App\Entity\Trayecto t JOIN t.destino e WHERE t.activo = true AND IDENTITY(t.origen) = :origen ORDER BY e.nombre",
            )->setParameter("origen", $origenId)->getArrayResult(),
        );
    }

    /**
     * @return list<Recorrido>
     */
    private function recorridosDelDia(\DateTimeImmutable $dia): array
    {
        $desde = \DateTime::createFromImmutable($dia->setTime(0, 0));
        $hasta = \DateTime::createFromImmutable($dia->setTime(0, 0)->modify("+1 day"));

        return $this->em->createQueryBuilder()
            ->select("r", "t", "o", "d", "b", "e")
            ->from(Recorrido::class, "r")
            ->join("r.trayecto", "t")
            ->join("t.origen", "o")
            ->join("t.destino", "d")
            ->leftJoin("r.bus", "b")
            ->leftJoin("r.empresa", "e")
            ->where("r.fecha >= :desde AND r.fecha < :hasta")
            ->setParameter("desde", $desde)
            ->setParameter("hasta", $hasta)
            ->orderBy("r.fecha")
            ->addOrderBy("r.id")
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int, int> $ocupados
     * @param array<int, int> $capacidad
     *
     * @return array<string, mixed>
     */
    private function resumen(Recorrido $r, Itinerario $it, ?int $estacionId, array $ocupados = [], array $capacidad = []): array
    {
        $busId = $r->getBus()?->getId();

        return [
            "id" => $r->getId(),
            "salida" => $r->getFecha()->format(DATE_ATOM),
            "salidaEstacion" => $estacionId !== null ? $this->horaEn($r, $it, $estacionId) : null,
            "estado" => $r->getEstado()->value,
            "empresa" => $r->getEmpresa() === null ? null : ["id" => $r->getEmpresa()->getId(), "nombre" => $r->getEmpresa()->getNombre()],
            "bus" => $r->getBus() === null ? null : ["id" => $busId, "codigo" => $r->getBus()->getCodigo(), "gama" => $r->getBus()->getGama()],
            "trayecto" => [
                "id" => $r->getTrayecto()->getId(),
                "origen" => ["id" => $r->getTrayecto()->getOrigen()->getId(), "nombre" => $r->getTrayecto()->getOrigen()->getNombre()],
                "destino" => ["id" => $r->getTrayecto()->getDestino()->getId(), "nombre" => $r->getTrayecto()->getDestino()->getNombre()],
            ],
            "vendidos" => $ocupados[$r->getId()] ?? null,
            "capacidad" => $busId !== null ? ($capacidad[$busId] ?? null) : null,
        ];
    }

    /** Hora estimada del recorrido en una parada (ISO), si se conoce la duración. */
    private function horaEn(Recorrido $r, Itinerario $it, int $enclaveId): ?string
    {
        return $this->horas->enParada($r, $enclaveId)?->format(DATE_ATOM);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, ?Enclave>
     */
    private function enclaves(array $ids): array
    {
        $porId = array_fill_keys($ids, null);
        foreach ($this->em->getRepository(Enclave::class)->findBy(["id" => $ids]) as $e) {
            $porId[$e->getId()] = $e;
        }

        return $porId;
    }

    /**
     * @param list<Recorrido> $recorridos
     *
     * @return array<int, int> boletos vivos por recorrido
     */
    private function boletosVivosPorRecorrido(array $recorridos): array
    {
        if ($recorridos === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(b.recorrido) AS recorrido", "COUNT(DISTINCT b.asiento) AS n")
            ->from(BoletoAsiento::class, "b")
            ->where("b.recorrido IN (:recorridos)")
            ->andWhere("b.estado NOT IN (:libres)")
            ->groupBy("b.recorrido")
            ->setParameter("recorridos", $recorridos)
            ->setParameter("libres", [EstadoBoletoAsiento::ANULADO->value, EstadoBoletoAsiento::REASIGNADO->value])
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn(array $f) => [(int) $f["recorrido"], (int) $f["n"]], $filas), 1, 0);
    }

    /**
     * @param list<Recorrido> $recorridos
     *
     * @return array<int, int> asientos por bus
     */
    private function capacidadPorBus(array $recorridos): array
    {
        $buses = array_values(array_unique(array_filter(array_map(static fn(Recorrido $r) => $r->getBus()?->getId(), $recorridos))));
        if ($buses === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(a.bus) AS bus", "COUNT(a.id) AS n")
            ->from(Asiento::class, "a")
            ->where("a.bus IN (:buses)")
            ->groupBy("a.bus")
            ->setParameter("buses", $buses)
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn(array $f) => [(int) $f["bus"], (int) $f["n"]], $filas), 1, 0);
    }
}
