<?php

declare(strict_types=1);

namespace App\Salida;

use App\Acceso\Alcance;
use App\Acceso\FiltroAlcance;
use App\Entity\Asiento;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Ruta\ParadasDeRutas;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/** Listado paginado de salidas para la gestión logística (ADR-024) y su calendario. */
final class ConsultaSalidas
{
    /** Tope de salidas del calendario: más que esto es un rango que no se puede leer en pantalla. */
    public const MAX_CALENDARIO = 6000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AsientosVendidos $vendidos,
        private readonly ParadasDeRutas $paradas,
    ) {}

    /**
     * Con `$alcance`, solo las salidas de esas empresas (ADR-039).
     *
     * @return array<string, mixed>
     */
    public function buscar(FiltroSalidas $f, ?Alcance $alcance = null): array
    {
        $base = $this->base($f);
        if ($alcance !== null) {
            FiltroAlcance::aplicar($base, $alcance, "s.empresa", compartidos: true);
        }

        $porEstado = [];
        $conteo = (clone $base)->select("s.estado AS estado", "COUNT(s.id) AS n")->groupBy("s.estado")->getQuery()->getArrayResult();
        foreach ($conteo as $c) {
            $porEstado[$c["estado"] instanceof \BackedEnum ? $c["estado"]->value : (string) $c["estado"]] = (int) $c["n"];
        }

        $qb = (clone $base)->select("s", "r", "b", "e");
        if ($f->estados !== []) {
            $qb->andWhere("s.estado IN (:estados)")->setParameter("estados", array_map(static fn($e) => $e->value, $f->estados));
        }
        $total = (int) (clone $qb)->select("COUNT(s.id)")->getQuery()->getSingleScalarResult();

        if ($f->orden === FiltroSalidas::PROXIMAS) {
            $qb->addSelect("CASE WHEN s.estado = 'iniciada' THEN 0 WHEN s.estado = 'abordando' THEN 1 ELSE 2 END AS HIDDEN prioridad")
                ->orderBy("prioridad", "ASC")
                ->addOrderBy("s.fecha", "ASC");
        } else {
            $qb->orderBy("s.fecha", $f->direccion === "desc" ? "DESC" : "ASC");
        }
        /** @var list<Salida> $salidas */
        $salidas = $qb->addOrderBy("s.id", "ASC")
            ->setFirstResult(($f->pagina - 1) * $f->porPagina)
            ->setMaxResults($f->porPagina)
            ->getQuery()
            ->getResult();

        $vendidos = $this->vendidos->porSalida(array_map(static fn(Salida $s) => (int) $s->getId(), $salidas));
        $capacidad = $this->capacidad(array_values(array_unique(array_filter(array_map(static fn(Salida $s) => $s->getBus()?->getId(), $salidas)))));
        $this->paradas->cargar(array_values(array_unique(array_map(static fn(Salida $s) => (int) $s->getRuta()->getId(), $salidas))));
        $ahora = new \DateTimeImmutable();

        return [
            "items" => array_map(
                static fn(Salida $s) => VistaSalida::fila($s, $vendidos[$s->getId()] ?? 0, $capacidad[$s->getBus()?->getId()] ?? null, $ahora),
                $salidas,
            ),
            "total" => $total,
            "pagina" => $f->pagina,
            "porPagina" => $f->porPagina,
            "porEstado" => $porEstado,
        ];
    }

    /**
     * Calendario de salidas (días × horas): todas las del rango que cumplen el
     * filtro, sin paginar y compactas (ids en lugar de objetos), con lo
     * comprometido, la capacidad del bus y la llegada estimada según la clase
     * de su bus. Las rutas que aparecen van aparte, una vez cada una.
     *
     * @return array{items: list<array<string, mixed>>, rutas: list<array<string, mixed>>, porEstado: array<string, int>, truncado: bool}
     */
    public function calendario(FiltroSalidas $f, ?Alcance $alcance = null): array
    {
        $base = $this->base($f);
        if ($alcance !== null) {
            FiltroAlcance::aplicar($base, $alcance, "s.empresa", compartidos: true);
        }
        $porEstado = [];
        foreach ((clone $base)->select("s.estado AS estado", "COUNT(s.id) AS n")->groupBy("s.estado")->getQuery()->getArrayResult() as $c) {
            $porEstado[$c["estado"] instanceof \BackedEnum ? $c["estado"]->value : (string) $c["estado"]] = (int) $c["n"];
        }

        $qb = (clone $base)->select("s", "r", "b", "k")->leftJoin("b.clase", "k");
        if ($f->estados !== []) {
            $qb->andWhere("s.estado IN (:estados)")->setParameter("estados", array_map(static fn($e) => $e->value, $f->estados));
        }
        /** @var list<Salida> $salidas */
        $salidas = $qb->orderBy("s.fecha", "ASC")->addOrderBy("s.id", "ASC")->setMaxResults(self::MAX_CALENDARIO + 1)->getQuery()->getResult();
        $truncado = count($salidas) > self::MAX_CALENDARIO;
        $salidas = array_slice($salidas, 0, self::MAX_CALENDARIO);

        $vendidos = $this->vendidos->porSalida(array_map(static fn(Salida $s) => (int) $s->getId(), $salidas));
        $capacidad = $this->capacidad(array_values(array_unique(array_filter(array_map(static fn(Salida $s) => $s->getBus()?->getId(), $salidas)))));
        $this->paradas->cargar(array_values(array_unique(array_map(static fn(Salida $s) => (int) $s->getRuta()->getId(), $salidas))));
        $ahora = new \DateTimeImmutable();

        $items = [];
        $rutas = [];
        foreach ($salidas as $s) {
            $ruta = $s->getRuta();
            $rutas[(int) $ruta->getId()] ??= [
                "id" => (int) $ruta->getId(),
                "nombre" => VistaSalida::ruta($ruta),
                "origen" => $ruta->getOrigen()?->getNombre(),
                "destino" => $ruta->getDestino()?->getNombre(),
                "origenAlias" => $ruta->getOrigen()?->getAlias(),
                "destinoAlias" => $ruta->getDestino()?->getAlias(),
                "paradas" => $ruta->getParadas()->count(),
            ];
            $fecha = $s->getFecha();
            $duracion = $ruta->duracionPara($s->getBus()?->getClase());
            $items[] = [
                "id" => (int) $s->getId(),
                "fecha" => $fecha?->format(DATE_ATOM),
                "llegada" => $fecha !== null && $duracion !== null ? \DateTimeImmutable::createFromInterface($fecha)->modify("+{$duracion} minutes")->format(DATE_ATOM) : null,
                "estado" => $s->getEstado()->value,
                "atrasada" => $s->getEstado() === EstadoSalida::PROGRAMADA && $fecha < $ahora,
                "rutaId" => (int) $ruta->getId(),
                "busId" => $s->getBus()?->getId(),
                "empresaId" => $s->getEmpresa()?->getId(),
                "vendidos" => $vendidos[$s->getId()] ?? 0,
                "capacidad" => $capacidad[$s->getBus()?->getId()] ?? null,
            ];
        }
        $rutas = array_values($rutas);
        usort($rutas, static fn(array $a, array $b) => strnatcasecmp($a["nombre"], $b["nombre"]));

        return ["items" => $items, "rutas" => $rutas, "porEstado" => $porEstado, "truncado" => $truncado];
    }

    /** Todo el filtro salvo el estado (el conteo por estado lo ignora). */
    private function base(FiltroSalidas $f): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Salida::class, "s")
            ->join("s.ruta", "r")
            ->leftJoin("s.bus", "b")
            ->leftJoin("s.empresa", "e");
        $iguales = ["s.empresa" => $f->empresa, "s.ruta" => $f->ruta, "s.bus" => $f->bus];
        $n = 0;
        foreach ($iguales as $campo => $valor) {
            if ($valor !== null) {
                $qb->andWhere("{$campo} = :p" . ++$n)->setParameter("p{$n}", $valor);
            }
        }
        // Origen y destino: las puntas de la ruta.
        if ($f->origen !== null) {
            $qb->andWhere("EXISTS (SELECT 1 FROM App\Entity\Parada po WHERE po.ruta = s.ruta AND po.orden = 0 AND po.enclave = :origen)")
                ->setParameter("origen", $f->origen);
        }
        if ($f->destino !== null) {
            $qb->andWhere("EXISTS (SELECT 1 FROM App\Entity\Parada pd WHERE pd.ruta = s.ruta AND pd.enclave = :destino
                AND pd.orden = (SELECT MAX(px.orden) FROM App\Entity\Parada px WHERE px.ruta = s.ruta))")
                ->setParameter("destino", $f->destino);
        }
        if ($f->desde !== null) {
            $qb->andWhere("s.fecha >= :desde")->setParameter("desde", $f->desde);
        }
        if ($f->hasta !== null) {
            $qb->andWhere("s.fecha < :hasta")->setParameter("hasta", $f->hasta->modify("+1 day"));
        }

        return $qb;
    }

    /**
     * @param list<int> $busIds
     *
     * @return array<int, int> asientos por bus
     */
    private function capacidad(array $busIds): array
    {
        if ($busIds === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(a.bus) AS bus", "COUNT(a.id) AS n")
            ->from(Asiento::class, "a")
            ->where("a.bus IN (:buses)")
            ->groupBy("a.bus")
            ->setParameter("buses", $busIds)
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn(array $f) => [(int) $f["bus"], (int) $f["n"]], $filas), 1, 0);
    }
}
