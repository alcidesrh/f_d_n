<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

use App\Bitacora\TipoRegistro;
use App\Entity\Bitacora;
use App\Entity\Empresa;
use App\Entity\Encomienda;
use App\Entity\EncomiendaArticulo;
use App\Entity\Enclave;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Enum\OperacionEncomienda;
use App\Entity\Enum\TipoArticuloEncomienda;
use App\Entity\Piloto;
use App\Entity\Salida;
use App\Entity\Usuario;
use App\Reporte\ReporteRechazado;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/** Lee las encomiendas y arma los reportes de encomiendas (ADR-035). Solo lectura. */
final class ConsultaReportesEncomienda
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** Manifiesto de carga: lo que lleva (o llevó) la salida, por destino. */
    public function manifiesto(FiltroEncomiendas $f): ManifiestoEncomiendas
    {
        $f->exigir("salidaId", "la salida");
        $salida = $this->em->find(Salida::class, $f->salidaId) ?? throw new ReporteRechazado("La salida no existe.", "no_encontrado", 404);
        if ($f->empresaId !== null && $salida->getEmpresa()?->getId() !== $f->empresaId) {
            throw new ReporteRechazado("La salida es de otra empresa.", "otra_empresa", 403);
        }
        $qb = $this->base()
            ->andWhere("e.salida = :salida AND e.estado <> :anulada")
            ->setParameter("salida", $salida)
            ->setParameter("anulada", EstadoEncomienda::ANULADA->value);
        $this->igual($qb, "e.destino", $f->destinoId, "destino");
        $bus = $salida->getBus();

        return new ManifiestoEncomiendas(
            (int) $salida->getId(),
            \DateTimeImmutable::createFromInterface($salida->getFecha()),
            (string) $salida->getRuta()?->getNombre(),
            (string) $salida->getEmpresa()?->getNombre(),
            $bus?->getCodigo(),
            [self::piloto($bus?->getPiloto()), self::piloto($bus?->getCopiloto())],
            $this->nombreEnclave($f->destinoId),
            $this->lineas($qb),
        );
    }

    /** Guías internas (sin cobro) emitidas en el rango. */
    public function guiaInterna(FiltroEncomiendas $f): GuiaInternaEncomiendas
    {
        $qb = $this->base()
            ->andWhere("e.modalidad = :interna")
            ->setParameter("interna", ModalidadEncomienda::INTERNA->value);
        $this->rango($qb, "e.creada", $f);
        $this->igual($qb, "e.estacion", $f->estacionId, "estacion");
        $this->igual($qb, "e.empresa", $f->empresaId, "empresa");

        return new GuiaInternaEncomiendas($f->rotulo(), $this->nombreEnclave($f->estacionId), $this->nombreEmpresa($f->empresaId), $this->lineas($qb));
    }

    /**
     * Facturas propias de encomiendas en el rango: las pagadas al recibirlas
     * (en la estación que las registró) y las por cobrar al entregarlas (en
     * su destino). Sin las anuladas.
     */
    public function detalleFactura(FiltroEncomiendas $f): DetalleFacturaEncomiendas
    {
        $qb = $this->base()
            ->andWhere("(e.modalidad = :pagada AND e.creada >= :desde AND e.creada < :fin" . ($f->estacionId !== null ? " AND e.estacion = :estacion" : "") . ")
                     OR (e.modalidad = :porCobrar AND e.estado = :entregada AND e.entregadaEn >= :desde AND e.entregadaEn < :fin" . ($f->estacionId !== null ? " AND e.destino = :estacion" : "") . ")")
            ->andWhere("e.estado <> :anulada AND e.estadoFacturacion <> :facturaAnulada")
            ->setParameter("pagada", ModalidadEncomienda::PAGADA->value)
            ->setParameter("porCobrar", ModalidadEncomienda::POR_COBRAR->value)
            ->setParameter("entregada", EstadoEncomienda::ENTREGADA->value)
            ->setParameter("anulada", EstadoEncomienda::ANULADA->value)
            ->setParameter("facturaAnulada", "anulada")
            ->setParameter("desde", $f->desde)
            ->setParameter("fin", $f->fin());
        if ($f->estacionId !== null) {
            $qb->setParameter("estacion", $f->estacionId);
        }
        $this->igual($qb, "e.empresa", $f->empresaId, "empresa");

        return new DetalleFacturaEncomiendas($f->rotulo(), $this->nombreEnclave($f->estacionId), $this->nombreEmpresa($f->empresaId), $this->lineas($qb));
    }

    /** Todas las registradas en el rango (sin las que van con boleto), por día, origen y destino. */
    public function detalleGeneral(FiltroEncomiendas $f): DetalleGeneralEncomiendas
    {
        $qb = $this->base()
            ->andWhere("e.modalidad <> :conBoleto")
            ->setParameter("conBoleto", ModalidadEncomienda::CON_BOLETO->value);
        $this->rango($qb, "e.creada", $f);
        $this->igual($qb, "e.empresa", $f->empresaId, "empresa");
        $this->igual($qb, "e.origen", $f->origenId, "origen");
        $this->igual($qb, "e.destino", $f->destinoId, "destino");
        $this->modalidades($qb, $f);

        return new DetalleGeneralEncomiendas(
            $f->rotulo(),
            $this->nombreEmpresa($f->empresaId),
            $this->nombreEnclave($f->origenId),
            $this->nombreEnclave($f->destinoId),
            $this->lineas($qb),
        );
    }

    /** Las que van hacia la estación (o a cualquiera) y todavía no se entregan, por día de registro. */
    public function pendientesEntrega(FiltroEncomiendas $f, \DateTimeImmutable $hoy): PendientesEntregaEncomiendas
    {
        $qb = $this->base()
            ->andWhere("e.estado NOT IN (:finales)")
            ->setParameter("finales", [EstadoEncomienda::ENTREGADA->value, EstadoEncomienda::ANULADA->value]);
        $this->igual($qb, "e.destino", $f->estacionId, "estacion");
        $this->igual($qb, "e.empresa", $f->empresaId, "empresa");
        $this->modalidades($qb, $f);

        return new PendientesEntregaEncomiendas($hoy, $this->nombreEnclave($f->estacionId), $this->nombreEmpresa($f->empresaId), $this->lineas($qb));
    }

    /** Cuadre del día en una estación (o en todas): lo recibido, lo cobrado al entregar, lo enviado y lo anulado. */
    public function cuadre(FiltroEncomiendas $f): CuadreEncomiendas
    {
        $inicio = $f->desde ?? throw new ReporteRechazado("Indique la fecha.", "fecha_invalida", 400);
        $fin = $inicio->modify("+1 day");

        $recibidas = $this->base()
            ->andWhere("e.creada >= :inicio AND e.creada < :fin AND e.modalidad <> :conBoleto")
            ->setParameter("conBoleto", ModalidadEncomienda::CON_BOLETO->value);
        $this->igual($recibidas, "e.estacion", $f->estacionId, "estacion");

        $cobradas = $this->base()
            ->andWhere("e.modalidad = :porCobrar AND e.estado = :entregada AND e.entregadaEn >= :inicio AND e.entregadaEn < :fin")
            ->setParameter("porCobrar", ModalidadEncomienda::POR_COBRAR->value)
            ->setParameter("entregada", EstadoEncomienda::ENTREGADA->value);
        $this->igual($cobradas, "e.destino", $f->estacionId, "estacion");

        $enviadas = $this->base()
            ->andWhere("s.fecha >= :inicio AND s.fecha < :fin AND e.estado <> :anulada")
            ->setParameter("anulada", EstadoEncomienda::ANULADA->value);
        $this->igual($enviadas, "e.origen", $f->estacionId, "estacion");

        $anuladas = $this->base()
            ->andWhere(sprintf(
                "e.estado = :anulada AND e.modalidad <> :conBoleto AND e.id IN (SELECT b.registroId FROM %s b WHERE b.entidad = :entidad AND b.operacion = :operacion AND b.fecha >= :inicio AND b.fecha < :fin)",
                Bitacora::class,
            ))
            ->setParameter("anulada", EstadoEncomienda::ANULADA->value)
            ->setParameter("conBoleto", ModalidadEncomienda::CON_BOLETO->value)
            ->setParameter("entidad", TipoRegistro::ENCOMIENDA->value)
            ->setParameter("operacion", OperacionEncomienda::ANULADA->value);
        $this->igual($anuladas, "e.estacion", $f->estacionId, "estacion");

        $lineas = [];
        foreach (["recibidas" => $recibidas, "cobradas" => $cobradas, "enviadas" => $enviadas, "anuladas" => $anuladas] as $clave => $qb) {
            $qb->setParameter("inicio", $inicio)->setParameter("fin", $fin);
            $this->igual($qb, "e.empresa", $f->empresaId, "empresa");
            $lineas[$clave] = $this->lineas($qb);
        }

        $usuarios = [];
        foreach ($lineas as $grupo) {
            foreach ($grupo as $l) {
                $usuarios[$l->usuario] = true;
                if ($l->entregadaPor !== null) {
                    $usuarios[$l->entregadaPor] = true;
                }
            }
        }

        return new CuadreEncomiendas(
            $inicio,
            $this->nombreEnclave($f->estacionId),
            $this->nombreEmpresa($f->empresaId),
            $lineas["recibidas"],
            $lineas["cobradas"],
            $lineas["enviadas"],
            $lineas["anuladas"],
            $this->nombresUsuarios(array_keys($usuarios)),
        );
    }

    /** Encomiendas con todo lo que los reportes leen ya cargado, de la más antigua a la más nueva. */
    private function base(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select("e", "o", "d", "rem", "des", "em", "s", "bus", "pil", "cop", "sr", "a", "esp", "usr", "est", "f", "r", "rec", "cf", "bv")
            ->from(Encomienda::class, "e")
            ->join("e.origen", "o")
            ->join("e.destino", "d")
            ->join("e.remitente", "rem")
            ->join("e.destinatario", "des")
            ->join("e.empresa", "em")
            ->leftJoin("e.salida", "s")
            ->leftJoin("s.bus", "bus")
            ->leftJoin("bus.piloto", "pil")
            ->leftJoin("bus.copiloto", "cop")
            ->leftJoin("s.ruta", "sr")
            ->leftJoin("e.articulos", "a")
            ->leftJoin("a.especial", "esp")
            ->leftJoin("e.usuario", "usr")
            ->leftJoin("e.estacion", "est")
            ->leftJoin("e.factura", "f")
            ->leftJoin("e.ruta", "r")
            ->leftJoin("e.receptor", "rec")
            ->leftJoin("e.clienteFactura", "cf")
            ->leftJoin("e.boletoVenta", "bv")
            ->where("1 = 1")
            ->orderBy("e.creada")
            ->addOrderBy("e.id");
    }

    private function rango(QueryBuilder $qb, string $campo, FiltroEncomiendas $f): void
    {
        $qb->andWhere("{$campo} >= :desde AND {$campo} < :fin")
            ->setParameter("desde", $f->desde ?? throw new ReporteRechazado("Indique la fecha.", "fecha_invalida", 400))
            ->setParameter("fin", $f->fin());
    }

    private function igual(QueryBuilder $qb, string $campo, ?int $valor, string $parametro): void
    {
        if ($valor !== null) {
            $qb->andWhere("{$campo} = :{$parametro}")->setParameter($parametro, $valor);
        }
    }

    /** Las casillas del legado: solo facturado (pagadas) y/o solo por cobrar. */
    private function modalidades(QueryBuilder $qb, FiltroEncomiendas $f): void
    {
        $modalidades = array_filter([
            $f->soloFacturado ? ModalidadEncomienda::PAGADA->value : null,
            $f->soloPorCobrar ? ModalidadEncomienda::POR_COBRAR->value : null,
        ]);
        if ($modalidades !== []) {
            $qb->andWhere("e.modalidad IN (:modalidades)")->setParameter("modalidades", array_values($modalidades));
        }
    }

    /** @return list<LineaEncomienda> */
    private function lineas(QueryBuilder $qb): array
    {
        /** @var list<Encomienda> $encomiendas */
        $encomiendas = $qb->getQuery()->getResult();
        $eventos = $this->eventos(array_map(static fn(Encomienda $e) => (int) $e->getId(), $encomiendas));

        return array_map(static function (Encomienda $e) use ($eventos): LineaEncomienda {
            $id = (int) $e->getId();
            $s = $e->getSalida();
            $bus = $s?->getBus();
            $f = $e->getFactura();
            $articulos = $e->getArticulos()->toArray();
            $tipos = array_unique(array_map(static fn(EncomiendaArticulo $a) => $a->getTipo()->value, $articulos));
            $entrega = $eventos[$id][OperacionEncomienda::ENTREGADA->value] ?? null;
            $anulacion = $eventos[$id][OperacionEncomienda::ANULADA->value] ?? null;

            return new LineaEncomienda(
                $id,
                $e->getCodigo(),
                $e->getCreada(),
                (string) $e->getEmpresa()?->getNombre(),
                (string) $e->getOrigen()?->getNombre(),
                (string) $e->getDestino()?->getNombre(),
                $e->getDestino()?->getAlias(),
                $e->getEstado()->value,
                $e->getModalidad()->value,
                (int) $e->getTotal()->getAmount(),
                $e->getTotal()->getCurrency()->getCode(),
                $e->getBoletoVenta()?->getId(),
                $f?->getSerie(),
                $f?->getDte(),
                $f?->isAnulada() ?? false,
                $e->getNumeroAcceso(),
                $e->getEstadoFacturacion()->value,
                $e->getUsuario()?->getUsername() ?? "—",
                (string) $e->getEstacion()?->getNombre(),
                (string) $e->getRemitente()?->getNombreCompleto(),
                (string) $e->getDestinatario()?->getNombreCompleto(),
                $e->getReceptor()?->getNombreCompleto(),
                $e->getClienteFactura()?->getNombreCompleto(),
                implode("; ", array_map(static fn(EncomiendaArticulo $a) => $a->getResumen(), $articulos)),
                $e->getBultos(),
                match (true) {
                    $tipos === [TipoArticuloEncomienda::ESPECIAL->value] => "Especial",
                    $tipos === [TipoArticuloEncomienda::PAQUETE->value] => "Paquete",
                    default => "Mixto",
                },
                $s?->getId(),
                $s === null ? null : \DateTimeImmutable::createFromInterface($s->getFecha()),
                $bus?->getCodigo(),
                [self::piloto($bus?->getPiloto()), self::piloto($bus?->getCopiloto())],
                $e->getRuta()?->getNombre(),
                $s?->getRuta()?->getNombre(),
                $e->getEntregadaEn(),
                $entrega["usuario"] ?? null,
                $anulacion["fecha"] ?? null,
                $anulacion["usuario"] ?? null,
                $e->getMotivoAnulacion(),
                $e->getObservacion(),
            );
        }, $encomiendas);
    }

    /**
     * Quién y cuándo se entregó o anuló cada una (la última vez), de la bitácora.
     *
     * @param list<int> $ids
     *
     * @return array<int, array<string, array{usuario: string, fecha: \DateTimeImmutable}>>
     */
    private function eventos(array $ids): array
    {
        $mapa = [];
        foreach (array_chunk($ids, 1000) as $lote) {
            $filas = $this->em->createQueryBuilder()
                ->select("b.registroId AS id", "b.operacion", "b.fecha", "b.usuarioNombre", "u.username")
                ->from(Bitacora::class, "b")
                ->leftJoin("b.usuario", "u")
                ->where("b.entidad = :entidad AND b.registroId IN (:ids) AND b.operacion IN (:operaciones)")
                ->setParameter("entidad", TipoRegistro::ENCOMIENDA->value)
                ->setParameter("ids", $lote, ArrayParameterType::INTEGER)
                ->setParameter("operaciones", [OperacionEncomienda::ENTREGADA->value, OperacionEncomienda::ANULADA->value])
                ->orderBy("b.fecha", "ASC")
                ->getQuery()
                ->getArrayResult();
            foreach ($filas as $r) {
                $mapa[(int) $r["id"]][(string) $r["operacion"]] = [
                    "usuario" => (string) ($r["username"] ?? $r["usuarioNombre"] ?? "—"),
                    "fecha" => \DateTimeImmutable::createFromInterface($r["fecha"]),
                ];
            }
        }

        return $mapa;
    }

    /**
     * @param list<string> $usernames
     *
     * @return array<string, string>
     */
    private function nombresUsuarios(array $usernames): array
    {
        if ($usernames === []) {
            return [];
        }
        $mapa = [];
        foreach ($this->em->getRepository(Usuario::class)->findBy(["username" => $usernames]) as $u) {
            $mapa[(string) $u->getUsername()] = trim((string) $u->getFullName());
        }

        return $mapa;
    }

    private static function piloto(?Piloto $p): string
    {
        if ($p === null) {
            return "N/D";
        }
        $apellido = $p->getApellido() !== "S/N" ? $p->getApellido() : null;
        $nombre = mb_strtoupper(trim(implode(" ", array_filter([$p->getNombre(), $apellido]))));

        return $nombre === "" ? $p->getCodigo() : $nombre;
    }

    private function nombreEnclave(?int $id): ?string
    {
        return $id === null ? null : $this->em->find(Enclave::class, $id)?->getNombre();
    }

    private function nombreEmpresa(?int $id): ?string
    {
        return $id === null ? null : $this->em->find(Empresa::class, $id)?->getNombre();
    }
}
