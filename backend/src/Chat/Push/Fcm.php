<?php

declare(strict_types=1);

namespace App\Chat\Push;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Firebase Cloud Messaging (API HTTP v1): las notificaciones del chat en la
 * app móvil (ADR-060). `PUSH_FCM_CREDENCIAL` es la ruta del JSON de la cuenta
 * de servicio de Firebase (secreto, fuera de git); vacía = sin notificaciones
 * en la app. El token de acceso de Google se pide con un JWT firmado con la
 * clave de esa cuenta y se guarda mientras vale.
 */
final class Fcm
{
    private const ALCANCE = "https://www.googleapis.com/auth/firebase.messaging";
    private const URL_TOKEN = "https://oauth2.googleapis.com/token";
    /** Una notificación de chat que no llega en un día ya no sirve. */
    private const TTL = 86400;
    /** Canal de Android que crea la app (`frontend/src/core/chat/pushNativo.ts`). */
    public const CANAL = "chat";

    /** @var array{project_id: string, client_email: string, private_key: string}|false|null */
    private array|false|null $cuenta = null;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire(env: "default::PUSH_FCM_CREDENCIAL")] private readonly ?string $credencial,
    ) {}

    public function activo(): bool
    {
        return $this->cuenta() !== null;
    }

    /** @param array{titulo: string, cuerpo: string, url: string, etiqueta: string} $contenido */
    public function enviar(string $token, array $contenido): EntregaFcm
    {
        $cuenta = $this->cuenta();
        if ($cuenta === null) {
            return EntregaFcm::Fallida;
        }
        try {
            $respuesta = $this->http->request("POST", sprintf("https://fcm.googleapis.com/v1/projects/%s/messages:send", rawurlencode($cuenta["project_id"])), [
                "auth_bearer" => $this->acceso($cuenta),
                "json" => self::mensaje($token, $contenido),
                "timeout" => 10,
            ]);
            $estado = $respuesta->getStatusCode();
            if ($estado === 200) {
                return EntregaFcm::Entregada;
            }
            $error = $respuesta->toArray(false)["error"] ?? [];
            if (self::vencido($estado, is_array($error) ? $error : [])) {
                return EntregaFcm::Vencida;
            }
            // 401: el token de acceso guardado ya no vale (se rotó la clave); el próximo envío pide otro.
            if ($estado === 401) {
                $this->cache->delete($this->claveCache($cuenta));
            }
            $this->logger->warning("Notificación FCM no enviada: {estado} {motivo}", ["estado" => $estado, "motivo" => is_array($error) ? ($error["status"] ?? "") : ""]);
        } catch (\Throwable $e) {
            $this->logger->warning("Notificación FCM no enviada: {motivo}", ["motivo" => $e->getMessage()]);
        }

        return EntregaFcm::Fallida;
    }

    /**
     * El mensaje para un teléfono. Con `notification`, Android la muestra sola
     * si la app está cerrada o en segundo plano; con la app a la vista llega al
     * código y no se muestra (el chat ya avisa dentro). `tag` agrupa por
     * conversación: la nueva reemplaza a la anterior. `url` es lo que abre al tocarla.
     *
     * @param array{titulo: string, cuerpo: string, url: string, etiqueta: string} $contenido
     *
     * @return array<string, mixed>
     */
    public static function mensaje(string $token, array $contenido): array
    {
        return [
            "message" => [
                "token" => $token,
                "notification" => ["title" => $contenido["titulo"], "body" => $contenido["cuerpo"]],
                "data" => ["url" => $contenido["url"], "etiqueta" => $contenido["etiqueta"]],
                "android" => [
                    "priority" => "HIGH",
                    "ttl" => self::TTL . "s",
                    "notification" => ["tag" => $contenido["etiqueta"], "channel_id" => self::CANAL],
                ],
            ],
        ];
    }

    /**
     * Firebase ya no conoce el token: 404 `UNREGISTERED`, o 400 cuando el token mismo es inválido.
     *
     * @param array<string, mixed> $error `error` de la respuesta
     */
    public static function vencido(int $estado, array $error): bool
    {
        $codigos = [];
        foreach (is_array($error["details"] ?? null) ? $error["details"] : [] as $detalle) {
            if (is_array($detalle) && is_string($detalle["errorCode"] ?? null)) {
                $codigos[] = $detalle["errorCode"];
            }
        }
        if ($estado === 404) {
            return $codigos === [] || in_array("UNREGISTERED", $codigos, true);
        }

        return $estado === 400 && in_array("INVALID_ARGUMENT", $codigos, true) && str_contains(strtolower((string) ($error["message"] ?? "")), "registration token");
    }

    /**
     * JWT (RS256) con el que la cuenta de servicio pide su token de acceso.
     *
     * @param array{client_email: string, private_key: string} $cuenta
     */
    public static function jwt(array $cuenta, int $ahora): string
    {
        $b64 = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), "+/", "-_"), "=");
        $firmar = $b64(json_encode(["alg" => "RS256", "typ" => "JWT"], JSON_THROW_ON_ERROR)) . "." . $b64(json_encode([
            "iss" => $cuenta["client_email"],
            "scope" => self::ALCANCE,
            "aud" => self::URL_TOKEN,
            "iat" => $ahora,
            "exp" => $ahora + 3600,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if (!openssl_sign($firmar, $firma, $cuenta["private_key"], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException("La clave de la cuenta de servicio de Firebase no sirve para firmar.");
        }

        return $firmar . "." . $b64($firma);
    }

    /** @param array{project_id: string, client_email: string, private_key: string} $cuenta */
    private function acceso(array $cuenta): string
    {
        return $this->cache->get($this->claveCache($cuenta), function (ItemInterface $item) use ($cuenta): string {
            $datos = $this->http->request("POST", self::URL_TOKEN, [
                "body" => ["grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer", "assertion" => self::jwt($cuenta, time())],
                "timeout" => 10,
            ])->toArray();
            $token = $datos["access_token"] ?? null;
            if (!is_string($token) || $token === "") {
                throw new \RuntimeException("Google no entregó el token de acceso de FCM.");
            }
            // Vale una hora; se renueva con margen.
            $item->expiresAfter(max(60, (int) ($datos["expires_in"] ?? 3600) - 300));

            return $token;
        });
    }

    /** @param array{client_email: string, private_key: string} $cuenta */
    private function claveCache(array $cuenta): string
    {
        return "fcm_acceso_" . hash("xxh128", $cuenta["client_email"] . $cuenta["private_key"]);
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    private function cuenta(): ?array
    {
        if ($this->cuenta === null) {
            $this->cuenta = false;
            if ($this->credencial !== null && $this->credencial !== "") {
                $json = is_file($this->credencial) && is_readable($this->credencial) ? file_get_contents($this->credencial) : false;
                $datos = $json === false ? null : json_decode($json, true);
                if (is_array($datos) && is_string($datos["project_id"] ?? null) && is_string($datos["client_email"] ?? null) && is_string($datos["private_key"] ?? null)) {
                    $this->cuenta = ["project_id" => $datos["project_id"], "client_email" => $datos["client_email"], "private_key" => $datos["private_key"]];
                } else {
                    $this->logger->error("PUSH_FCM_CREDENCIAL no es el JSON de una cuenta de servicio de Firebase legible: sin notificaciones en la app móvil.");
                }
            }
        }

        return $this->cuenta ?: null;
    }
}
