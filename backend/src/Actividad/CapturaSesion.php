<?php

declare(strict_types=1);

namespace App\Actividad;

use App\Entity\Usuario;
use App\Repository\ApiTokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Bitácora de actividad de la sesión: inicio (`POST /api/login`), intento
 * fallido y cierre (`POST /api/logout`). Va al búfer de `RegistroActividad`
 * y se escribe al terminar la petición, con el resto.
 *
 * El firewall no tiene sesión: cada petición con Bearer también "inicia
 * sesión" para Symfony; solo cuenta el login por usuario y contraseña. Un
 * intento fallido se anota siempre que la familia esté encendida (es de
 * seguridad), con el usuario que se escribió.
 */
final class CapturaSesion
{
    public const RUTA_LOGIN = "/api/login";

    public function __construct(
        private readonly RegistroActividad $registro,
        private readonly AjustesBitacora $ajustes,
        private readonly CapturaPeticion $captura,
        private readonly ApiTokenRepository $tokens,
        private readonly LoggerInterface $logger,
    ) {}

    #[AsEventListener]
    public function alEntrar(LoginSuccessEvent $event): void
    {
        $usuario = $event->getUser();
        if ($event->getRequest()->getPathInfo() !== self::RUTA_LOGIN || !$usuario instanceof Usuario) {
            return;
        }
        $this->anotar($usuario, "inicio", $event->getRequest());
    }

    #[AsEventListener]
    public function alFallar(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->getPathInfo() !== self::RUTA_LOGIN || !$this->ajustes->politica()->registra(TipoActividad::SESION)) {
            return;
        }
        try {
            $datos = json_decode($request->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $datos = [];
        }
        $this->registro->anotar(TipoActividad::SESION, [
            "accion" => "fallo",
            "metodo" => "POST",
            "ruta" => self::RUTA_LOGIN,
            "estado" => 401,
            "ip" => $request->getClientIp(),
            "agente" => mb_substr((string) $request->headers->get("User-Agent", ""), 0, 255) ?: null,
            "detalle" => Detalle::json([
                "usuario" => is_array($datos) ? mb_substr((string) ($datos["username"] ?? ""), 0, 100) : null,
                "motivo" => mb_substr($event->getException()->getMessageKey(), 0, 120),
            ], 1024),
        ]);
    }

    #[AsEventListener(priority: 16)]
    public function alSalir(LogoutEvent $event): void
    {
        $usuario = $event->getToken()?->getUser();
        if (!$usuario instanceof Usuario) {
            $bearer = (string) $event->getRequest()->headers->get("Authorization", "");
            $usuario = str_starts_with($bearer, "Bearer ") ? $this->tokens->findOneBy(["token" => substr($bearer, 7)])?->getUsuario() : null;
        }
        if ($usuario instanceof Usuario) {
            $this->anotar($usuario, "cierre", $event->getRequest());
        }
    }

    private function anotar(Usuario $usuario, string $accion, Request $request): void
    {
        try {
            $politica = $this->ajustes->politica();
            if (!$politica->registra(TipoActividad::SESION)) {
                return;
            }
            $contexto = $this->captura->contexto($usuario, $politica);
            if ($contexto === null) {
                return;
            }
            $this->registro->anotar(TipoActividad::SESION, [
                "usuario_id" => (int) $usuario->getId(),
                "empresa_id" => $contexto["empresa"],
                "estacion_id" => $contexto["estacion"],
                "accion" => $accion,
                "metodo" => "POST",
                "ruta" => $request->getPathInfo(),
                "ip" => $request->getClientIp(),
                "agente" => mb_substr((string) $request->headers->get("User-Agent", ""), 0, 255) ?: null,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("No se pudo anotar la sesión en la bitácora: {error}", ["error" => $e->getMessage()]);
        }
    }
}
