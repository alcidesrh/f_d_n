<?php

declare(strict_types=1);

namespace App\Inventario;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\PermisosUsuario;
use App\Entity\Almacen;
use App\Entity\Articulo;
use App\Entity\Enum\TipoMovimiento;
use App\Entity\Existencia;
use App\Entity\Movimiento;
use App\Entity\Usuario;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lectura del inventario (ADR-071). Un usuario ve los almacenes donde tiene
 * alguna capacidad `inventario.*` (`ver` sola alcanza para mirar); los
 * almacenes son pocos, así que se filtran aquí con las mismas reglas que al
 * mover (`ReglasInventario::ubicacion`) y lo demás se pide por sus ids.
 */
final class ConsultaInventario
{
    public const POR_PAGINA = 30;

    private const QUE_VEN = [Capacidad::INVENTARIO_VER, Capacidad::INVENTARIO_MOVER, Capacidad::INVENTARIO_AJUSTAR, Capacidad::INVENTARIO_ARTICULOS];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
    ) {}

    /**
     * Los almacenes que ve, con lo que puede hacer en cada uno y cuántos
     * artículos tiene debajo del mínimo.
     *
     * @return list<array<string, mixed>>
     */
    public function almacenes(Usuario $yo): array
    {
        $permisos = $this->autorizacion->de($yo);
        $visibles = $this->visibles($permisos);
        $faltantes = $this->faltantesPorAlmacen(array_map(static fn(Almacen $a) => (int) $a->getId(), $visibles));

        return array_map(fn(Almacen $a) => $this->almacen($a) + [
            "faltantes" => $faltantes[(int) $a->getId()] ?? 0,
            "puede" => $this->puede($permisos, $a),
        ], $visibles);
    }

    /**
     * Lo que hay en un almacén, por artículo, con su estado y cuánto reponer.
     *
     * @return array{almacen: array<string, mixed>, filas: list<array<string, mixed>>, valor: string}
     */
    public function existencias(Usuario $yo, int $almacenId): array
    {
        $permisos = $this->autorizacion->de($yo);
        $almacen = $this->visible($permisos, $almacenId);
        /** @var list<Existencia> $existencias */
        $existencias = $this->em->createQueryBuilder()
            ->select("e", "r")
            ->from(Existencia::class, "e")
            ->join("e.articulo", "r")
            ->where("e.almacen = :almacen")
            ->setParameter("almacen", $almacenId)
            ->orderBy("r.nombre", "ASC")
            ->getQuery()
            ->getResult();
        $enCamino = $this->enCaminoHacia([$almacenId]);
        $valor = 0;
        $filas = [];
        foreach ($existencias as $e) {
            $valor += Cantidad::valorCentavos($e->centesimas(), $e->costo());
            $filas[] = $this->existencia($e, $enCamino[$almacenId][(int) $e->getArticulo()->getId()] ?? 0);
        }

        return [
            "almacen" => $this->almacen($almacen) + ["puede" => $this->puede($permisos, $almacen)],
            "filas" => $filas,
            "valor" => Cantidad::texto($valor),
        ];
    }

    /**
     * El libro de un almacén, lo más nuevo primero.
     *
     * @return array{filas: list<array<string, mixed>>, total: int, pagina: int, porPagina: int}
     */
    public function movimientos(Usuario $yo, int $almacenId, ?int $articulo, int $pagina): array
    {
        $this->visible($this->autorizacion->de($yo), $almacenId);
        $pagina = max(1, $pagina);
        $qb = $this->em->createQueryBuilder()->from(Movimiento::class, "m")->where("m.almacen = :almacen")->setParameter("almacen", $almacenId);
        if ($articulo !== null) {
            $qb->andWhere("m.articulo = :articulo")->setParameter("articulo", $articulo);
        }
        $total = (int) (clone $qb)->select("COUNT(m.id)")->getQuery()->getSingleScalarResult();
        $movimientos = $qb->select("m", "r", "d", "u", "ev", "eva", "af")
            ->join("m.articulo", "r")
            ->leftJoin("m.afectacion", "af")
            ->leftJoin("m.destino", "d")
            ->leftJoin("m.usuario", "u")
            ->leftJoin("m.envio", "ev")
            ->leftJoin("ev.almacen", "eva")
            ->orderBy("m.creadoEn", "DESC")
            ->addOrderBy("m.id", "DESC")
            ->setFirstResult(($pagina - 1) * self::POR_PAGINA)
            ->setMaxResults(self::POR_PAGINA)
            ->getQuery()
            ->getResult();

        return ["filas" => array_map(fn(Movimiento $m) => $this->movimiento($m), $movimientos), "total" => $total, "pagina" => $pagina, "porPagina" => self::POR_PAGINA];
    }

    /**
     * Los envíos que van en camino desde o hacia los almacenes que ve, los
     * más viejos primero; `puedeRecibir` donde tiene `inventario.mover` en
     * el destino.
     *
     * @return list<array<string, mixed>>
     */
    public function enCamino(Usuario $yo): array
    {
        $permisos = $this->autorizacion->de($yo);
        $ids = array_map(static fn(Almacen $a) => (int) $a->getId(), $this->visibles($permisos));
        if ($ids === []) {
            return [];
        }
        /** @var list<Movimiento> $envios */
        $envios = $this->em->createQueryBuilder()
            ->select("m", "r", "o", "d", "u")
            ->from(Movimiento::class, "m")
            ->join("m.articulo", "r")
            ->join("m.almacen", "o")
            ->join("m.destino", "d")
            ->leftJoin("m.usuario", "u")
            ->where("m.tipo = :envio AND m.recibidoEn IS NULL")
            ->andWhere("(m.almacen IN (:ids) OR m.destino IN (:ids))")
            ->setParameter("envio", TipoMovimiento::ENVIO)
            ->setParameter("ids", $ids, ArrayParameterType::INTEGER)
            ->orderBy("m.creadoEn", "ASC")
            ->getQuery()
            ->getResult();

        return array_map(fn(Movimiento $m) => $this->movimiento($m) + [
            "puedeRecibir" => $m->getDestino() !== null && $permisos->puede(Capacidad::INVENTARIO_MOVER->value, ReglasInventario::ubicacion(Capacidad::INVENTARIO_MOVER, $m->getDestino())),
        ], $envios);
    }

    /**
     * Lo que toca reponer en los almacenes activos que ve: debajo del mínimo
     * o en el punto de reorden, con lo que ya viene en camino. Lo más grave
     * primero (agotado, bajo el mínimo, por reponer).
     *
     * @return list<array<string, mixed>>
     */
    public function porReponer(Usuario $yo): array
    {
        $permisos = $this->autorizacion->de($yo);
        $ids = array_map(static fn(Almacen $a) => (int) $a->getId(), array_values(array_filter($this->visibles($permisos), static fn(Almacen $a) => $a->isActivo())));
        if ($ids === []) {
            return [];
        }
        /** @var list<Existencia> $existencias */
        $existencias = $this->em->createQueryBuilder()
            ->select("e", "r", "a")
            ->from(Existencia::class, "e")
            ->join("e.articulo", "r")
            ->join("e.almacen", "a")
            ->where("e.almacen IN (:ids)")
            ->andWhere("((e.minimo > 0 AND e.cantidad < e.minimo) OR (e.puntoReorden IS NOT NULL AND e.cantidad <= e.puntoReorden))")
            ->setParameter("ids", $ids, ArrayParameterType::INTEGER)
            ->orderBy("a.nombre", "ASC")
            ->addOrderBy("r.nombre", "ASC")
            ->getQuery()
            ->getResult();
        $enCamino = $this->enCaminoHacia($ids);
        $peso = [ReglasInventario::AGOTADO => 0, ReglasInventario::BAJO_MINIMO => 1, ReglasInventario::POR_REPONER => 2, ReglasInventario::BIEN => 3];
        $filas = array_map(fn(Existencia $e) => $this->existencia($e, $enCamino[(int) $e->getAlmacen()->getId()][(int) $e->getArticulo()->getId()] ?? 0) + [
            "almacen" => $this->almacen($e->getAlmacen()),
        ], $existencias);
        usort($filas, static fn(array $x, array $y) => $peso[$x["estado"]] <=> $peso[$y["estado"]]);

        return $filas;
    }

    /** @return list<array{id: int, codigo: string, nombre: string, unidad: string, categoria: ?string}> los artículos activos */
    public function articulos(): array
    {
        return array_map(
            static fn(Articulo $r) => ["id" => (int) $r->getId(), "codigo" => $r->getCodigo(), "nombre" => $r->getNombre(), "unidad" => $r->getUnidad(), "categoria" => $r->getCategoria()],
            $this->em->getRepository(Articulo::class)->findBy(["activo" => true], ["nombre" => "ASC"]),
        );
    }

    /**
     * A dónde puede enviar un almacén: los demás activos de su empresa, los
     * vea o no quien envía.
     *
     * @return list<array<string, mixed>>
     */
    public function destinos(Usuario $yo, int $almacenId): array
    {
        $origen = $this->visible($this->autorizacion->de($yo), $almacenId);
        $destinos = $this->em->getRepository(Almacen::class)->findBy(["empresa" => $origen->getEmpresa(), "activo" => true], ["nombre" => "ASC"]);

        return array_values(array_map(fn(Almacen $a) => $this->almacen($a), array_filter($destinos, static fn(Almacen $a) => $a->getId() !== $origen->getId())));
    }

    /** @return array<string, mixed> */
    public function existencia(Existencia $e, int $enCamino = 0): array
    {
        $r = $e->getArticulo();
        $q = $e->centesimas();
        $minimo = $e->minimoCentesimas();
        $reorden = $e->puntoReordenCentesimas();
        $maximo = $e->maximoCentesimas();

        return [
            "id" => (int) $e->getId(),
            "articulo" => ["id" => (int) $r->getId(), "codigo" => $r->getCodigo(), "nombre" => $r->getNombre(), "unidad" => $r->getUnidad(), "categoria" => $r->getCategoria(), "activo" => $r->isActivo()],
            "cantidad" => $e->getCantidad(),
            "costoPromedio" => $e->getCostoPromedio(),
            "valor" => Cantidad::texto(Cantidad::valorCentavos($q, $e->costo())),
            "minimo" => $e->getMinimo(),
            "puntoReorden" => $e->getPuntoReorden(),
            "maximo" => $e->getMaximo(),
            "estado" => ReglasInventario::estado($q, $minimo, $reorden),
            "reponer" => Cantidad::texto(max(0, ReglasInventario::reponer($q, $minimo, $reorden, $maximo) - $enCamino)),
            "enCamino" => Cantidad::texto($enCamino),
        ];
    }

    /** @return array<string, mixed> */
    public function movimiento(Movimiento $m): array
    {
        $r = $m->getArticulo();
        $u = $m->getUsuario();
        $lugar = static fn(?Almacen $a) => $a === null ? null : ["id" => (int) $a->getId(), "nombre" => $a->getNombre()];
        $afectacion = $m->getAfectacion();

        return [
            "id" => (int) $m->getId(),
            "tipo" => $m->getTipo()->value,
            "etiqueta" => $m->getTipo()->etiqueta(),
            "almacen" => $lugar($m->getAlmacen()),
            "articulo" => ["id" => (int) $r->getId(), "codigo" => $r->getCodigo(), "nombre" => $r->getNombre(), "unidad" => $r->getUnidad()],
            "cantidad" => $m->getCantidad(),
            "costoUnitario" => $m->getCostoUnitario(),
            "saldo" => $m->getSaldo(),
            "documento" => $m->getDocumento(),
            "motivo" => $m->getMotivo(),
            "destino" => $lugar($m->getDestino()),
            "origen" => $lugar($m->getEnvio()?->getAlmacen()),
            "recibidoEn" => $m->getRecibidoEn()?->format(DATE_ATOM),
            "afectacion" => $afectacion === null ? null : ["id" => (int) $afectacion->getId(), "descripcion" => $afectacion->getDescripcion()],
            "usuario" => $u === null ? null : ["id" => (int) $u->getId(), "nombre" => trim((string) $u->getFullName()) ?: $u->getUsername()],
            "creadoEn" => $m->getCreadoEn()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function almacen(Almacen $a): array
    {
        return [
            "id" => (int) $a->getId(),
            "nombre" => $a->getNombre(),
            "tipo" => $a->getTipo()->value,
            "tipoEtiqueta" => $a->getTipo()->etiqueta(),
            "activo" => $a->isActivo(),
            "empresa" => $a->getEmpresa() === null ? null : ["id" => (int) $a->getEmpresa()->getId(), "nombre" => (string) $a->getEmpresa()->getNombre()],
            "estacion" => $a->getEstacion() === null ? null : ["id" => (int) $a->getEstacion()->getId(), "nombre" => (string) $a->getEstacion()->getNombre()],
            "bus" => $a->getBus() === null ? null : ["id" => (int) $a->getBus()->getId(), "codigo" => $a->getBus()->getCodigo()],
        ];
    }

    /** @return array{mover: bool, ajustar: bool, niveles: bool} */
    private function puede(PermisosUsuario $permisos, Almacen $a): array
    {
        $puede = static fn(Capacidad $c) => $permisos->puede($c->value, ReglasInventario::ubicacion($c, $a));

        return ["mover" => $puede(Capacidad::INVENTARIO_MOVER), "ajustar" => $puede(Capacidad::INVENTARIO_AJUSTAR), "niveles" => $puede(Capacidad::INVENTARIO_ARTICULOS)];
    }

    /** @return list<Almacen> los que ve, activos primero y por nombre */
    private function visibles(PermisosUsuario $permisos): array
    {
        if (!array_filter(self::QUE_VEN, static fn(Capacidad $c) => $permisos->tiene($c->value))) {
            return [];
        }
        $todos = $this->em->createQueryBuilder()
            ->select("a", "e", "s", "b")
            ->from(Almacen::class, "a")
            ->leftJoin("a.empresa", "e")
            ->leftJoin("a.estacion", "s")
            ->leftJoin("a.bus", "b")
            ->orderBy("a.activo", "DESC")
            ->addOrderBy("a.nombre", "ASC")
            ->getQuery()
            ->getResult();

        return array_values(array_filter($todos, fn(Almacen $a) => $this->ve($permisos, $a)));
    }

    private function ve(PermisosUsuario $permisos, Almacen $a): bool
    {
        foreach (self::QUE_VEN as $c) {
            if ($permisos->puede($c->value, ReglasInventario::ubicacion($c, $a))) {
                return true;
            }
        }

        return false;
    }

    private function visible(PermisosUsuario $permisos, int $id): Almacen
    {
        $a = $this->em->find(Almacen::class, $id);
        if ($a === null || !$this->ve($permisos, $a)) {
            throw new InventarioRechazado("El almacén no existe.", "almacen_no_existe", 404);
        }

        return $a;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, int> almacén → artículos debajo del mínimo
     */
    private function faltantesPorAlmacen(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(e.almacen) AS almacen", "COUNT(e.id) AS n")
            ->from(Existencia::class, "e")
            ->where("e.almacen IN (:ids) AND e.minimo > 0 AND e.cantidad < e.minimo")
            ->setParameter("ids", $ids, ArrayParameterType::INTEGER)
            ->groupBy("e.almacen")
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn(array $f) => ["a" => (int) $f["almacen"], "n" => (int) $f["n"]], $filas), "n", "a");
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<int, int>> almacén → artículo → centésimas en camino hacia él
     */
    private function enCaminoHacia(array $ids): array
    {
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(m.destino) AS almacen", "IDENTITY(m.articulo) AS articulo", "SUM(m.cantidad) AS q")
            ->from(Movimiento::class, "m")
            ->where("m.tipo = :envio AND m.recibidoEn IS NULL AND m.destino IN (:ids)")
            ->setParameter("envio", TipoMovimiento::ENVIO)
            ->setParameter("ids", $ids, ArrayParameterType::INTEGER)
            ->groupBy("m.destino", "m.articulo")
            ->getQuery()
            ->getArrayResult();
        $mapa = [];
        foreach ($filas as $f) {
            // El envío lleva la cantidad en negativo (sale del origen).
            $mapa[(int) $f["almacen"]][(int) $f["articulo"]] = -(int) Cantidad::centesimas((string) $f["q"]);
        }

        return $mapa;
    }
}
