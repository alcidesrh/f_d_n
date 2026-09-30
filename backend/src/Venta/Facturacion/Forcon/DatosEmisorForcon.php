<?php

declare(strict_types=1);

namespace App\Venta\Facturacion\Forcon;

use App\Venta\Facturacion\CertificacionFallida;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Datos del emisor por establecimiento (nombre comercial, correo, dirección
 * desglosada) tal como los tiene Forcon/SAT (servicio "Datos Emisor"). Así
 * la dirección de cada factura es la registrada, sin duplicarla en la base.
 * Se guardan en caché 12 horas.
 */
final class DatosEmisorForcon
{
    public function __construct(
        private readonly ClienteForcon $cliente,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * @return array{nombreComercial: string, correo: string, direccion: string, codigoPostal: string, municipio: string, departamento: string, pais: string, razonSocial: ?string}
     *
     * @throws CertificacionFallida
     */
    public function de(string $nit, int $establecimiento): array
    {
        return $this->cache->get(
            sprintf("forcon_emisor_%s_%d", preg_replace('/\W/', "", $nit), $establecimiento),
            function (ItemInterface $item) use ($nit, $establecimiento) {
                $item->expiresAfter(43200);
                $r = $this->cliente->get(ClienteForcon::DATOS_EMISOR, ["NIT" => $nit, "Establecimiento" => $establecimiento], $nit);
                if (($r["Resultado"] ?? false) !== true) {
                    throw new CertificacionFallida(
                        sprintf(
                            "El certificador no tiene datos del establecimiento %d del NIT %s: %s",
                            $establecimiento,
                            $nit,
                            (string) ($r["Descripcion"] ?? "sin detalle"),
                        ),
                        false,
                        "establecimiento",
                    );
                }

                return self::desdeRespuesta($r);
            },
        );
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return array{nombreComercial: string, correo: string, direccion: string, codigoPostal: string, municipio: string, departamento: string, pais: string, razonSocial: ?string}
     */
    public static function desdeRespuesta(array $r): array
    {
        $texto = static fn(string $k) => trim((string) ($r[$k] ?? ""));
        $direccion = $texto("DireccionCompletaManual") ?: $texto("DireccionCompletaAutomatica");

        return [
            "nombreComercial" => $texto("NombreComercial") ?: $texto("RazonSocial"),
            "correo" => $texto("CorreoElectronico"),
            "direccion" => $direccion !== "" ? $direccion : "CIUDAD",
            "codigoPostal" => $texto("CodigoPostal") ?: "01000",
            "municipio" => mb_strtoupper($texto("Municipio")),
            "departamento" => mb_strtoupper($texto("Departamento")),
            "pais" => $texto("Pais") ?: "GT",
            "razonSocial" => $texto("RazonSocial") ?: null,
        ];
    }
}
