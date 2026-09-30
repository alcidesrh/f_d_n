<?php

declare(strict_types=1);

namespace App\Venta\Pago\Cybersource;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * API REST de Cybersource con autenticación HTTP Signature (HMAC-SHA256
 * sobre host, fecha, método + ruta, digest del cuerpo y merchant id).
 * `CYBERSOURCE_URL`: https://apitest.cybersource.com (pruebas) o
 * https://api.cybersource.com (producción).
 */
final class ClienteCybersource
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly ClockInterface $reloj,
        #[Autowire(env: "default:pago_cybersource_url_defecto:CYBERSOURCE_URL")]
        private readonly string $url,
    ) {}

    /**
     * @param array<string, mixed> $cuerpo
     *
     * @return array{0: int, 1: array<string, mixed>} código HTTP y respuesta
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface sin respuesta
     */
    public function post(Comercio $comercio, string $ruta, array $cuerpo): array
    {
        $json = json_encode($cuerpo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $host = (string) parse_url($this->url, PHP_URL_HOST);
        $fecha = $this->reloj->now()->setTimezone(new \DateTimeZone("UTC"))->format("D, d M Y H:i:s") . " GMT";

        $respuesta = $this->http->request("POST", rtrim($this->url, "/") . $ruta, [
            "headers" => [
                "Accept" => "application/hal+json;charset=utf-8",
                "Content-Type" => "application/json;charset=utf-8",
                ...self::firmar($comercio, $host, "post", $ruta, $fecha, $json),
            ],
            "body" => $json,
            "timeout" => 30,
            "max_duration" => 45,
        ]);
        $codigo = $respuesta->getStatusCode();
        $texto = $respuesta->getContent(false);
        $datos = json_decode($texto, true);

        return [$codigo, is_array($datos) ? $datos : []];
    }

    /**
     * Encabezados de autenticación (HTTP Signature de Cybersource).
     *
     * @return array<string, string>
     */
    public static function firmar(Comercio $comercio, string $host, string $metodo, string $ruta, string $fecha, ?string $cuerpo): array
    {
        $encabezados = ["v-c-merchant-id" => $comercio->id, "Date" => $fecha, "Host" => $host];
        $lineas = ["host: {$host}", "date: {$fecha}", "request-target: " . strtolower($metodo) . " {$ruta}"];
        $nombres = "host date request-target";
        if ($cuerpo !== null) {
            $digest = "SHA-256=" . base64_encode(hash("sha256", $cuerpo, true));
            $encabezados["Digest"] = $digest;
            $lineas[] = "digest: {$digest}";
            $nombres .= " digest";
        }
        $lineas[] = "v-c-merchant-id: {$comercio->id}";
        $nombres .= " v-c-merchant-id";

        $firma = base64_encode(hash_hmac("sha256", implode("\n", $lineas), (string) base64_decode($comercio->secreto, true), true));
        $encabezados["Signature"] = sprintf(
            'keyid="%s", algorithm="HmacSHA256", headers="%s", signature="%s"',
            $comercio->llave,
            $nombres,
            $firma,
        );

        return $encabezados;
    }
}
