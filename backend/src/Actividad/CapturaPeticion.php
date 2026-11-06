<?php

declare(strict_types=1);

namespace App\Actividad;

use App\Acceso\Autorizacion;
use App\Acceso\Suplantacion\SuplantacionActual;
use App\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bitácora de actividad de cada petición a la API: al empezar fija el
 * `request_id`, al responder lo devuelve en `X-Request-Id` y mide, y al
 * terminar (ya enviada la respuesta) anota la petición si la política lo
 * pide y vuelca el búfer en un solo `INSERT` (`RegistroActividad`).
 *
 * Mientras un administrador suplanta al usuario, cada petición (menos los
 * sondeos) se anota siempre, con `suplantado_por_id`, aunque la política no
 * siga a ese usuario o la bitácora esté apagada.
 *
 * Nunca rompe una petición: un error de la bitácora queda en el log.
 */
final class CapturaPeticion
{
    public const CABECERA_ID = "X-Request-Id";
    /** La SPA marca así sus consultas de fondo (polling). */
    public const CABECERA_SONDEO = "X-Actividad";

    /** Rutas que nunca dejan fila de petición: su contenido va por otro lado. */
    private const EXCLUIDAS = ["/api/actividad"];

    private ?float $inicio = null;
    private ?int $estado = null;
    private ?int $duracionMs = null;

    public function __construct(
        private readonly RegistroActividad $registro,
        private readonly AjustesBitacora $ajustes,
        private readonly Security $security,
        private readonly Autorizacion $autorizacion,
        private readonly Connection $conexion,
        private readonly ClockInterface $reloj,
        private readonly LoggerInterface $logger,
        private readonly SuplantacionActual $suplantacion,
    ) {}

    #[AsEventListener(KernelEvents::REQUEST, priority: 512)]
    public function alEmpezar(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->inicio = microtime(true);
        $this->estado = null;
        $this->duracionMs = null;
        $this->registro->usarRequestId($event->getRequest()->headers->get(self::CABECERA_ID));
    }

    #[AsEventListener(KernelEvents::RESPONSE, priority: -512)]
    public function alResponder(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->estado = $event->getResponse()->getStatusCode();
        $this->duracionMs = $this->inicio === null ? null : (int) round((microtime(true) - $this->inicio) * 1000);
        if (self::esApi($event->getRequest())) {
            $event->getResponse()->headers->set(self::CABECERA_ID, $this->registro->requestId());
        }
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    public function alTerminar(TerminateEvent $event): void
    {
        try {
            $this->anotarPeticion($event->getRequest());
            $this->registro->volcar($this->conexion, !RegistroActividad::enWorker());
        } catch (\Throwable $e) {
            $this->registro->reset();
            $this->logger->error("No se pudo escribir la bitácora de actividad: {error}", ["error" => $e->getMessage(), "exception" => $e]);
        } finally {
            $this->inicio = null;
        }
    }

    private function anotarPeticion(Request $request): void
    {
        if (!self::esApi($request) || in_array($request->getPathInfo(), self::EXCLUIDAS, true)) {
            return;
        }
        $usuario = $this->security->getUser();
        if (!$usuario instanceof Usuario) {
            return;
        }
        $politica = $this->ajustes->politica();
        $actor = $this->suplantacion->actor();
        if (!$politica->activa && $actor === null) {
            return;
        }
        $sondeo = $request->headers->get(self::CABECERA_SONDEO) === "sondeo";
        $cuerpo = self::cuerpo($request);
        $graphql = str_starts_with($request->getPathInfo(), "/api/graphql") && $cuerpo !== null ? OperacionGraphql::nombre($cuerpo) : null;
        // Una consulta GraphQL va por POST pero solo lee.
        $metodo = $graphql !== null && str_starts_with($graphql, "query ") ? "GET" : $request->getMethod();
        $tipo = $politica->activa ? $politica->tipoDePeticion($metodo, $sondeo) : null;
        $contexto = $tipo === null ? null : $this->contexto($usuario, $politica);
        if ($actor !== null && !$sondeo && ($tipo === null || $contexto === null)) {
            // Suplantando: se anota aunque la política no lo pida.
            $tipo = TipoActividad::PETICION;
            $contexto = ["empresa" => $this->autorizacion->de($usuario)->general()->empresaUnica(), "estacion" => $usuario->getEstacion()?->getId()];
        }
        if ($tipo === null || $contexto === null) {
            return;
        }

        $ruta = RutaNormalizada::de($request->getPathInfo());
        if ($tipo === TipoActividad::SONDEO) {
            $this->registro->contarSondeo((int) $usuario->getId(), $contexto["empresa"], $contexto["estacion"], $ruta->ruta);

            return;
        }

        $detalle = [];
        if ($request->query->count() > 0) {
            $detalle["consulta"] = Detalle::filtrar($request->query->all(), $politica->camposPermitidos());
        }
        if (($politica->cuerpos() || $actor !== null) && $cuerpo !== null) {
            $detalle["cuerpo"] = Detalle::filtrar($cuerpo, $politica->camposPermitidos());
        }

        $this->registro->anotar($tipo, [
            "usuario_id" => (int) $usuario->getId(),
            "empresa_id" => $contexto["empresa"],
            "estacion_id" => $contexto["estacion"],
            "accion" => $graphql,
            "metodo" => mb_substr($request->getMethod(), 0, 7),
            "ruta" => $ruta->ruta,
            "estado" => $this->estado,
            "duracion_ms" => $this->duracionMs,
            "ip" => $request->getClientIp(),
            "agente" => mb_substr((string) $request->headers->get("User-Agent", ""), 0, 255) ?: null,
            "entidad" => $ruta->entidad,
            "registro_id" => $ruta->registroId,
            "detalle" => Detalle::json($detalle, $politica->maxDetalleBytes),
            "suplantado_por_id" => $actor?->getId(),
        ]);
    }

    /**
     * Empresa y estación del usuario, o null si no se le sigue.
     *
     * @return array{empresa: ?int, estacion: ?int}|null
     */
    public function contexto(Usuario $usuario, Politica $politica): ?array
    {
        $estacion = $usuario->getEstacion()?->getId();
        // Ya calculado en la petición (lo usan el filtro de empresa y los voters).
        $general = $this->autorizacion->de($usuario)->general();
        $sujeto = new Sujeto(
            (int) $usuario->getId(),
            $politica->necesitaPermisos() ? $this->autorizacion->de($usuario)->roles() : [],
            $general->empresas(),
            $estacion,
        );
        if (!$politica->sigue($sujeto, $this->reloj->now())) {
            return null;
        }

        return ["empresa" => $general->empresaUnica(), "estacion" => $estacion];
    }

    private static function esApi(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), "/api/") && $request->getMethod() !== "OPTIONS";
    }

    /** @return array<mixed>|null el cuerpo JSON, si lo hay */
    private static function cuerpo(Request $request): ?array
    {
        if (!in_array($request->getMethod(), ["POST", "PUT", "PATCH", "DELETE"], true) || !str_contains((string) $request->headers->get("Content-Type"), "json")) {
            return null;
        }
        $contenido = $request->getContent();
        if ($contenido === "" || strlen($contenido) > 1_000_000) {
            return null;
        }
        try {
            $datos = json_decode($contenido, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($datos) ? $datos : null;
    }
}
