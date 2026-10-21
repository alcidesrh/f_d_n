<?php

declare(strict_types=1);

namespace App\Venta\Boleto\Listado;

use App\Entity\Agencia;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Cliente;
use App\Entity\Empresa;
use App\Entity\Estacion;
use App\Entity\Factura;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\PagoWeb;
use App\Entity\TipoPago;
use App\Entity\Usuario;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Boleto\Flujo\FlujoBoleto;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Listado de boletos (el "Boletos" del legado): una fila por asiento vendido
 * con su salida, documento, trayecto, clientes, precio y quién lo vendió, y
 * el detalle de uno ("Consultar"). Solo lectura. Un usuario de agencia solo
 * ve lo vendido por su agencia; el filtro de empresa (ADR-015) acota las
 * salidas como en el resto de la API.
 */
final class ListadoBoletos
{
    private const ORDEN_DQL = [
        "id" => "b.id",
        "fecha" => "s.fecha",
        "numero" => "a.numero",
        "ruta" => "r.nombre",
        "clienteDocumento" => "cd.nombre",
        "clienteBoleto" => "cb.nombre",
        "creada" => "v.createdAt",
    ];

    /** Uniones que agregan los filtros y el orden: alias => [campo, LEFT JOIN]. */
    private const UNIONES = [
        "a" => ["b.asiento", false],
        "t" => ["b.trayecto", false],
        "o" => ["t.origen", false],
        "d" => ["t.destino", false],
        "r" => ["s.ruta", false],
        "f" => ["v.factura", true],
        "cd" => ["v.cliente", true],
        "cb" => ["b.cliente", true],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FlujoBoleto $flujo,
    ) {}

    /**
     * Dos pasos: los ids de la página (solo con las uniones que piden los
     * filtros y el orden) y luego las filas con pocas consultas simples. Una
     * sola consulta con todas las uniones tarda segundos en planificarse.
     *
     * @return array{items: list<array<string, mixed>>, total: int, pagina: int, porPagina: int, desde: string, hasta: string}
     */
    public function buscar(FiltroBoletos $f, Usuario $usuario): array
    {
        $qb = $this->base($usuario);
        $this->filtrar($qb, $f);

        $total = (int) (clone $qb)->select("COUNT(b.id)")->getQuery()->getSingleScalarResult();

        $dir = $f->descendente ? "DESC" : "ASC";
        $orden = self::ORDEN_DQL[$f->orden];
        $this->unir($qb, explode(".", $orden)[0]);
        $ids = array_map("intval", array_column($qb
            ->select("b.id", sprintf("%s AS HIDDEN orden", $orden))
            ->orderBy("orden", $dir)
            ->addOrderBy("b.id", $dir)
            ->setFirstResult(($f->pagina - 1) * $f->porPagina)
            ->setMaxResults($f->porPagina)
            ->getQuery()
            ->getScalarResult(), "id"));
        $boletos = $this->cargar($ids);
        $referencias = $this->referencias($boletos);

        return [
            "items" => array_map(fn(BoletoAsiento $b) => $this->fila($b, $referencias), $boletos),
            "total" => $total,
            "pagina" => $f->pagina,
            "porPagina" => $f->porPagina,
            "desde" => $f->desde->format("Y-m-d"),
            "hasta" => $f->hasta->format("Y-m-d"),
        ];
    }

    /**
     * "Consultar": la fila del listado más la venta, la factura, los demás
     * boletos de la venta y las reasignaciones.
     *
     * @return array<string, mixed>
     *
     * @throws VentaRechazada si no existe o el usuario no lo puede ver
     */
    public function detalle(int $id, Usuario $usuario): array
    {
        $visible = $this->base($usuario)->select("b.id")->andWhere("b.id = :id")->setParameter("id", $id)->getQuery()->getOneOrNullResult();
        $b = $visible === null ? null : ($this->cargar([$id])[0] ?? null);
        if ($b === null) {
            throw new VentaRechazada("El boleto no existe.", "no_encontrado", 404);
        }
        $venta = $b->getBoletoVenta();
        $factura = $venta->getFactura();
        $reasignadoA = $this->em->getRepository(BoletoAsiento::class)->findOneBy(["reasignadoDe" => $b]);

        return $this->fila($b, $this->referencias([$b])) + [
            "observacion" => $b->getObservacion(),
            "reasignadoA" => $reasignadoA?->getId(),
            "salida" => [
                "id" => $b->getSalida()->getId(),
                "fecha" => $b->getSalida()->getFecha()->format(DATE_ATOM),
                "estado" => $b->getSalida()->getEstado()->value,
                "empresa" => $b->getSalida()->getEmpresa()?->getNombreCorto(),
                "bus" => $b->getSalida()->getBus()?->getCodigo(),
            ],
            "venta" => [
                "id" => $venta->getId(),
                "canal" => $venta->getCanal()->value,
                "estado" => $venta->getEstado()->value,
                "estadoFacturacion" => $venta->getEstadoFacturacion()->value,
                "errorFacturacion" => $venta->getErrorFacturacion(),
                "total" => DatosBoleto::importe($venta->getTotal()),
                "agencia" => $venta->getAgencia()?->getNombre(),
                "enviarCorreo" => $venta->isEnviarCorreo(),
            ],
            "factura" => $factura === null ? null : [
                "serie" => $factura->getSerie(),
                "numero" => $factura->getDte(),
                "uuid" => $factura->getUuid()?->toRfc4122(),
                "certificada" => $factura->getFechaCertificacion()?->format(DATE_ATOM),
                "receptorNit" => $factura->getReceptopNit(),
                "receptorNombre" => $factura->getReceptorNombre(),
                "anulada" => $factura->getAnuladaEn()?->format(DATE_ATOM),
                "motivoAnulacion" => $factura->getMotivoAnulacion(),
                "urlPdf" => $factura->getUrlPdf(),
            ],
            "otros" => array_values(array_map(
                static fn(BoletoAsiento $o) => ["id" => $o->getId(), "asiento" => $o->getAsiento()->getNumero(), "estado" => $o->getEstado()->value],
                array_filter($venta->getAsientos()->toArray(), static fn(BoletoAsiento $o) => $o->getId() !== $b->getId()),
            )),
        ];
    }

    /**
     * Tipo de documento de la venta, el "Documento" del legado: voucher y
     * cortesía no se cobran; la factura certificada; la que espera al
     * certificador; lo de agencia (sin factura) y el resto.
     */
    public static function documento(BoletoVenta $v): string
    {
        return match (true) {
            $v->isVoucher() => "voucher",
            $v->isCortesia() => "cortesia",
            $v->getFactura()?->getDte() !== null => "factura",
            $v->getEstadoFacturacion() === EstadoFacturacion::PENDIENTE => "pendiente",
            $v->getAgencia() !== null => "agencia",
            default => "sin_factura",
        };
    }

    /** Boletos visibles para el usuario, con la venta y la salida (las demás uniones las agrega `unir`). */
    private function base(Usuario $usuario): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->select("b")
            ->from(BoletoAsiento::class, "b")
            ->join("b.boletoVenta", "v")
            ->join("b.salida", "s");
        $agencia = $this->em->find(Usuario::class, $usuario->getId())?->getAgencia();
        if ($agencia !== null) {
            $qb->andWhere("v.agencia = :agenciaUsuario")->setParameter("agenciaUsuario", $agencia->getId());
        }

        return $qb;
    }

    /** Agrega (una vez) la unión de un alias que usa un filtro o el orden. */
    private function unir(QueryBuilder $qb, string $alias): void
    {
        if (in_array($alias, $qb->getAllAliases(), true)) {
            return;
        }
        [$campo, $izquierda] = self::UNIONES[$alias] ?? [null, false];
        if ($campo === null) {
            return;
        }
        $this->unir($qb, explode(".", $campo)[0]);
        $izquierda ? $qb->leftJoin($campo, $alias) : $qb->join($campo, $alias);
    }

    /**
     * Las filas de esos ids, en ese orden: el boleto con su viaje en una
     * consulta y lo demás de la venta por lotes (sin N+1).
     *
     * @param list<int> $ids
     *
     * @return list<BoletoAsiento>
     */
    private function cargar(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->createQueryBuilder()
            ->select("b", "a", "v", "s", "t", "o", "d", "r")
            ->from(BoletoAsiento::class, "b")
            ->join("b.asiento", "a")
            ->join("b.boletoVenta", "v")
            ->join("b.salida", "s")
            ->join("b.trayecto", "t")
            ->join("t.origen", "o")
            ->join("t.destino", "d")
            ->join("s.ruta", "r")
            ->where("b.id IN (:ids)")
            ->setParameter("ids", $ids, ArrayParameterType::INTEGER)
            ->getQuery()
            ->getResult();

        // Lo relacionado, una consulta por entidad: inicializa las referencias ya cargadas.
        $ventas = array_map(static fn(BoletoAsiento $b) => $b->getBoletoVenta(), $boletos);
        $relacionados = [
            Cliente::class => [...array_map(static fn(BoletoAsiento $b) => $b->getCliente(), $boletos), ...array_map(static fn(BoletoVenta $v) => $v->getCliente(), $ventas)],
            Factura::class => array_map(static fn(BoletoVenta $v) => $v->getFactura(), $ventas),
            Usuario::class => array_map(static fn(BoletoVenta $v) => $v->getUsuario(), $ventas),
            Estacion::class => array_map(static fn(BoletoVenta $v) => $v->getEstacion(), $ventas),
            Agencia::class => array_map(static fn(BoletoVenta $v) => $v->getAgencia(), $ventas),
            TipoPago::class => array_map(static fn(BoletoVenta $v) => $v->getTipoPago(), $ventas),
            Empresa::class => array_map(static fn(BoletoAsiento $b) => $b->getSalida()->getEmpresa(), $boletos),
        ];
        foreach ($relacionados as $clase => $entidades) {
            $idsRel = array_values(array_unique(array_filter(array_map(static fn(?object $e) => $e?->getId(), $entidades))));
            if ($idsRel !== []) {
                $this->em->getRepository($clase)->findBy(["id" => $idsRel]);
            }
        }

        $posicion = array_flip($ids);
        usort($boletos, static fn(BoletoAsiento $x, BoletoAsiento $y) => $posicion[$x->getId()] <=> $posicion[$y->getId()]);

        return $boletos;
    }

    private function filtrar(QueryBuilder $qb, FiltroBoletos $f): void
    {
        // Un id exacto o una salida concreta se buscan en cualquier fecha (enlaces del chat, "Consultar" de otro boleto).
        if ($f->id === null && $f->salida === null) {
            $qb->andWhere("(s.fecha >= :desde AND s.fecha < :fin) OR (v.createdAt >= :desde AND v.createdAt < :fin)")
                ->setParameter("desde", $f->desde)
                ->setParameter("fin", $f->finExclusivo());
        }

        $iguales = [
            "b.id" => $f->id,
            "IDENTITY(b.reasignadoDe)" => $f->reasignadoDe,
            "s.id" => $f->salida,
            "t.origen" => $f->origen,
            "t.destino" => $f->destino,
            "a.numero" => $f->numeroAsiento,
            "a.clase" => $f->clase?->value,
            "f.dte" => $f->numeroFactura,
            "IDENTITY(v.cliente)" => $f->clienteDocumento,
            "IDENTITY(b.cliente)" => $f->clienteBoleto,
        ];
        $i = 0;
        foreach ($iguales as $campo => $valor) {
            if ($valor !== null) {
                $this->unir($qb, explode(".", $campo)[0]);
                $qb->andWhere(sprintf("%s = :igual%d", $campo, $i))->setParameter("igual" . $i++, $valor);
            }
        }

        $contiene = [
            "f.serie" => $f->serieFactura,
            "v.referenciaPago" => $f->autorizacion,
        ];
        foreach ($contiene as $campo => $texto) {
            if ($texto !== null) {
                $this->unir($qb, explode(".", $campo)[0]);
                $qb->andWhere(sprintf("LOWER(%s) LIKE :contiene%d", $campo, $i))->setParameter("contiene" . $i++, self::patron($texto));
            }
        }

        if ($f->estados !== []) {
            $qb->andWhere("b.estado IN (:estados)")->setParameter("estados", array_map(static fn($e) => $e->value, $f->estados));
        }
        if ($f->referenciaExterna !== null) {
            $qb->andWhere(sprintf("EXISTS (SELECT 1 FROM %s pr WHERE (pr.boletoVenta = v OR pr.boletoVentaRegreso = v) AND LOWER(pr.referenciaPasarela) LIKE :referencia)", PagoWeb::class))
                ->setParameter("referencia", self::patron($f->referenciaExterna));
        }
        if ($f->documento !== null) {
            $this->unir($qb, "f");
            $qb->andWhere(match ($f->documento) {
                "voucher" => "v.voucher = true",
                "cortesia" => "v.voucher = false AND v.cortesia = true",
                "factura" => "v.voucher = false AND v.cortesia = false AND f.dte IS NOT NULL",
                "pendiente" => "v.voucher = false AND v.cortesia = false AND f.dte IS NULL AND v.estadoFacturacion = 'pendiente'",
                "agencia" => "v.voucher = false AND v.cortesia = false AND f.dte IS NULL AND v.estadoFacturacion <> 'pendiente' AND v.agencia IS NOT NULL",
                default => "v.voucher = false AND v.cortesia = false AND f.dte IS NULL AND v.estadoFacturacion <> 'pendiente' AND v.agencia IS NULL",
            });
        }
    }

    /**
     * @param array<int, string> $referencias referencia de la pasarela por id de venta
     *
     * @return array<string, mixed>
     */
    private function fila(BoletoAsiento $b, array $referencias): array
    {
        $venta = $b->getBoletoVenta();
        $salida = $b->getSalida();
        $ruta = $salida->getRuta();
        $factura = $venta->getFactura();
        $usuario = $venta->getUsuario();

        return [
            "id" => $b->getId(),
            "estado" => $b->getEstado()->value,
            "fecha" => $salida->getFecha()->format(DATE_ATOM),
            "salidaId" => $salida->getId(),
            "salidaEstado" => $salida->getEstado()->value,
            "empresa" => $salida->getEmpresa()?->getNombreCorto(),
            "asiento" => $b->getAsiento()->getNumero(),
            "clase" => $b->getAsiento()->getClase()->value,
            "documento" => [
                "tipo" => self::documento($venta),
                "serie" => $factura?->getSerie(),
                "numero" => $factura?->getDte(),
            ],
            "ruta" => trim(sprintf("%s - %s", $ruta->getLegacyId() ?? "", $ruta->getNombre()), " -"),
            "sube" => $b->getTrayecto()->getOrigen()?->getNombre(),
            "baja" => $b->getTrayecto()->getDestino()?->getNombre(),
            "clienteDocumento" => self::cliente($venta->getCliente(), true),
            "clienteBoleto" => self::cliente($b->getCliente(), false),
            "tipoPago" => $venta->getTipoPago()?->getNombre(),
            "precio" => DatosBoleto::importe($b->getPrecio()),
            "sinCobro" => $venta->isVoucher() || $venta->isCortesia(),
            "canal" => $venta->getCanal()->value,
            "ventaId" => $venta->getId(),
            "ventaConfirmada" => $venta->getEstado()->value === "confirmada",
            "reasignadoDe" => $b->getReasignadoDe()?->getId(),
            "referenciaExterna" => $referencias[(int) $venta->getId()] ?? null,
            "autorizacion" => $venta->getReferenciaPago() ?: null,
            "creada" => $venta->getCreada()?->format(DATE_ATOM),
            "usuario" => $usuario === null ? null : (trim((string) $usuario->getFullName()) ?: $usuario->getUsername()),
            "estacion" => $venta->getEstacion()?->getNombre() ?? $venta->getAgencia()?->getNombre(),
            // Lo que el boleto admite ahora (null) o por qué no, para el panel de acciones (ADR-037).
            "acciones" => $this->flujo->acciones($b),
        ];
    }

    /** @return array{id: int, nombre: string, documento: ?string, email: ?string}|null */
    private static function cliente(?Cliente $c, bool $nit): ?array
    {
        if ($c === null) {
            return null;
        }

        return [
            "id" => (int) $c->getId(),
            "nombre" => $c->getNombreCompleto(),
            "documento" => $nit ? ($c->getNit() ?: "CF") : $c->getNumeroDocumento(),
            "email" => $c->getEmail(),
        ];
    }

    /**
     * Referencia de la pasarela por id de venta (ida y regreso del pago web).
     *
     * @param list<BoletoAsiento> $boletos
     *
     * @return array<int, string>
     */
    private function referencias(array $boletos): array
    {
        $ids = array_values(array_unique(array_map(static fn(BoletoAsiento $b) => (int) $b->getBoletoVenta()->getId(), $boletos)));
        if ($ids === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(p.boletoVenta) AS ida", "IDENTITY(p.boletoVentaRegreso) AS regreso", "p.referenciaPasarela AS referencia")
            ->from(PagoWeb::class, "p")
            ->where("p.referenciaPasarela IS NOT NULL")
            ->andWhere("p.boletoVenta IN (:ids) OR p.boletoVentaRegreso IN (:ids)")
            ->setParameter("ids", $ids, ArrayParameterType::INTEGER)
            ->getQuery()
            ->getArrayResult();
        $mapa = [];
        foreach ($filas as $fila) {
            foreach (["ida", "regreso"] as $campo) {
                if ($fila[$campo] !== null) {
                    $mapa[(int) $fila[$campo]] = (string) $fila["referencia"];
                }
            }
        }

        return $mapa;
    }

    private static function patron(string $texto): string
    {
        return "%" . addcslashes(mb_strtolower($texto), "%_\\") . "%";
    }
}
