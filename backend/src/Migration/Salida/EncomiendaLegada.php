<?php

declare(strict_types=1);

namespace App\Migration\Salida;

use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Enum\TipoArticuloEncomienda;

/**
 * Lectura pura de una encomienda del legado (fila de `encomienda` + sus
 * eventos de `encomienda_bitacora`) en términos del modelo nuevo (ADR-035).
 * Sin base de datos: los ids que devuelve son del legado y los resuelve
 * `EncomiendasLegado`.
 *
 * El legado no guarda el estado en la encomienda: es el del último evento de
 * su bitácora (`ultima_bitacora_id`). Una encomienda es un solo artículo
 * (efectivo, especial o paquete) con `cantidad` bultos.
 */
final class EncomiendaLegada
{
    /** `encomienda_estado` del legado; la «cancelada» (7) no existe aquí: es una anulación. */
    private const ESTADOS = [
        1 => EstadoEncomienda::RECIBIDA,
        2 => EstadoEncomienda::EMBARCADA,
        3 => EstadoEncomienda::TRANSITO,
        4 => EstadoEncomienda::DESEMBARCADA,
        5 => EstadoEncomienda::ENTREGADA,
        6 => EstadoEncomienda::ANULADA,
        7 => EstadoEncomienda::ANULADA,
    ];

    /** `encomienda_tipo`: 1 efectivo, 2 especial, 3 paquete. */
    private const TIPO_EFECTIVO = 1;
    private const TIPO_ESPECIAL = 2;

    /** `encomienda_documento_tipo`: 1 factura, 2 por cobrar, 3 cortesía, 4 interna. */
    private const DOCUMENTO_POR_COBRAR = 2;
    private const DOCUMENTO_CORTESIA = 3;
    private const DOCUMENTO_INTERNA = 4;

    private const SMALLINT = 32767;

    public static function estadoDe(mixed $estadoId): ?EstadoEncomienda
    {
        return self::ESTADOS[(int) $estadoId] ?? null;
    }

    /**
     * Eventos en el orden en que ocurrieron. El legado escribe a veces dos en
     * el mismo segundo (desembarcar y entregar): el empate lo decide el ciclo
     * de vida, no el id.
     *
     * @param list<array<string, mixed>> $eventos filas de `encomienda_bitacora`
     *
     * @return list<array<string, mixed>>
     */
    public static function ordenar(array $eventos): array
    {
        $rango = static fn(array $e) => (int) $e["estado_id"] >= 6 ? 9 : (int) $e["estado_id"];
        usort($eventos, static fn(array $a, array $b) => [(string) $a["fecha"], $rango($a), (int) $a["id"]] <=> [(string) $b["fecha"], $rango($b), (int) $b["id"]]);

        return $eventos;
    }

    /**
     * Estado actual: el de `ultima_bitacora_id` si está entre los eventos; si
     * no, el del último. Sin eventos, recibida.
     *
     * @param list<array<string, mixed>> $eventos ordenados
     */
    public static function estado(array $old, array $eventos): EstadoEncomienda
    {
        foreach ($eventos as $e) {
            if ((string) $e["id"] === (string) ($old["ultima_bitacora_id"] ?? "")) {
                return self::estadoDe($e["estado_id"]) ?? EstadoEncomienda::RECIBIDA;
            }
        }
        for ($i = count($eventos) - 1; $i >= 0; $i--) {
            if (($estado = self::estadoDe($eventos[$i]["estado_id"])) !== null) {
                return $estado;
            }
        }

        return EstadoEncomienda::RECIBIDA;
    }

    /**
     * Con boleto solo si el boleto ya está migrado y la encomienda no tiene
     * factura propia (va en la de la venta del boleto). Cortesía e interna
     * del legado son la interna de aquí: no se cobran.
     */
    public static function modalidad(array $old, bool $boletoMigrado): ModalidadEncomienda
    {
        $fg = $old["factura_generada_id"] ?? null;
        if ($boletoMigrado && (empty($fg) || (string) $fg === (string) ($old["boleto_fg"] ?? ""))) {
            return ModalidadEncomienda::CON_BOLETO;
        }

        return match ((int) ($old["tipo_documento_id"] ?? 0)) {
            self::DOCUMENTO_POR_COBRAR => ModalidadEncomienda::POR_COBRAR,
            self::DOCUMENTO_CORTESIA, self::DOCUMENTO_INTERNA => ModalidadEncomienda::INTERNA,
            default => ModalidadEncomienda::PAGADA,
        };
    }

    /**
     * Estación (id del legado) en cuya bodega está: donde se recibió o donde
     * se desembarcó por última vez. Null si va a bordo, se entregó o se anuló.
     *
     * @param list<array<string, mixed>> $eventos ordenados
     */
    public static function ubicacion(EstadoEncomienda $estado, array $old, array $eventos): ?int
    {
        if (!$estado->enBodega()) {
            return null;
        }
        $ultimo = self::ultimo($eventos, $estado === EstadoEncomienda::RECIBIDA ? 1 : 4);
        $id = $ultimo["estacion"] ?? null;
        if (empty($id) && $estado === EstadoEncomienda::RECIBIDA) {
            $id = ($old["estacion_creacion_id"] ?? null) ?: ($old["estacion_origen_id"] ?? null);
        }

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Salidas del legado en las que viajó, de la primera a la última.
     *
     * @param list<array<string, mixed>> $eventos ordenados
     *
     * @return list<int>
     */
    public static function salidas(array $old, array $eventos): array
    {
        $ids = [];
        if (is_numeric($old["primera_salida_id"] ?? null)) {
            $ids[(int) $old["primera_salida_id"]] = true;
        }
        foreach ($eventos as $e) {
            if (is_numeric($e["salida_id"] ?? null)) {
                unset($ids[(int) $e["salida_id"]]);
                $ids[(int) $e["salida_id"]] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Último evento con ese estado del legado (5 = entregada: fecha y quién la retiró).
     *
     * @param list<array<string, mixed>> $eventos ordenados
     *
     * @return array<string, mixed>|null
     */
    public static function ultimo(array $eventos, int $estadoId): ?array
    {
        for ($i = count($eventos) - 1; $i >= 0; $i--) {
            if ((int) $eventos[$i]["estado_id"] === $estadoId || ($estadoId === 6 && (int) $eventos[$i]["estado_id"] === 7)) {
                return $eventos[$i];
            }
        }

        return null;
    }

    /** Lo que costó en quetzales (centavos): el precio en moneda base o, si falta, el de su moneda. */
    public static function precio(array $old): int
    {
        $valor = $old["precioCalculadoMonedaBase"] ?? $old["precio_calculado_moneda_base"] ?? null;
        $valor ??= $old["precioCalculado"] ?? $old["precio_calculado"] ?? 0;

        return max(0, (int) round(((float) $valor) * 100));
    }

    /**
     * El artículo único de la encomienda. El legado no separa el recargo por
     * distancia ni el descuento: el importe es el precio y la tarifa, lo que
     * toca a cada bulto. El efectivo (envío de dinero, ya en desuso) va como
     * paquete con su descripción.
     *
     * @return array{tipo: TipoArticuloEncomienda, especial: ?int, descripcion: ?string, cantidad: int, peso: ?float, alto: ?int, ancho: ?int, largo: ?int, tarifa: int, importe: int}
     */
    public static function articulo(array $old): array
    {
        $tipo = (int) ($old["tipo_encomienda_id"] ?? 0);
        $especial = $tipo === self::TIPO_ESPECIAL && is_numeric($old["tipo_encomienda_especial_id"] ?? null)
            ? (int) $old["tipo_encomienda_especial_id"]
            : null;
        $cantidad = max(1, min(self::SMALLINT, (int) ($old["cantidad"] ?? 1)));
        $descripcion = trim(preg_replace('/\s+/', " ", (string) ($old["descripcion"] ?? "")));
        if ($tipo === self::TIPO_EFECTIVO) {
            $descripcion = trim("Efectivo " . $descripcion);
        }
        $importe = self::precio($old);
        $medida = static fn(string $k) => is_numeric($old[$k] ?? null) && (int) $old[$k] > 0 ? min(self::SMALLINT, (int) $old[$k]) : null;
        $paquete = $especial === null;

        return [
            "tipo" => $paquete ? TipoArticuloEncomienda::PAQUETE : TipoArticuloEncomienda::ESPECIAL,
            "especial" => $especial,
            "descripcion" => $descripcion === "" ? null : mb_substr($descripcion, 0, 255),
            "cantidad" => $cantidad,
            "peso" => $paquete && is_numeric($old["peso"] ?? null) && (float) $old["peso"] > 0 ? (float) $old["peso"] : null,
            "alto" => $paquete ? $medida("alto") : null,
            "ancho" => $paquete ? $medida("ancho") : null,
            "largo" => $paquete ? $medida("profundidad") : null,
            "tarifa" => intdiv($importe, $cantidad),
            "importe" => $importe,
        ];
    }

    /** Texto libre del legado, en una línea y al largo de la columna. */
    public static function texto(mixed $valor, int $largo = 255): ?string
    {
        $t = trim(preg_replace('/\s+/', " ", (string) $valor));

        return $t === "" ? null : mb_substr($t, 0, $largo);
    }
}
