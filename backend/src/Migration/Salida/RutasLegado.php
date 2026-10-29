<?php

declare(strict_types=1);

namespace App\Migration\Salida;

use App\Ruta\Trayectos;
use Doctrine\DBAL\Connection;

/**
 * Ruta del legado → `ruta` + `parada` + `parada_tiempo` (ADR-030): origen,
 * paradas intermedias de `ruta_estacion_item` por `posicion` y destino; los
 * minutos de `tiempo` (desde el origen, por clase de bus). `parada.minutos`
 * es el menor de las clases (para subir, llegar antes es seguro) y las
 * clases que difieren quedan en `parada_tiempo`.
 *
 * Cada par de paradas en orden tiene su trayecto (`Trayectos`).
 *
 * Idempotente por código (`ruta.legacy_id`): una ruta ya migrada se
 * actualiza. Si cambiaron sus paradas se reemplazan; las salidas existentes
 * conservan su horario (es una foto, `App\Ruta\Horarios`).
 */
class RutasLegado
{
    /** @var array<string, RutaLegada|null> por código */
    private array $rutas = [];

    public function __construct(
        private readonly Connection $db,
        private readonly LectorLegado $legado,
        private readonly DependenciasLegado $dependencias,
        private readonly Trayectos $trayectos,
    ) {}

    /** Null si la ruta no existe o sus puntas no se pueden migrar. */
    public function deCodigo(?string $codigo): ?RutaLegada
    {
        $codigo = trim((string) $codigo);
        if ($codigo === "") {
            return null;
        }
        if (array_key_exists($codigo, $this->rutas)) {
            return $this->rutas[$codigo];
        }
        $ruta = $this->legado->fila("SELECT * FROM ruta WHERE codigo = :codigo", ["codigo" => $codigo]);

        return $this->rutas[$codigo] = $ruta === null ? null : $this->migrar($ruta);
    }

    /** Todas las rutas del legado. @return int cuántas quedaron migradas */
    public function migrarTodas(): int
    {
        $n = 0;
        foreach ($this->legado->filas("SELECT codigo FROM ruta ORDER BY codigo") as $r) {
            if ($this->deCodigo((string) $r["codigo"]) !== null) {
                $n++;
            }
        }

        return $n;
    }

    /** Tras revertir una transacción: lo escrito en ella ya no existe. */
    public function olvidar(): void
    {
        $this->rutas = [];
    }

    /** @param array<string, mixed> $ruta */
    private function migrar(array $ruta): ?RutaLegada
    {
        $codigo = trim((string) $ruta["codigo"]);
        $origen = $this->dependencias->lugar($ruta["estacion_origen_id"] ?? null);
        $destino = $this->dependencias->lugar($ruta["estacion_destino_id"] ?? null);
        if ($origen === null || $destino === null || $origen === $destino) {
            $this->dependencias->contar("ruta_sin_puntas");

            return null;
        }

        $enclaves = [$origen];
        $legadoAEnclave = [(int) $ruta["estacion_origen_id"] => $origen, (int) $ruta["estacion_destino_id"] => $destino];
        foreach ($this->legado->filas(
            "SELECT estacion_id FROM ruta_estacion_item WHERE ruta_codigo = :codigo ORDER BY posicion ASC",
            ["codigo" => $codigo],
        ) as $item) {
            $enclave = $this->dependencias->lugar($item["estacion_id"]);
            if ($enclave === null) {
                $this->dependencias->contar("ruta_parada_no_migrable");
                continue;
            }
            $legadoAEnclave[(int) $item["estacion_id"]] = $enclave;
            if (!in_array($enclave, $enclaves, true) && $enclave !== $destino) {
                $enclaves[] = $enclave;
            }
        }
        $enclaves[] = $destino;

        $minutos = $this->minutos($codigo, $legadoAEnclave);

        $datos = [
            "nombre" => self::nombre($ruta),
            "kilometros" => is_numeric($ruta["kilometros"] ?? null) ? (int) $ruta["kilometros"] : null,
            "activo" => filter_var($ruta["activo"] ?? true, FILTER_VALIDATE_BOOL) ? "true" : "false",
            "legacy_id" => $codigo,
        ];
        $id = $this->db->fetchOne("SELECT id FROM ruta WHERE legacy_id = :c", ["c" => $codigo]);
        if ($id === false) {
            $id = (int) $this->db->fetchOne(
                "INSERT INTO ruta (nombre, kilometros, activo, legacy_id) VALUES (:nombre, :kilometros, :activo, :legacy_id) RETURNING id",
                $datos,
            );
            $this->dependencias->contar("ruta");
        } else {
            $id = (int) $id;
            $this->db->executeStatement(
                "UPDATE ruta SET nombre = :nombre, kilometros = :kilometros, activo = :activo WHERE legacy_id = :legacy_id",
                $datos,
            );
        }

        $actuales = array_map("intval", $this->db->fetchFirstColumn(
            "SELECT enclave_id FROM parada WHERE ruta_id = :r ORDER BY orden",
            ["r" => $id],
        ));
        if ($actuales !== $enclaves) {
            if ($actuales !== []) {
                $this->dependencias->contar("ruta_paradas_cambiadas");
            }
            $this->db->executeStatement("DELETE FROM parada WHERE ruta_id = :r", ["r" => $id]);
            foreach ($enclaves as $orden => $enclave) {
                $this->db->insert("parada", [
                    "ruta_id" => $id,
                    "enclave_id" => $enclave,
                    "orden" => $orden,
                    "permite_subir" => "true",
                    "permite_bajar" => "true",
                ]);
            }
        }
        $this->escribirMinutos($id, $minutos);
        $this->trayectos->asegurarDeRuta($id);

        return new RutaLegada($id, array_flip($enclaves));
    }

    /**
     * Minutos por enclave y clase de bus desde `tiempo`.
     *
     * @param array<int, int> $legadoAEnclave estación del legado → enclave
     *
     * @return array<int, array<int, int>> enclave → clase → minutos
     */
    private function minutos(string $codigo, array $legadoAEnclave): array
    {
        $porEnclave = [];
        foreach ($this->legado->filas(
            "SELECT estacion_destino_id, clasebus_id, minutos FROM tiempo WHERE ruta_codigo = :codigo",
            ["codigo" => $codigo],
        ) as $t) {
            $enclave = $legadoAEnclave[(int) $t["estacion_destino_id"]] ?? null;
            $clase = $this->dependencias->busClase($t["clasebus_id"] ?? null);
            if ($enclave === null || $clase === null || !is_numeric($t["minutos"]) || (int) $t["minutos"] < 0) {
                $this->dependencias->contar("ruta_tiempo_omitido");
                continue;
            }
            $porEnclave[$enclave][$clase] = (int) $t["minutos"];
        }

        return $porEnclave;
    }

    /** @param array<int, array<int, int>> $minutos enclave → clase → minutos */
    private function escribirMinutos(int $rutaId, array $minutos): void
    {
        $this->db->executeStatement(
            "DELETE FROM parada_tiempo WHERE parada_id IN (SELECT id FROM parada WHERE ruta_id = :r)",
            ["r" => $rutaId],
        );
        foreach ($this->db->fetchAllAssociative("SELECT id, enclave_id, orden FROM parada WHERE ruta_id = :r", ["r" => $rutaId]) as $p) {
            $porClase = (int) $p["orden"] === 0 ? [] : ($minutos[(int) $p["enclave_id"]] ?? []);
            $base = $porClase === [] ? null : min($porClase);
            $this->db->executeStatement("UPDATE parada SET minutos = :m WHERE id = :id", ["m" => $base, "id" => $p["id"]]);
            foreach ($porClase as $clase => $m) {
                if ($m !== $base) {
                    $this->db->insert("parada_tiempo", ["parada_id" => $p["id"], "bus_clase_id" => $clase, "minutos" => $m]);
                }
            }
        }
    }

    /**
     * `ruta.nombre` del legado (`SANTA ELENA-GUATE,ZONA1` → `Santa Elena-Guate,Zona1`).
     * `descripcion` solo si `nombre` está vacío: es texto libre y puede llevar
     * reglas de negocio ("solo aplica a Mayas de Oro"), no el nombre de la ruta.
     *
     * @param array<string, mixed> $ruta
     */
    public static function nombre(array $ruta): string
    {
        $nombre = trim((string) ($ruta["nombre"] ?? "")) ?: trim((string) ($ruta["descripcion"] ?? ""));

        return mb_substr($nombre !== "" ? self::sinMayusculas($nombre) : (string) $ruta["codigo"], 0, 255);
    }

    /**
     * Las palabras escritas en mayúsculas (`GUATEMALA`, `SAN-JOSE`) quedan con
     * mayúscula inicial (`Guatemala`, `San-Jose`). Solo cuentan las de dos
     * letras o más: una letra suelta (`A`) queda igual. Puro.
     */
    public static function sinMayusculas(string $texto): string
    {
        return preg_replace_callback(
            '/(?<!\p{L})\p{Lu}{2,}(?!\p{L})/u',
            static fn (array $m): string => mb_strtoupper(mb_substr($m[0], 0, 1)) . mb_strtolower(mb_substr($m[0], 1)),
            $texto,
        ) ?? $texto;
    }
}
