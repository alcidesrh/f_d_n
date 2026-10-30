<?php

declare(strict_types=1);

namespace App\Chat\Push;

use App\Chat\ChatRechazado;
use App\Entity\Enum\TipoCanalChat;
use App\Entity\SuscripcionPush;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Notificaciones del chat con la aplicación cerrada (Web Push): quién envía
 * y un fragmento del mensaje. Cada navegador se suscribe desde "Mi cuenta";
 * el envío es asíncrono (`EnviarAvisoPush`). Sin claves VAPID configuradas
 * (`PUSH_VAPID_*`, `app:push:claves`) no hay notificaciones y la app no
 * ofrece activarlas.
 */
final class AvisosPush
{
    public const MAX_FRAGMENTO = 100;
    private const SERVICIOS = ["fcm.googleapis.com", "push.services.mozilla.com", "notify.windows.com", "push.apple.com"];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        #[Autowire(env: "default::PUSH_VAPID_PUBLICA")] private readonly ?string $clavePublica,
        #[Autowire(env: "default::PUSH_VAPID_PRIVADA")] private readonly ?string $clavePrivada,
    ) {}

    public function activo(): bool
    {
        return $this->clavePublica !== null && $this->clavePublica !== "" && $this->clavePrivada !== null && $this->clavePrivada !== "";
    }

    /**
     * Estado para el navegador que pregunta: la clave con la que suscribirse
     * (null = no disponible) y si su suscripción (`$endpoint`) es de `$yo`.
     *
     * @return array{clave: ?string, mia: bool}
     */
    public function estado(Usuario $yo, ?string $endpoint): array
    {
        $s = $endpoint ? $this->buscar($endpoint) : null;

        return ["clave" => $this->activo() ? $this->clavePublica : null, "mia" => $s !== null && $s->getUsuario()->getId() === $yo->getId()];
    }

    /** @param array<string, mixed> $datos `PushSubscription.toJSON()` del navegador */
    public function suscribir(Usuario $yo, array $datos): void
    {
        if (!$this->activo()) {
            throw new ChatRechazado("Las notificaciones no están configuradas.", 503);
        }
        $endpoint = $datos["endpoint"] ?? null;
        $clave = $datos["keys"]["p256dh"] ?? null;
        $secreto = $datos["keys"]["auth"] ?? null;
        if (!is_string($endpoint) || !self::endpointValido($endpoint) || !self::base64url($clave, 200) || !self::base64url($secreto, 100)) {
            throw new ChatRechazado("Suscripción inválida.");
        }
        $s = $this->buscar($endpoint);
        if ($s === null) {
            $this->em->persist(new SuscripcionPush($yo, $endpoint, $clave, $secreto));
        } else {
            $s->renovar($yo, $clave, $secreto);
        }
        $this->em->flush();
    }

    /**
     * Quita la suscripción de ese navegador, sea de quien sea: solo el
     * navegador conoce su `endpoint` (lo hace al cerrar sesión o al
     * desactivarlas, y al entrar otro usuario en el mismo).
     */
    public function desuscribir(string $endpoint): void
    {
        $s = $this->buscar($endpoint);
        if ($s !== null) {
            $this->em->remove($s);
            $this->em->flush();
        }
    }

    /**
     * Nuevo mensaje para `$usuarios` (sin el autor).
     *
     * @param list<int> $usuarios
     */
    public function avisarMensaje(array $usuarios, int $canal, TipoCanalChat $tipo, ?string $grupo, ?string $autor, string $texto): void
    {
        if ($usuarios === [] || !$this->activo()) {
            return;
        }
        $this->bus->dispatch(new EnviarAvisoPush(array_values(array_unique($usuarios)), self::contenido($canal, $tipo, $grupo, $autor, $texto)));
    }

    /**
     * Lo que muestra la notificación: en un directo, quien escribe y el
     * fragmento; en un grupo, el grupo y "Autor: fragmento". `etiqueta`
     * agrupa por conversación (la nueva reemplaza a la anterior).
     *
     * @return array{titulo: string, cuerpo: string, url: string, etiqueta: string}
     */
    public static function contenido(int $canal, TipoCanalChat $tipo, ?string $grupo, ?string $autor, string $texto): array
    {
        $fragmento = self::fragmento($texto);
        [$titulo, $cuerpo] = match (true) {
            $tipo === TipoCanalChat::Sistema || $autor === null => ["Bitácora del sistema", $fragmento],
            $tipo === TipoCanalChat::Grupo => [$grupo ?: "Grupo", sprintf("%s: %s", $autor, $fragmento)],
            default => [$autor, $fragmento],
        };

        return ["titulo" => $titulo, "cuerpo" => $cuerpo, "url" => sprintf("/chat/%d", $canal), "etiqueta" => sprintf("chat-%d", $canal)];
    }

    public static function fragmento(string $texto): string
    {
        $texto = preg_replace('/\s+/u', " ", trim($texto)) ?? "";

        return mb_strlen($texto) > self::MAX_FRAGMENTO ? rtrim(mb_substr($texto, 0, self::MAX_FRAGMENTO - 1)) . "…" : $texto;
    }

    /**
     * Solo los servicios de push de los navegadores (Chrome/Edge/Android,
     * Firefox, Windows, Safari): el servidor hace un POST a esa URL y no debe
     * poder apuntarse a otra parte.
     */
    public static function endpointValido(string $endpoint): bool
    {
        $partes = parse_url($endpoint);
        if (strlen($endpoint) > 1000 || !is_array($partes) || ($partes["scheme"] ?? null) !== "https" || isset($partes["user"]) || isset($partes["port"])) {
            return false;
        }
        $host = strtolower($partes["host"] ?? "");
        foreach (self::SERVICIOS as $servicio) {
            if ($host === $servicio || str_ends_with($host, "." . $servicio)) {
                return true;
            }
        }

        return false;
    }

    private static function base64url(mixed $valor, int $max): bool
    {
        return is_string($valor) && strlen($valor) <= $max && preg_match('/^[A-Za-z0-9_-]+=*$/', $valor) === 1;
    }

    private function buscar(string $endpoint): ?SuscripcionPush
    {
        return $this->em->getRepository(SuscripcionPush::class)->findOneBy(["endpoint" => $endpoint]);
    }
}
