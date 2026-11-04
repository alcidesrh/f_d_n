<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

use App\Bitacora\TipoRegistro;
use App\Entity\Base\PersonaBase;
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
            [self::pilotoDe($bus?->getPiloto()), self::pilotoDe($bus?->getCopiloto())],
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

    /**
     * Encomiendas de la más antigua a la más nueva, con solo las columnas de
     * cada `LineaEncomienda` (sin hidratar sus 20 relaciones ni el XML de la
     * factura). Los artículos van aparte (`articulos()`): unirlos repetía la fila.
     */
    private function base(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select(
                "e.id AS id",
                "e.creada AS creada",
                "e.estado AS estado",
                "e.modalidad AS modalidad",
                "e.total.monto AS total",
                "e.total.moneda AS moneda",
                "IDENTITY(e.boletoVenta) AS ventaId",
                "e.numeroAcceso AS numeroAcceso",
                "e.estadoFacturacion AS estadoFacturacion",
                "e.entregadaEn AS entregadaEn",
                "e.motivoAnulacion AS motivoAnulacion",
                "e.observacion AS observacion",
                "em.nombre AS empresa",
                "o.nombre AS origen",
                "d.nombre AS destino",
                "d.alias AS destinoAlias",
                "f.serie AS serie",
                "f.dte AS dte",
                "f.anuladaEn AS facturaAnuladaEn",
                "usr.username AS usuario",
                "est.nombre AS estacion",
                "rem.nombre AS remitenteNombre",
                "rem.apellido AS remitenteApellido",
                "des.nombre AS destinatarioNombre",
                "des.apellido AS destinatarioApellido",
                "rec.nombre AS receptorNombre",
                "rec.apellido AS receptorApellido",
                "cf.nombre AS clienteFacturaNombre",
                "cf.apellido AS clienteFacturaApellido",
                "s.id AS salidaId",
                "s.fecha AS salidaFecha",
                "bus.codigo AS busCodigo",
                "pil.nombre AS pilotoNombre",
                "pil.apellido AS pilotoApellido",
                "pil.codigo AS pilotoCodigo",
                "cop.nombre AS copilotoNombre",
                "cop.apellido AS copilotoApellido",
                "cop.codigo AS copilotoCodigo",
                "r.nombre AS ruta",
                "sr.nombre AS rutaSalida",
            )
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
            ->leftJoin("e.usuario", "usr")
            ->leftJoin("e.estacion", "est")
            ->leftJoin("e.factura", "f")
            ->leftJoin("e.ruta", "r")
            ->leftJoin("e.receptor", "rec")
            ->leftJoin("e.clienteFactura", "cf")
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
        /** @var list<array<string, mixed>> $filas */
        $filas = $qb->getQuery()->getArrayResult();
        $ids = array_map(static fn(array $f) => (int) $f["id"], $filas);
        $eventos = $this->eventos($ids);
        $articulos = $this->articulos($ids);

        return array_map(static function (array $f) use ($eventos, $articulos): LineaEncomienda {
            $id = (int) $f["id"];
            $suyos = $articulos[$id] ?? [];
            $tipos = array_values(array_unique(array_map(static fn(EncomiendaArticulo $a) => $a->getTipo()->value, $suyos)));
            $entrega = $eventos[$id][OperacionEncomienda::ENTREGADA->value] ?? null;
            $anulacion = $eventos[$id][OperacionEncomienda::ANULADA->value] ?? null;
            $cliente = static fn(string $quien): ?string => $f["{$quien}Nombre"] === null && $f["{$quien}Apellido"] === null
                ? null
                : PersonaBase::nombreCompletoDe($f["{$quien}Nombre"], $f["{$quien}Apellido"]);

            return new LineaEncomienda(
                $id,
                Encomienda::codigoDe($id),
                $f["creada"],
                (string) $f["empresa"],
                (string) $f["origen"],
                (string) $f["destino"],
                $f["destinoAlias"],
                self::valor($f["estado"]),
                self::valor($f["modalidad"]),
                (int) $f["total"],
                (string) $f["moneda"],
                $f["ventaId"] === null ? null : (int) $f["ventaId"],
                $f["serie"],
                $f["dte"] === null ? null : (int) $f["dte"],
                $f["facturaAnuladaEn"] !== null,
                $f["numeroAcceso"],
                self::valor($f["estadoFacturacion"]),
                $f["usuario"] ?? "—",
                (string) $f["estacion"],
                (string) $cliente("remitente"),
                (string) $cliente("destinatario"),
                $cliente("receptor"),
                $cliente("clienteFactura"),
                implode("; ", array_map(static fn(EncomiendaArticulo $a) => $a->getResumen(), $suyos)),
                array_sum(array_map(static fn(EncomiendaArticulo $a) => $a->getCantidad(), $suyos)),
                match (true) {
                    $tipos === [TipoArticuloEncomienda::ESPECIAL->value] => "Especial",
                    $tipos === [TipoArticuloEncomienda::PAQUETE->value] => "Paquete",
                    default => "Mixto",
                },
                $f["salidaId"] === null ? null : (int) $f["salidaId"],
                $f["salidaFecha"] === null ? null : \DateTimeImmutable::createFromInterface($f["salidaFecha"]),
                $f["busCodigo"],
                [self::piloto($f["pilotoNombre"], $f["pilotoApellido"], $f["pilotoCodigo"]), self::piloto($f["copilotoNombre"], $f["copilotoApellido"], $f["copilotoCodigo"])],
                $f["ruta"],
                $f["rutaSalida"],
                $f["entregadaEn"],
                $entrega["usuario"] ?? null,
                $anulacion["fecha"] ?? null,
                $anulacion["usuario"] ?? null,
                $f["motivoAnulacion"],
                $f["observacion"],
            );
        }, $filas);
    }

    /**
     * Los artículos de las encomiendas, por id, en el orden de la colección
     * (`Encomienda::articulos`): entidades, para su `getResumen()`.
     *
     * @param list<int> $ids
     *
     * @return array<int, list<EncomiendaArticulo>>
     */
    private function articulos(array $ids): array
    {
        $mapa = [];
        foreach (array_chunk($ids, 1000) as $lote) {
            $filas = $this->em->createQueryBuilder()
                ->select("a", "esp", "IDENTITY(a.encomienda) AS encomienda")
                ->from(EncomiendaArticulo::class, "a")
                ->leftJoin("a.especial", "esp")
                ->where("a.encomienda IN (:ids)")
                ->setParameter("ids", $lote, ArrayParameterType::INTEGER)
                ->orderBy("a.id")
                ->getQuery()
                ->getResult();
            foreach ($filas as $fila) {
                $mapa[(int) $fila["encomienda"]][] = $fila[0];
            }
        }

        return $mapa;
    }

    /** Un enum de Doctrine llega como enum o como su valor, según la versión del hidratador. */
    private static function valor(mixed $valor): string
    {
        return $valor instanceof \BackedEnum ? (string) $valor->value : (string) $valor;
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

    private static function pilotoDe(?Piloto $p): string
    {
        return self::piloto($p?->getNombre(), $p?->getApellido(), $p?->getCodigo());
    }

    /** Nombre en mayúsculas del piloto (su código si no tiene); sin piloto (`$codigo` null), "N/D". */
    private static function piloto(?string $nombre, ?string $apellido, ?string $codigo): string
    {
        if ($codigo === null) {
            return "N/D";
        }
        $completo = mb_strtoupper(PersonaBase::nombreCompletoDe($nombre, $apellido));

        return $completo === "" ? $codigo : $completo;
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
