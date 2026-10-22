<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Encomienda;
use App\Entity\EncomiendaArticulo;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Estacion;
use App\Entity\Salida;
use App\Entity\SalidaParada;
use App\Venta\Boleto\DatosBoleto;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Lecturas de encomiendas (ADR-035): el listado con filtros, las salidas de
 * una estación para procesar y, por salida, lo que va a bordo y lo que espera
 * en bodega. `$empresas` limita a esas empresas (el alcance de quien consulta,
 * ADR-039); null = todas.
 */
final class ConsultaEncomiendas
{
    public const POR_PAGINA = [10, 15, 25, 50, 100];
    /** Tope de los listados de pendientes (no se paginan). */
    public const MAX_PENDIENTES = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FlujoEncomienda $flujo,
    ) {}

    /**
     * @param array<string, mixed> $f `q` (código o cliente), `estados`, `modalidad`, `origen`, `destino`, `ubicacion`, `salida`, `desde`, `hasta` (AAAA-MM-DD, de registro), `pagina`, `porPagina`
     *
     * @return array{items: list<array<string, mixed>>, total: int, pagina: int, porPagina: int}
     */
    public function listado(array $f, ?array $empresas): array
    {
        $porPagina = (int) ($f["porPagina"] ?? 25);
        $porPagina = in_array($porPagina, self::POR_PAGINA, true) ? $porPagina : 25;
        $pagina = max(1, (int) ($f["pagina"] ?? 1));

        $qb = $this->em->createQueryBuilder()->from(Encomienda::class, "e");
        $this->filtrar($qb, $f, $empresas);

        $total = (int) (clone $qb)->select("COUNT(e.id)")->getQuery()->getSingleScalarResult();
        /** @var list<int> $ids */
        $ids = array_map("intval", array_column(
            (clone $qb)->select("e.id")->orderBy("e.id", "DESC")->setFirstResult(($pagina - 1) * $porPagina)->setMaxResults($porPagina)->getQuery()->getScalarResult(),
            "id",
        ));

        return ["items" => $this->filas($ids), "total" => $total, "pagina" => $pagina, "porPagina" => $porPagina];
    }

    /**
     * Salidas que pasan por la estación ese día (por la hora en esa parada),
     * con lo que llevan, lo que bajan ahí y lo que podrían subir. Con
     * `$destino`, si cada una llega hasta ahí (`llegaADestino`).
     *
     * @return list<array<string, mixed>>
     */
    public function salidasDeEstacion(\DateTimeImmutable $dia, Estacion $estacion, ?array $empresas, ?int $destino = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("sp", "s", "r", "b", "em")
            ->from(SalidaParada::class, "sp")
            ->join("sp.salida", "s")
            ->leftJoin("s.ruta", "r")
            ->leftJoin("s.bus", "b")
            ->leftJoin("s.empresa", "em")
            ->where("sp.enclave = :estacion AND sp.hora >= :desde AND sp.hora < :hasta AND s.estado <> :cancelada")
            ->setParameter("estacion", $estacion)
            ->setParameter("desde", $dia->setTime(0, 0))
            ->setParameter("hasta", $dia->setTime(0, 0)->modify("+1 day"))
            ->setParameter("cancelada", EstadoSalida::CANCELADA->value)
            ->orderBy("sp.hora", "ASC");
        if ($empresas !== null) {
            $qb->andWhere("s.empresa IN (:empresas)")->setParameter("empresas", $empresas);
        }
        /** @var list<SalidaParada> $paradas */
        $paradas = $qb->getQuery()->getResult();
        if ($paradas === []) {
            return [];
        }
        $salidaIds = array_map(static fn(SalidaParada $p) => (int) $p->getSalida()->getId(), $paradas);

        $aBordo = $this->contarPorSalida($salidaIds, null);
        $bajan = $this->contarPorSalida($salidaIds, (int) $estacion->getId());
        $bodega = $this->enBodega($estacion, $empresas);

        return array_map(function (SalidaParada $p) use ($aBordo, $bajan, $bodega, $estacion, $destino) {
            $s = $p->getSalida();
            $ids = RutasEncomienda::idsDeSalida($s);
            $aqui = (int) $estacion->getId();
            $suben = count(array_filter($bodega, static fn(array $e) => $e["empresa"] === $s->getEmpresa()?->getId() && ReglasEncomienda::llegaADestino($ids, $aqui, $e["destino"])));

            return [
                "id" => $s->getId(),
                "fecha" => $s->getFecha()?->format(DATE_ATOM),
                "hora" => $p->getHora()->format(DATE_ATOM),
                "ruta" => $s->getRuta()?->getNombre(),
                "origen" => $s->getRuta()?->getOrigen()?->getNombre(),
                "destino" => $s->getRuta()?->getDestino()?->getNombre(),
                "bus" => $s->getBus()?->getCodigo(),
                "empresa" => $s->getEmpresa() === null ? null : ["id" => $s->getEmpresa()->getId(), "nombre" => $s->getEmpresa()->getNombre(), "alias" => $s->getEmpresa()->getAlias()],
                "estado" => $s->getEstado()->value,
                "aBordo" => $aBordo[(int) $s->getId()] ?? 0,
                "bajanAqui" => $bajan[(int) $s->getId()] ?? 0,
                "porEmbarcar" => $suben,
                // Sigue después de esta parada (se le puede subir algo aquí).
                "sigue" => ($i = array_search($aqui, $ids, true)) !== false && $i < count($ids) - 1,
                "llegaADestino" => $destino !== null ? ReglasEncomienda::llegaADestino($ids, $aqui, $destino) : null,
            ];
        }, $paradas);
    }

    /**
     * Lo que va a bordo de la salida y lo que espera en la bodega de la
     * estación y la salida puede llevar (de su empresa, con destino o
     * transbordo más adelante en la ruta).
     *
     * @return array<string, mixed>
     */
    public function procesar(Salida $salida, Estacion $estacion, ?array $empresas): array
    {
        $paradas = RutasEncomienda::paradasDeSalida($salida);
        $ids = array_column($paradas, "id");
        $aqui = (int) $estacion->getId();
        $posicion = array_search($aqui, $ids, true);

        /** @var list<int> $aBordo */
        $aBordo = array_map("intval", array_column($this->em->createQueryBuilder()
            ->select("e.id")
            ->from(Encomienda::class, "e")
            ->where("e.salida = :salida AND e.estado IN (:estados)")
            ->setParameter("salida", $salida)
            ->setParameter("estados", [EstadoEncomienda::EMBARCADA->value, EstadoEncomienda::TRANSITO->value])
            ->orderBy("e.id", "ASC")
            ->getQuery()->getScalarResult(), "id"));

        // Lo que la salida puede subir aquí: lo de su empresa, si sigue más adelante.
        $bodega = $posicion !== false && $posicion < count($ids) - 1 && ($empresas === null || in_array($salida->getEmpresa()?->getId(), $empresas, true))
            ? array_column($this->enBodega($estacion, $salida->getEmpresa()?->getId()), "id")
            : [];

        $marcar = static function (array $fila) use ($ids, $aqui): array {
            $destino = (int) $fila["destino"]["id"];

            return [...$fila, "bajaAqui" => $destino === $aqui, "llegaADestino" => ReglasEncomienda::llegaADestino($ids, $aqui, $destino)];
        };

        return [
            "salida" => [
                "id" => $salida->getId(),
                "fecha" => $salida->getFecha()?->format(DATE_ATOM),
                "ruta" => $salida->getRuta()?->getNombre(),
                "bus" => $salida->getBus()?->getCodigo(),
                "estado" => $salida->getEstado()->value,
                "empresa" => $salida->getEmpresa()?->getNombre(),
            ],
            "estacion" => ["id" => $aqui, "nombre" => $estacion->getNombre()],
            "paradas" => $paradas,
            "pasaPorAqui" => $posicion !== false,
            "aBordo" => array_map($marcar, $this->filas($aBordo)),
            "enBodega" => array_map($marcar, $this->filas($bodega)),
        ];
    }

    /**
     * Lo que espera en la bodega de la estación para salir (recibidas y
     * transbordos), lo más antiguo primero: los «pendientes de envío».
     *
     * @return list<array<string, mixed>>
     */
    public function pendientesEnvio(Estacion $estacion, ?array $empresas): array
    {
        return $this->filas(array_slice(array_column($this->enBodega($estacion, $empresas), "id"), 0, self::MAX_PENDIENTES));
    }

    /**
     * Lo que se puede entregar en la estación: desembarcado aquí o en
     * tránsito en una salida que llega aquí (se desembarca al entregarlo).
     * Con `$q`, solo las de ese código o cliente (remitente o destinatario).
     *
     * @return list<array<string, mixed>>
     */
    public function pendientesEntrega(Estacion $estacion, ?array $empresas, ?string $q = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("e.id", "IDENTITY(e.salida) AS salida")
            ->from(Encomienda::class, "e")
            ->where("e.destino = :estacion")
            ->andWhere("(e.estado = :desembarcada AND e.ubicacion = :estacion) OR e.estado = :transito")
            ->setParameter("estacion", $estacion)
            ->setParameter("desembarcada", EstadoEncomienda::DESEMBARCADA->value)
            ->setParameter("transito", EstadoEncomienda::TRANSITO->value)
            ->orderBy("e.id", "ASC");
        $this->filtrar($qb, ["q" => $q], $empresas);

        $aqui = (int) $estacion->getId();
        $paradas = [];
        $ids = [];
        foreach ($qb->getQuery()->getScalarResult() as $r) {
            $salida = $r["salida"] === null ? null : (int) $r["salida"];
            if ($salida !== null && !isset($paradas[$salida])) {
                $s = $this->em->find(Salida::class, $salida);
                $paradas[$salida] = $s === null ? [] : RutasEncomienda::idsDeSalida($s);
            }
            // En tránsito, solo si su salida pasa por aquí (si no, hará transbordo antes).
            if ($salida === null || in_array($aqui, $paradas[$salida], true)) {
                $ids[] = (int) $r["id"];
            }
        }

        return $this->filas(array_slice($ids, 0, self::MAX_PENDIENTES));
    }

    /**
     * Filas del listado, en el orden de los ids.
     *
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    public function filas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        /** @var list<Encomienda> $encomiendas */
        $encomiendas = $this->em->createQueryBuilder()
            ->select("e", "o", "d", "u", "rem", "des", "s", "em", "a", "esp", "usr", "r")
            ->from(Encomienda::class, "e")
            ->join("e.origen", "o")
            ->join("e.destino", "d")
            ->join("e.remitente", "rem")
            ->join("e.destinatario", "des")
            ->join("e.empresa", "em")
            ->leftJoin("e.ubicacion", "u")
            ->leftJoin("e.salida", "s")
            ->leftJoin("e.articulos", "a")
            ->leftJoin("a.especial", "esp")
            ->leftJoin("e.usuario", "usr")
            ->leftJoin("e.ruta", "r")
            ->where("e.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()
            ->getResult();
        $porId = [];
        foreach ($encomiendas as $e) {
            $porId[(int) $e->getId()] = $e;
        }

        return array_values(array_map(function (int $id) use ($porId) {
            $e = $porId[$id];
            $s = $e->getSalida();

            return [
                "id" => $e->getId(),
                "codigo" => $e->getCodigo(),
                "creada" => $e->getCreada()->format(DATE_ATOM),
                "estado" => $e->getEstado()->value,
                "modalidad" => $e->getModalidad()->value,
                "porCobrar" => $e->getModalidad() === ModalidadEncomienda::POR_COBRAR && $e->getFactura() === null && $e->getNumeroAcceso() === null,
                "empresa" => ["id" => $e->getEmpresa()?->getId(), "nombre" => $e->getEmpresa()?->getNombre(), "alias" => $e->getEmpresa()?->getAlias()],
                "origen" => ["id" => $e->getOrigen()?->getId(), "nombre" => $e->getOrigen()?->getNombre()],
                "destino" => ["id" => $e->getDestino()?->getId(), "nombre" => $e->getDestino()?->getNombre()],
                "ubicacion" => $e->getUbicacion() === null ? null : ["id" => $e->getUbicacion()->getId(), "nombre" => $e->getUbicacion()->getNombre()],
                "salida" => $s === null ? null : ["id" => $s->getId(), "fecha" => $s->getFecha()?->format(DATE_ATOM), "bus" => $s->getBus()?->getCodigo()],
                "ruta" => $e->getRuta() === null ? null : ["id" => $e->getRuta()->getId(), "nombre" => $e->getRuta()->getNombre()],
                "remitente" => DatosEncomienda::cliente($e->getRemitente()),
                "destinatario" => DatosEncomienda::cliente($e->getDestinatario()),
                "bultos" => $e->getBultos(),
                "contenido" => implode("; ", array_map(static fn(EncomiendaArticulo $a) => $a->getResumen(), $e->getArticulos()->toArray())),
                "total" => DatosBoleto::importe($e->getTotal()),
                "estadoFacturacion" => $e->getEstadoFacturacion()->value,
                "venta" => $e->getBoletoVenta()?->getId(),
                "usuario" => $e->getUsuario()?->getUsername(),
                // Qué transiciones admite y, si no, por qué (ADR-038): el panel solo suma el permiso y la estación.
                "acciones" => $this->flujo->acciones($e),
            ];
        }, array_filter($ids, static fn(int $id) => isset($porId[$id]))));
    }

    /**
     * En bodega en la estación (recibidas o desembarcadas), sin las que ya llegaron.
     *
     * @return list<array{id: int, destino: int, empresa: int}>
     */
    private function enBodega(Estacion $estacion, ?array $empresas): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("e.id", "IDENTITY(e.destino) AS destino", "IDENTITY(e.empresa) AS empresa")
            ->from(Encomienda::class, "e")
            ->where("e.ubicacion = :estacion AND e.estado IN (:estados) AND e.destino <> :estacion")
            ->setParameter("estacion", $estacion)
            ->setParameter("estados", [EstadoEncomienda::RECIBIDA->value, EstadoEncomienda::DESEMBARCADA->value])
            ->orderBy("e.id", "ASC");
        if ($empresas !== null) {
            $qb->andWhere("e.empresa IN (:empresas)")->setParameter("empresas", $empresas);
        }

        return array_map(static fn(array $r) => ["id" => (int) $r["id"], "destino" => (int) $r["destino"], "empresa" => (int) $r["empresa"]], $qb->getQuery()->getScalarResult());
    }

    /**
     * Encomiendas a bordo por salida; con `$destino`, solo las que bajan ahí.
     *
     * @param list<int> $salidaIds
     *
     * @return array<int, int>
     */
    private function contarPorSalida(array $salidaIds, ?int $destino): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("IDENTITY(e.salida) AS salida", "COUNT(e.id) AS n")
            ->from(Encomienda::class, "e")
            ->where("e.salida IN (:salidas) AND e.estado IN (:estados)")
            ->setParameter("salidas", $salidaIds)
            ->setParameter("estados", [EstadoEncomienda::EMBARCADA->value, EstadoEncomienda::TRANSITO->value])
            ->groupBy("e.salida");
        if ($destino !== null) {
            $qb->andWhere("e.destino = :destino")->setParameter("destino", $destino);
        }
        $n = [];
        foreach ($qb->getQuery()->getScalarResult() as $r) {
            $n[(int) $r["salida"]] = (int) $r["n"];
        }

        return $n;
    }

    /** @param array<string, mixed> $f */
    private function filtrar(QueryBuilder $qb, array $f, ?array $empresas): void
    {
        if ($empresas !== null) {
            $qb->andWhere("e.empresa IN (:empresas)")->setParameter("empresas", $empresas);
        }
        $q = trim((string) ($f["q"] ?? ""));
        if ($q !== "") {
            if (ctype_digit($q)) {
                $qb->andWhere("e.id = :id")->setParameter("id", (int) $q);
            } else {
                $qb->join("e.remitente", "frem")->join("e.destinatario", "fdes")
                    ->andWhere("LOWER(CONCAT(CONCAT(frem.nombre, ' '), COALESCE(frem.apellido, ''))) LIKE :q OR LOWER(CONCAT(CONCAT(fdes.nombre, ' '), COALESCE(fdes.apellido, ''))) LIKE :q OR frem.nit = :nit OR fdes.nit = :nit")
                    ->setParameter("q", "%" . mb_strtolower($q) . "%")
                    ->setParameter("nit", strtoupper(str_replace(["-", " "], "", $q)));
            }
        }
        $estados = array_values(array_filter(array_map(
            static fn($v) => EstadoEncomienda::tryFrom((string) $v)?->value,
            is_array($f["estados"] ?? null) ? $f["estados"] : array_filter(explode(",", (string) ($f["estados"] ?? ""))),
        )));
        if ($estados !== []) {
            $qb->andWhere("e.estado IN (:estados)")->setParameter("estados", $estados);
        }
        $modalidad = ModalidadEncomienda::tryFrom((string) ($f["modalidad"] ?? ""));
        if ($modalidad !== null) {
            $qb->andWhere("e.modalidad = :modalidad")->setParameter("modalidad", $modalidad->value);
        }
        foreach (["origen", "destino", "ubicacion", "salida"] as $campo) {
            $id = (int) ($f[$campo] ?? 0);
            if ($id > 0) {
                $qb->andWhere("e.{$campo} = :{$campo}")->setParameter($campo, $id);
            }
        }
        $desde = \DateTimeImmutable::createFromFormat("!Y-m-d", (string) ($f["desde"] ?? ""));
        if ($desde !== false) {
            $qb->andWhere("e.creada >= :desde")->setParameter("desde", $desde);
        }
        $hasta = \DateTimeImmutable::createFromFormat("!Y-m-d", (string) ($f["hasta"] ?? ""));
        if ($hasta !== false) {
            $qb->andWhere("e.creada < :hasta")->setParameter("hasta", $hasta->modify("+1 day"));
        }
    }
}
