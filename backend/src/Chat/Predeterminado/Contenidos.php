<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado;

use App\Bitacora\TipoRegistro;
use App\Chat\Tarjeta\TarjetaOcupacionSalida;
use App\Entity\BoletoAsiento;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\Enum\OperacionTarifa;
use App\Entity\Enum\TipoMensajePredeterminado;
use App\Entity\MensajePredeterminado;
use App\Entity\Salida;
use App\Reporte\Diario\ConsultaIncidencias;
use App\Reporte\Diario\ResumenDiario;
use App\Reporte\Dinero;
use App\Reporte\ReportePdf;
use App\Tablero\ConsultaTablero;
use App\Tablero\Periodo;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Arma lo que dice cada tipo de mensaje predeterminado (ADR-045), con las
 * mismas consultas que las pantallas. A diferencia de los reportes por
 * suscripción, no se recorta al alcance de cada destinatario: la audiencia la
 * eligió un administrador, como quien reenvía un reporte. Las tarjetas sí se
 * resuelven con los permisos de quien lee.
 */
final class Contenidos
{
    /** Quién figura como "generado por" en los PDF. */
    public const AUTOR = "Mensajes predeterminados";

    /** Una salida cuya hora pasó hace más que esto ya no se avisa (el programador estuvo detenido). */
    public const VENTANA_SALIDA = "-6 hours";

    /** Tope de líneas (y tarjetas) de anomalías por mensaje. */
    public const MAX_ANOMALIAS = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly ReportePdf $pdf,
        private readonly ConsultaTablero $tablero,
        private readonly ConsultaIncidencias $incidencias,
    ) {}

    /**
     * Salidas de la ruta y empresa de la instancia cuya hora de salida cae
     * entre `$desde` y `$hasta` (con `$sinAvisar`, las que esta instancia aún
     * no avisó). Cuenta la hora programada, no el estado: casi nadie marca
     * una salida como iniciada. Las canceladas no salen.
     *
     * @return list<int>
     */
    public function salidasIniciadas(MensajePredeterminado $m, \DateTimeImmutable $desde, \DateTimeImmutable $hasta, bool $sinAvisar = false): array
    {
        if ($m->getRuta() === null || $m->getEmpresa() === null) {
            return [];
        }

        return array_map("intval", $this->db->fetchFirstColumn(
            "SELECT s.id FROM salida s
              WHERE s.ruta_id = :ruta AND s.empresa_id = :empresa AND s.estado <> :cancelada
                AND s.fecha >= :desde AND s.fecha <= :hasta
                AND (NOT CAST(:sinAvisar AS BOOLEAN) OR NOT EXISTS (
                    SELECT 1 FROM envio_mensaje_predeterminado e WHERE e.mensaje_id = :mensaje AND e.clave = 'salida:' || s.id))
              ORDER BY s.fecha, s.id",
            [
                "ruta" => $m->getRuta()->getId(),
                "empresa" => $m->getEmpresa()->getId(),
                "cancelada" => EstadoSalida::CANCELADA->value,
                "desde" => $desde->format("Y-m-d H:i:s"),
                "hasta" => $hasta->format("Y-m-d H:i:s"),
                "sinAvisar" => $sinAvisar ? "true" : "false",
                "mensaje" => (int) $m->getId(),
            ],
        ));
    }

    public function salidaIniciada(int $salidaId): ?Pieza
    {
        $salida = $this->em->find(Salida::class, $salidaId);
        if ($salida?->getFecha() === null) {
            return null;
        }
        $ocupacion = $this->ocupacion($salida);
        $ficha = self::ficha($salida);

        // Solo las dos tarjetas, en este orden: la salida y su ocupación por vendedor.
        return new Pieza(
            "salida:" . $salidaId,
            "",
            [["tipo" => "Salida", "id" => $salidaId], ["tipo" => TarjetaOcupacionSalida::TIPO, "id" => $salidaId]],
            [[
                "nombre" => sprintf("salida_iniciada_%d_%s.pdf", $salidaId, $salida->getFecha()->format("Ymd_Hi")),
                "contenido" => $this->pdf->salidaIniciada($ocupacion, $ficha, self::AUTOR),
            ]],
        );
    }

    /** La ocupación de la salida con lo vendido hasta ahora (también la tarjeta, al leer). */
    public function ocupacion(Salida $salida): OcupacionAlIniciar
    {
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->createQuery(
            "SELECT b, a, v, u FROM App\Entity\BoletoAsiento b JOIN b.asiento a JOIN b.boletoVenta v LEFT JOIN v.usuario u
              WHERE b.salida = :salida AND v.estado = :confirmada",
        )->setParameter("salida", $salida)->setParameter("confirmada", EstadoBoletoVenta::CONFIRMADA)->getResult();

        $filas = array_map(static function (BoletoAsiento $b): array {
            $v = $b->getBoletoVenta();
            $u = $v?->getUsuario();

            return [
                "asiento" => (int) $b->getAsiento()?->getId(),
                "estado" => $b->getEstado()->value,
                "canal" => $v?->getCanal()->value ?? "estacion",
                "usuario" => $u === null ? "Página web" : (string) $u->getUsername(),
                "voucher" => (bool) $v?->isVoucher(),
                "cortesia" => (bool) $v?->isCortesia(),
                "clase" => $b->getAsiento()?->getClase()->value ?? "A",
                "sube" => (int) $b->getOrdenSube(),
                "baja" => (int) $b->getOrdenBaja(),
                "reasignado" => $b->getReasignadoDe() !== null,
            ];
        }, $boletos);

        $ultimaParada = max(0, ($salida->getRuta()?->getParadas()->count() ?? 1) - 1);

        return OcupacionAlIniciar::de($filas, $salida->getBus()?->getAsientos()->count() ?? 0, $ultimaParada);
    }

    /** @return array{ruta: string, inicio: string, bus: ?string, empresa: ?string} */
    public static function ficha(Salida $salida): array
    {
        return [
            "ruta" => self::ruta($salida),
            "inicio" => $salida->getFecha() === null ? "?" : self::formato("EEE d 'de' MMM, h:mm a")->format($salida->getFecha()),
            "bus" => $salida->getBus()?->getCodigo(),
            "empresa" => $salida->getEmpresa()?->getNombreCorto() ?: $salida->getEmpresa()?->getNombre(),
        ];
    }

    /** Totales del día de la empresa y desglose por estación (y por canal). */
    public function ventasDelDia(MensajePredeterminado $m, \DateTimeImmutable $ahora): Pieza
    {
        $empresa = $m->getEmpresa();
        $t = $this->tablero->tablero(new Periodo(1, $ahora), $empresa?->getId());
        $k = $t->kpis();
        $q = static fn(int $c) => Dinero::con($c, ConsultaTablero::MONEDA);
        $lineas = [
            sprintf("Ventas del %s · %s", ResumenDiario::fechaLarga($ahora), $empresa?->getNombre() ?? "Todas las empresas"),
            sprintf("Total: %s en %d boletos.", $q((int) $k["ingresos"]["actual"]), (int) $k["boletos"]["actual"]),
            sprintf("Anulados: %d (%s). Cortesías: %d.", (int) $k["anulados"]["actual"], $q((int) $k["montoAnulado"]["actual"]), (int) $k["cortesias"]["actual"]),
        ];
        $canales = array_filter($t->canales(), static fn(array $c) => $c["boletos"] > 0);
        if ($canales !== []) {
            $lineas[] = "Por canal: " . implode(" · ", array_map(static fn(array $c) => sprintf("%s %s (%d)", self::canal($c["canal"]), $q($c["ingresos"]), $c["boletos"]), $canales)) . ".";
        }
        $puntos = array_filter($t->puntos(null), static fn(array $p) => $p["boletos"] > 0);
        $estaciones = array_filter($puntos, static fn(array $p) => $p["tipo"] === "estacion");
        if ($estaciones !== []) {
            $lineas[] = "Por estación:";
            foreach ($estaciones as $p) {
                $lineas[] = sprintf("• %s: %s en %d boletos", $p["nombre"], $q($p["ingresos"]), $p["boletos"]);
            }
        }
        $otros = array_filter($puntos, static fn(array $p) => $p["tipo"] !== "estacion");
        foreach ($otros as $p) {
            $lineas[] = sprintf("• %s (%s): %s en %d boletos", $p["nombre"], self::canal($p["tipo"]), $q($p["ingresos"]), $p["boletos"]);
        }
        if ((int) $k["boletos"]["actual"] === 0) {
            $lineas[] = "Hoy no hubo ventas.";
        }

        return new Pieza("dia:" . $ahora->format("Y-m-d"), implode("\n", $lineas));
    }

    /** El resumen del día (como el reporte de cada mañana) del mismo día, al cierre. */
    public function desempenoDelDia(MensajePredeterminado $m, \DateTimeImmutable $ahora): Pieza
    {
        $empresa = $m->getEmpresa();
        $dia = $ahora->setTime(0, 0);
        $t = $this->tablero->tablero(new Periodo(1, $ahora), $empresa?->getId());
        $titulo = sprintf("Desempeño del %s", ResumenDiario::fechaLarga($dia));
        $seccion = ResumenDiario::seccion($empresa?->getNombre() ?? "Todas las empresas", $t, $this->incidencias->porUsuario($dia, $empresa?->getId()));

        return new Pieza(
            "dia:" . $dia->format("Y-m-d"),
            $seccion === null ? sprintf("%s\nHoy no hubo venta, ni salidas, ni nada que atender.", $titulo) : ResumenDiario::texto($titulo, [$seccion]),
        );
    }

    /**
     * Las anomalías del día `$dia` (de 00:00 a 24:00) del tipo de la
     * instancia; null si no hubo.
     */
    public function anomalias(MensajePredeterminado $m, \DateTimeImmutable $dia): ?Pieza
    {
        $desde = $dia->setTime(0, 0);
        $hasta = $desde->modify("+1 day");
        $lista = match ($m->getTipo()) {
            TipoMensajePredeterminado::ANOMALIA_REASIGNACION => $this->reasignacionesRepetidas($desde, $hasta, $m->getEmpresa()?->getId()),
            TipoMensajePredeterminado::ANOMALIA_TARIFA => $this->tarifasCambiadas($desde, $hasta, $m->getEmpresa()?->getId()),
            default => [],
        };
        if ($lista === []) {
            return null;
        }
        $titulo = $m->getTipo() === TipoMensajePredeterminado::ANOMALIA_TARIFA ? "Anomalía en tarifa" : "Anomalía en reasignación";
        $resto = count($lista) - self::MAX_ANOMALIAS;
        $lista = array_slice($lista, 0, self::MAX_ANOMALIAS);
        $lineas = [
            sprintf("%s · %s (%d)", $titulo, ResumenDiario::fechaLarga($desde), count($lista) + max(0, $resto)),
            ...array_map(static fn(array $a) => "• " . $a["texto"], $lista),
        ];
        if ($resto > 0) {
            $lineas[] = sprintf("Hay %d más: se ven en la bitácora de cada registro.", $resto);
        }

        return new Pieza(
            "anomalias:" . $desde->format("Y-m-d"),
            implode("\n", $lineas),
            array_map(static fn(array $a) => $a["tarjeta"], $lista),
        );
    }

    /**
     * Boletos creados en el día por una reasignación que ya van por la
     * tercera o más (la cadena `reasignadoDe`). Solo el último de cada cadena.
     *
     * @return list<array{texto: string, tarjeta: array{tipo: string, id: int}}>
     */
    public function reasignacionesRepetidas(\DateTimeImmutable $desde, \DateTimeImmutable $hasta, ?int $empresa): array
    {
        $filas = $this->db->fetchAllAssociative(
            "WITH RECURSIVE nuevos AS (
                 SELECT DISTINCT bi.registro_id AS id FROM bitacora bi
                  WHERE bi.entidad = :boleto AND bi.operacion = :creado AND bi.fecha >= :desde AND bi.fecha < :hasta
             ), cadena AS (
                 SELECT b.id AS hoja, b.reasignado_de_id AS previo, 1 AS veces
                   FROM boleto_asiento b JOIN nuevos n ON n.id = b.id
                  WHERE b.reasignado_de_id IS NOT NULL
                    AND NOT EXISTS (SELECT 1 FROM boleto_asiento sig JOIN nuevos ns ON ns.id = sig.id WHERE sig.reasignado_de_id = b.id)
                 UNION ALL
                 SELECT c.hoja, b.reasignado_de_id, c.veces + 1
                   FROM cadena c JOIN boleto_asiento b ON b.id = c.previo
                  WHERE b.reasignado_de_id IS NOT NULL AND c.veces < 50
             )
             SELECT c.hoja AS id, MAX(c.veces) AS veces
               FROM cadena c JOIN boleto_asiento b ON b.id = c.hoja JOIN salida s ON s.id = b.salida_id
              WHERE (CAST(:empresa AS INT) IS NULL OR s.empresa_id = CAST(:empresa AS INT))
              GROUP BY c.hoja HAVING MAX(c.veces) > 2 ORDER BY c.hoja",
            [
                "boleto" => TipoRegistro::BOLETO->value,
                "creado" => OperacionBoleto::CREADO->value,
                "desde" => $desde->format("Y-m-d H:i:s"),
                "hasta" => $hasta->format("Y-m-d H:i:s"),
                "empresa" => $empresa,
            ],
        );
        $r = [];
        foreach ($filas as $f) {
            $b = $this->em->find(BoletoAsiento::class, (int) $f["id"]);
            if ($b === null) {
                continue;
            }
            $r[] = [
                "texto" => sprintf(
                    "Boleto %d (asiento %s, %s): reasignado %d veces. Referencia: bitácora del boleto %d.",
                    $b->getId(),
                    $b->getAsiento()?->getNumero() ?? "?",
                    $b->getSalida() === null ? "sin salida" : self::ruta($b->getSalida()) . ", " . ($b->getSalida()->getFecha() === null ? "?" : self::formato("d/MM h:mm a")->format($b->getSalida()->getFecha())),
                    (int) $f["veces"],
                    $b->getId(),
                ),
                "tarjeta" => ["tipo" => "BoletoAsiento", "id" => (int) $b->getId()],
            ];
        }

        return $r;
    }

    /**
     * Tarifas creadas, modificadas o eliminadas en el día (su bitácora). Con
     * empresa, las de esa empresa y las que no tienen (valen para todas).
     *
     * @return list<array{texto: string, tarjeta: array{tipo: string, id: int}}>
     */
    public function tarifasCambiadas(\DateTimeImmutable $desde, \DateTimeImmutable $hasta, ?int $empresa): array
    {
        $filas = $this->db->fetchAllAssociative(
            "SELECT bi.registro_id AS id,
                    STRING_AGG(DISTINCT bi.operacion, ',') AS operaciones,
                    STRING_AGG(DISTINCT COALESCE(bi.usuario_nombre, '?'), ', ') AS usuarios,
                    MAX(bi.detalle::text) AS detalle
               FROM bitacora bi LEFT JOIN boleto_tarifa t ON t.id = bi.registro_id
              WHERE bi.entidad = :tarifa AND bi.fecha >= :desde AND bi.fecha < :hasta
                AND (CAST(:empresa AS INT) IS NULL OR t.id IS NULL OR t.empresa_id IS NULL OR t.empresa_id = CAST(:empresa AS INT))
              GROUP BY bi.registro_id ORDER BY bi.registro_id",
            [
                "tarifa" => TipoRegistro::TARIFA->value,
                "desde" => $desde->format("Y-m-d H:i:s"),
                "hasta" => $hasta->format("Y-m-d H:i:s"),
                "empresa" => $empresa,
            ],
        );
        $etiquetas = [];
        foreach (OperacionTarifa::cases() as $o) {
            $etiquetas[$o->value] = mb_strtolower(str_replace("Tarifa ", "", $o->etiqueta()));
        }

        return array_map(static function (array $f) use ($etiquetas): array {
            $detalle = json_decode((string) $f["detalle"], true) ?: [];
            $ops = array_map(static fn(string $o) => $etiquetas[$o] ?? $o, explode(",", (string) $f["operaciones"]));

            return [
                "texto" => sprintf(
                    "Tarifa %d (%s, clase %s, %s): %s por %s. Referencia: bitácora de la tarifa %d.",
                    $f["id"],
                    $detalle["trayecto"] ?? "?",
                    $detalle["clase"] ?? "?",
                    $detalle["precio"] ?? "sin precio",
                    implode(" y ", $ops),
                    $f["usuarios"],
                    $f["id"],
                ),
                "tarjeta" => ["tipo" => "BoletoTarifa", "id" => (int) $f["id"]],
            ];
        }, $filas);
    }

    private static function ruta(Salida $s): string
    {
        return sprintf("%s → %s", $s->getRuta()?->getOrigen()?->getNombre() ?? "?", $s->getRuta()?->getDestino()?->getNombre() ?? "?");
    }

    private static function canal(string $canal): string
    {
        return match ($canal) {
            "estacion" => "Estación",
            "agencia" => "Agencia",
            "web" => "Web",
            default => ucfirst($canal),
        };
    }

    private static function formato(string $patron): \IntlDateFormatter
    {
        return new \IntlDateFormatter("es_GT", \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, "America/Guatemala", null, $patron);
    }
}
