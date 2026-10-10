<?php

declare(strict_types=1);

namespace App\Venta\Facturacion\Forcon;

use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\DteCertificado;
use App\Venta\Facturacion\SolicitudAnulacion;
use App\Venta\Facturacion\SolicitudDte;
use Money\Money;

/**
 * Traducción pura entre el DTE del sistema y el JSON de Forcon ("Esquema
 * Oficial SAT", `Ejemplos JSON Esquema SAT/5. Factura (local).json`).
 *
 * - Factura (`FACT`) con un ítem de servicio por boleto; el precio incluye
 *   IVA (12 %), que se desglosa en `MontoGravable`/`MontoImpuesto`.
 * - Adenda: encabezado 59 = CÓDIGO INTERNO (la referencia de la venta: un
 *   reintento con el mismo código lo rechaza el certificador con ESW-025 en
 *   vez de emitir otra factura) y 137 = RUTA; por ítem 129 = NO. ASIENTO y
 *   132 = NOMBRE PASAJERO (`Codigos_de_Adendas.xls`).
 */
final class DteJsonForcon
{
    public const IVA = 0.12;

    private const ADENDA_CODIGO_INTERNO = "59";
    private const ADENDA_RUTA = "137";
    private const ADENDA_ASIENTO = "129";
    private const ADENDA_PASAJERO = "132";

    /** Datos del certificador que se imprimen en el boleto. */
    public const CERTIFICADOR_NIT = "4150686";
    public const CERTIFICADOR_NOMBRE = "Formularios Continuos de Centro America, S.A.";

    /**
     * @param array{nombreComercial: string, correo: string, direccion: string, codigoPostal: string, municipio: string, departamento: string, pais: string, razonSocial: ?string} $emisor
     *
     * @return array<string, mixed>
     */
    public static function construir(SolicitudDte $s, array $emisor): array
    {
        $generales = [
            "CodigoMoneda" => $s->total->getCurrency()->getCode(),
            "FechaHoraEmision" => $s->fechaEmision->format("Y-m-d\\TH:i:s.vP"),
            "Tipo" => "FACT",
        ];
        if ($s->numeroAcceso !== null) {
            $generales["NumeroAcceso"] = (string) $s->numeroAcceso;
        }

        $items = [];
        $detalleAdenda = [];
        $totalIva = 0.0;
        foreach (array_values($s->items) as $i => $item) {
            $linea = (string) ($i + 1);
            $total = self::decimal($item->total);
            $gravable = round($total / (1 + self::IVA), 6);
            $iva = round($total - $gravable, 6);
            $totalIva += $iva;
            $items[] = [
                "BienOServicio" => "S",
                "NumeroLinea" => $linea,
                "Cantidad" => number_format($item->cantidad, 10, ".", ""),
                "UnidadMedida" => "UNI",
                "Descripcion" => mb_substr($item->descripcion, 0, 500),
                "PrecioUnitario" => number_format(self::decimal($item->precioUnitario), 10, ".", ""),
                "Precio" => number_format($total, 10, ".", ""),
                "Descuento" => "0.0000000000",
                "Impuestos" => ["Impuesto" => [[
                    "NombreCorto" => "IVA",
                    "CodigoUnidadGravable" => "1",
                    "MontoGravable" => number_format($gravable, 6, ".", ""),
                    "MontoImpuesto" => number_format($iva, 6, ".", ""),
                ]]],
                "Total" => number_format($total, 2, ".", ""),
            ];
            if ($item->asiento !== null) {
                $detalleAdenda[] = ["NumeroItem" => $linea, "CodigoEtiqueta" => self::ADENDA_ASIENTO, "ValorEtiqueta" => (string) $item->asiento];
            }
            if ($item->pasajero) {
                $detalleAdenda[] = ["NumeroItem" => $linea, "CodigoEtiqueta" => self::ADENDA_PASAJERO, "ValorEtiqueta" => mb_substr($item->pasajero, 0, 100)];
            }
        }

        $encabezadoAdenda = [["CodigoEtiqueta" => self::ADENDA_CODIGO_INTERNO, "ValorEtiqueta" => $s->referenciaInterna]];
        if ($s->ruta) {
            $encabezadoAdenda[] = ["CodigoEtiqueta" => self::ADENDA_RUTA, "ValorEtiqueta" => mb_substr($s->ruta, 0, 100)];
        }
        $adenda = ["Encabezado" => ["DefinicionEncabezado" => $encabezadoAdenda]];
        if ($detalleAdenda !== []) {
            $adenda["Detalle"] = ["DefinicionDetalle" => $detalleAdenda];
        }

        return [
            "DatosEmision" => [
                "DatosGeneralesEmision" => $generales,
                "Emisor" => [
                    "AfiliacionIVA" => $s->afiliacionIva,
                    "CodigoEstablecimiento" => (string) $s->establecimientoCodigo,
                    "CorreoEmisor" => $emisor["correo"],
                    "NITEmisor" => $s->emisorNit,
                    "NombreComercial" => $emisor["nombreComercial"],
                    "NombreEmisor" => $emisor["razonSocial"] ?? $s->emisorNombre,
                    "DireccionEmisor" => [
                        "Direccion" => $emisor["direccion"],
                        "CodigoPostal" => $emisor["codigoPostal"],
                        "Municipio" => $emisor["municipio"],
                        "Departamento" => $emisor["departamento"],
                        "Pais" => $emisor["pais"],
                    ],
                ],
                "Receptor" => [
                    "CorreoReceptor" => $s->receptorCorreo ?? "",
                    "IDReceptor" => $s->receptorNit,
                    "NombreReceptor" => mb_substr($s->receptorNit === "CF" ? "CONSUMIDOR FINAL" : $s->receptorNombre, 0, 255),
                ],
                "Frases" => ["Frase" => array_map(
                    static fn(array $f) => ["CodigoEscenario" => (string) $f[1], "TipoFrase" => (string) $f[0]],
                    $s->frases,
                )],
                "Items" => ["Item" => $items],
                "Totales" => [
                    "TotalImpuestos" => ["TotalImpuesto" => [[
                        "NombreCorto" => "IVA",
                        "TotalMontoImpuesto" => number_format($totalIva, 6, ".", ""),
                    ]]],
                    "GranTotal" => number_format(self::decimal($s->total), 2, ".", ""),
                ],
                "Adenda" => $adenda,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $r respuesta de `EmitirDteJson`
     *
     * @throws CertificacionFallida si no se certificó
     */
    public static function respuesta(array $r): DteCertificado
    {
        if (($r["Resultado"] ?? false) !== true) {
            throw self::fallo((string) ($r["Descripcion"] ?? ""));
        }

        $fecha = (string) ($r["FechaCertificacionDTE"] ?? $r["FechaCertifcacionDTE"] ?? "");

        return new DteCertificado(
            uuid: strtoupper((string) $r["AutorizacionUUID"]),
            serie: (string) $r["SerieDTE"],
            numero: (int) $r["NumeroDTE"],
            fechaCertificacion: $fecha !== "" ? new \DateTimeImmutable($fecha) : new \DateTimeImmutable(),
            certificadorNit: self::CERTIFICADOR_NIT,
            certificadorNombre: self::CERTIFICADOR_NOMBRE,
            xml: isset($r["XMLCertificado"]) ? (string) $r["XMLCertificado"] : null,
            urlPdf: isset($r["RutaPDF"]) ? (string) $r["RutaPDF"] : null,
        );
    }

    /**
     * Anulación (`Ejemplos JSON Esquema SAT/- Anulacion.json`): se identifica el
     * DTE por su UUID de autorización.
     *
     * @return array<string, mixed>
     */
    public static function construirAnulacion(SolicitudAnulacion $s): array
    {
        $formato = "Y-m-d\\TH:i:s.vP";

        return [
            "DatosGeneralesAnulacion" => [
                "FechaEmisionDocumentoAnular" => $s->fechaEmision->format($formato),
                "FechaHoraAnulacion" => $s->fechaAnulacion->format($formato),
                "IDReceptor" => $s->receptorNit,
                "MotivoAnulacion" => mb_substr($s->motivo, 0, 255),
                "NITEmisor" => $s->emisorNit,
                "NumeroDocumentoAAnular" => strtoupper($s->uuid),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $r respuesta de `AnularDteJson`
     *
     * @throws CertificacionFallida si no se anuló
     */
    public static function respuestaAnulacion(array $r): void
    {
        if (($r["Resultado"] ?? false) === true) {
            return;
        }
        $descripcion = (string) ($r["Descripcion"] ?? "");
        $texto = trim(preg_replace('/^\s*ERROR\s+[A-Z]{3}-\d{3}\s*:\s*/', "", $descripcion) ?? $descripcion);
        $codigo = preg_match('/\b([A-Z]{3}-\d{3})\b/', $descripcion, $m) ? $m[1] : null;

        throw new CertificacionFallida(
            $texto !== "" ? "El certificador no anuló la factura: {$texto}" : "El certificador no anuló la factura.",
            // Sin código de error es una falla del servicio: vale reintentar.
            $codigo === null || in_array($codigo, ["EAL-020", "ESW-043"], true),
            $codigo,
        );
    }

    /**
     * Rechazo del certificador → mensaje para el usuario. Con código de error
     * (`EVI-049`, `ESW-025`, …) es un problema del documento: reintentar igual
     * no sirve. Sin código, se trata como falla del servicio.
     */
    public static function fallo(string $descripcion): CertificacionFallida
    {
        $texto = trim(preg_replace('/^\s*ERROR\s+[A-Z]{3}-\d{3}\s*:\s*/', "", $descripcion) ?? $descripcion);
        if (!preg_match('/\b([A-Z]{3}-\d{3})\b/', $descripcion, $m)) {
            return new CertificacionFallida(
                $texto !== "" ? "El certificador no emitió la factura: {$texto}" : "El certificador no emitió la factura.",
                true,
            );
        }

        $mensaje = match ($m[1]) {
            "ESW-025" => "Esta venta ya tiene una factura certificada (código interno repetido). Consulte al administrador antes de reintentar.",
            "EVI-049", "EVI-186" => "El NIT del cliente no es válido o no existe en la SAT. Corrija el NIT o use CF.",
            "EVI-023", "EVI-221" => "Para montos de Q2,500.00 o más la SAT exige el NIT del cliente (no se puede facturar a CF).",
            "EVI-031" => "El correo electrónico del cliente no es válido para la factura. Corríjalo o quítelo.",
            "ESW-073", "ESW-001" => "El certificador rechazó las credenciales de acceso.",
            default => "La SAT/certificador rechazó la factura: {$texto} ({$m[1]})",
        };

        // Errores internos del certificador (EAL-020, ESW-043): vale reintentar.
        return new CertificacionFallida($mensaje, in_array($m[1], ["EAL-020", "ESW-043"], true), $m[1]);
    }

    private static function decimal(Money $monto): float
    {
        return ((int) $monto->getAmount()) / 100;
    }
}
