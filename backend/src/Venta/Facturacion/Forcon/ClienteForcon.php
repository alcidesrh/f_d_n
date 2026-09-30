<?php

declare(strict_types=1);

namespace App\Venta\Facturacion\Forcon;

use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\CredencialesFel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transporte de las APIs REST de Forcon (certificador FEL): Basic Auth,
 * JSON y la misma forma de respuesta en todos los servicios:
 * `{ StatusCode, Resultado, Descripcion, ... }`.
 *
 * `FEL_FORCON_URL` (pruebas: `https://pruebasfel.eforcon.com`; la de
 * producción la entrega soporte). Las credenciales son por NIT emisor
 * (`CredencialesFel`) y distintas en pruebas y producción. Ver
 * `docs/certificador_factura_electronica/`.
 */
final class ClienteForcon
{
    public const EMITIR_JSON = "/apiforcon/fel/EmitirDteJson";
    public const CONSULTA_NIT = "/apinitcontribuyente/receptor/Consulta";
    public const DATOS_EMISOR = "/apidatosemisor/establecimiento/Consulta";

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire(env: "default:fel_forcon_url_defecto:FEL_FORCON_URL")]
        private readonly string $url,
        private readonly CredencialesFel $credenciales,
        /** Segundos. La certificación en pruebas llega a tardar ~20 s. */
        private readonly float $timeout = 45.0,
    ) {}

    /**
     * @param array<string, mixed> $cuerpo
     *
     * @return array<string, mixed>
     *
     * @throws CertificacionFallida si no hubo respuesta utilizable (recuperable)
     */
    public function post(string $ruta, array $cuerpo, ?string $nitEmisor): array
    {
        return $this->pedir("POST", $ruta, ["json" => $cuerpo], $nitEmisor);
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    public function get(string $ruta, array $query, ?string $nitEmisor): array
    {
        return $this->pedir("GET", $ruta, ["query" => $query], $nitEmisor);
    }

    /**
     * @param array<string, mixed> $opciones
     *
     * @return array<string, mixed>
     */
    private function pedir(string $metodo, string $ruta, array $opciones, ?string $nitEmisor): array
    {
        [$usuario, $clave] = $this->credenciales->para($nitEmisor);

        try {
            $respuesta = $this->http->request($metodo, rtrim($this->url, "/") . $ruta, [
                ...$opciones,
                "auth_basic" => [$usuario, $clave],
                "headers" => ["Accept" => "application/json", "Cache-Control" => "no-cache"],
                "timeout" => $this->timeout,
                "max_duration" => $this->timeout + 15,
            ]);
            $estado = $respuesta->getStatusCode();
            $datos = $respuesta->toArray(false);
        } catch (ExceptionInterface|\JsonException $e) {
            throw CertificacionFallida::sinRespuesta($e);
        }

        if ($estado === 401 || $estado === 403) {
            throw new CertificacionFallida("El certificador rechazó las credenciales de acceso.", false, "credenciales");
        }
        if ($estado >= 500 || !isset($datos["Resultado"])) {
            throw CertificacionFallida::sinRespuesta();
        }

        return $datos;
    }
}
