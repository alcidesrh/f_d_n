<?php

declare(strict_types=1);

namespace App\Migration\Salida;

use App\Croquis\ElementoCroquis;
use App\Croquis\Moldes;
use App\Entity\Enum\AsientoClase;
use App\Migration\Mapeador;
use App\Salida\AgendaBus;
use Doctrine\DBAL\Connection;

/**
 * Bus para una salida del legado que no lo tiene (ADR-027). En el legado se
 * vende con el tipo de bus y el bus se asigna cerca de partir; en el modelo
 * nuevo toda salida tiene bus, así que la migración lo infiere:
 *
 * 1. `hora`: el bus de la salida más reciente (anterior a esta y a hoy) con
 *    el mismo tipo de bus, empresa, ruta y hora.
 * 2. `franja`: igual, con la hora a ±60 minutos.
 * 3. `flota`: cualquier bus de la empresa con el croquis del tipo.
 *
 * Un candidato sirve si es de la empresa, tiene exactamente el croquis del
 * tipo (los boletos se pasan por número) y está libre a esa hora, tanto en
 * la base nueva como en el legado. Si ninguno sirve: null.
 */
class InferenciaBus
{
    public const HORA = "hora";
    public const FRANJA = "franja";
    public const FLOTA = "flota";

    private const FRANJA_MINUTOS = 60;
    private const CANDIDATOS = 40;
    /** `salida_estado` del legado: cancelada. */
    private const LEGADO_CANCELADA = 4;

    /** @var array<int, string|null> tipo de bus → firma de su croquis */
    private array $firmas = [];

    public function __construct(
        private readonly Connection $db,
        private readonly LectorLegado $legado,
        private readonly Mapeador $mapeador,
        private readonly DependenciasLegado $dependencias,
    ) {}

    /**
     * @param array<string, mixed> $salida fila de la salida del legado (`SELECT_SALIDA`)
     *
     * @return array{bus: int, criterio: string, referencia: ?string}|array{motivo: string}
     */
    public function inferir(array $salida, int $empresaId, int $trayectoId, \DateTimeImmutable $ahora): array
    {
        $tipo = (int) ($salida["tipo_bus_id"] ?: ($salida["it_tipo_bus_id"] ?? 0));
        if ($tipo <= 0) {
            return ["motivo" => "sin_tipo_bus"];
        }
        $firma = $this->firmaDeTipo($tipo);
        if ($firma === null) {
            return ["motivo" => "tipo_sin_asientos"];
        }

        $fecha = new \DateTimeImmutable((string) $this->mapeador->salida($salida, null, null)["fecha"]);
        $limite = min($fecha, $ahora);
        $minutos = $this->duracion($trayectoId);
        $legacyId = (string) $salida["id"];
        $filtro = [
            "tipo" => $tipo,
            "empresa" => (int) ($salida["empresa_id"] ?: ($salida["it_empresa_id"] ?? 0)),
            "ruta" => (string) ($salida["ruta_codigo"] ?? ""),
            "hora" => $fecha->format("H:i:s"),
            "limite" => $limite->format("Y-m-d H:i:s"),
        ];

        foreach ([self::HORA => "CONVERT(time, s.fecha) = CAST(:hora AS time)", self::FRANJA => "ABS(DATEDIFF(minute, CONVERT(time, s.fecha), CAST(:hora AS time))) <= " . self::FRANJA_MINUTOS] as $criterio => $condicion) {
            foreach ($this->anteriores($filtro, $condicion) as $codigo => $referencia) {
                $codigo = (string) $codigo; // las claves numéricas llegan como int
                $bus = $this->dependencias->bus($codigo, $empresaId);
                if ($bus !== null && $this->sirve($bus, $codigo, $empresaId, $firma, $fecha, $minutos, $legacyId)) {
                    return ["bus" => $bus, "criterio" => $criterio, "referencia" => $referencia];
                }
            }
        }

        foreach ($this->db->fetchAllAssociative(
            "SELECT b.id, b.codigo FROM bus b JOIN croquis c ON c.id = b.croquis_id
              WHERE b.empresa_id = :empresa AND c.firma = :firma ORDER BY b.codigo",
            ["empresa" => $empresaId, "firma" => $firma],
        ) as $b) {
            if ($this->sirve((int) $b["id"], (string) $b["codigo"], $empresaId, $firma, $fecha, $minutos, $legacyId)) {
                return ["bus" => (int) $b["id"], "criterio" => self::FLOTA, "referencia" => null];
            }
        }

        return ["motivo" => "sin_bus_libre"];
    }

    /**
     * Firma del croquis de un tipo de bus del legado, armado como lo copia la
     * migración a cada bus (`DependenciasLegado::croquis`): sin números ni
     * celdas repetidos.
     */
    public function firmaDeTipo(int $tipo): ?string
    {
        if (array_key_exists($tipo, $this->firmas)) {
            return $this->firmas[$tipo];
        }
        $elementos = [];
        $numeros = [];
        foreach ($this->legado->filas("SELECT * FROM bus_asiento WHERE tipoBus_id = :tipo", ["tipo" => $tipo]) as $old) {
            $d = $this->mapeador->asiento($old, 0);
            if (isset($numeros[$d["numero"]])) {
                continue;
            }
            $numeros[$d["numero"]] = true;
            $elementos[] = new ElementoCroquis(ElementoCroquis::ASIENTO, $d["planta"], $d["fila"], $d["columna"], $d["numero"], AsientoClase::from($d["clase"]));
        }
        $celdas = [];
        foreach ($this->legado->filas(
            "SELECT s.*, t.nombre AS tipo_nombre FROM bus_senal s JOIN bus_senal_tipo t ON t.id = s.tipo_id WHERE s.tipoBus_id = :tipo",
            ["tipo" => $tipo],
        ) as $old) {
            $d = $this->mapeador->senal($old, 0);
            if ($d === null || isset($celdas[$c = "{$d["planta"]}:{$d["fila"]}:{$d["columna"]}"])) {
                continue;
            }
            $celdas[$c] = true;
            $elementos[] = new ElementoCroquis($d["tipo"], $d["planta"], $d["fila"], $d["columna"]);
        }

        return $this->firmas[$tipo] = Moldes::firma($elementos);
    }

    /**
     * Buses de las salidas anteriores que cumplen el filtro, de la más
     * reciente hacia atrás: código → id de esa salida del legado.
     *
     * @param array<string, mixed> $filtro
     *
     * @return array<string, string>
     */
    private function anteriores(array $filtro, string $condicion): array
    {
        $candidatos = [];
        foreach ($this->legado->filas(
            "SELECT TOP " . self::CANDIDATOS . " s.id, s.bus_codigo
               FROM salida s
               LEFT JOIN itineario i ON i.id = s.itinerario_id
              WHERE s.bus_codigo IS NOT NULL AND s.bus_codigo <> ''
                AND COALESCE(s.tipo_bus_id, i.tipo_bus_id) = :tipo
                AND COALESCE(s.empresa_id, i.empresa_id) = :empresa
                AND i.ruta_codigo = :ruta
                AND s.fecha < CAST(:limite AS datetime)
                AND {$condicion}
              ORDER BY s.fecha DESC",
            $filtro,
        ) as $f) {
            $candidatos[trim((string) $f["bus_codigo"])] ??= (string) $f["id"];
        }

        return $candidatos;
    }

    /** De la empresa, con el croquis del tipo y libre a esa hora (base nueva y legado). */
    private function sirve(int $bus, string $codigo, int $empresaId, string $firma, \DateTimeImmutable $fecha, ?int $minutos, string $legacyId): bool
    {
        $ok = $this->db->fetchOne(
            "SELECT 1 FROM bus b JOIN croquis c ON c.id = b.croquis_id WHERE b.id = :bus AND b.empresa_id = :empresa AND c.firma = :firma",
            ["bus" => $bus, "empresa" => $empresaId, "firma" => $firma],
        );
        if ($ok === false) {
            return false;
        }

        $agenda = new AgendaBus();
        $desde = $fecha->modify("-3 days")->format("Y-m-d H:i:s");
        $hasta = $fecha->modify("+3 days")->format("Y-m-d H:i:s");
        foreach ($this->db->fetchAllAssociative(
            "SELECT s.fecha, t.duracion_estimada_minutos AS minutos FROM salida s JOIN trayecto t ON t.id = s.trayecto_id
              WHERE s.bus_id = :bus AND s.estado <> 'cancelada' AND s.fecha BETWEEN :desde AND :hasta
                AND (s.legacy_id IS NULL OR s.legacy_id <> :lid)",
            ["bus" => $bus, "desde" => $desde, "hasta" => $hasta, "lid" => $legacyId],
        ) as $s) {
            $agenda->ocupar($bus, new \DateTimeImmutable((string) $s["fecha"]), $s["minutos"] === null ? null : (int) $s["minutos"], true);
        }
        // En el legado, las salidas que el bus ya tiene asignadas (aún sin migrar); con la duración de esta.
        foreach ($this->legado->filas(
            "SELECT CONVERT(varchar(19), fecha, 120) AS fecha FROM salida
              WHERE bus_codigo = :codigo AND id <> :lid AND COALESCE(estado_id, 0) <> " . self::LEGADO_CANCELADA . "
                AND fecha BETWEEN CAST(:desde AS datetime) AND CAST(:hasta AS datetime)",
            ["codigo" => $codigo, "lid" => $legacyId, "desde" => $desde, "hasta" => $hasta],
        ) as $s) {
            $agenda->ocupar($bus, new \DateTimeImmutable((string) $s["fecha"]), $minutos, true);
        }

        return $agenda->choque($bus, $fecha, $minutos) === null;
    }

    private function duracion(int $trayectoId): ?int
    {
        $m = $this->db->fetchOne("SELECT duracion_estimada_minutos FROM trayecto WHERE id = :id", ["id" => $trayectoId]);

        return $m === false || $m === null ? null : (int) $m;
    }
}
