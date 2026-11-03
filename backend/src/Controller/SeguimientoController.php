<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Ubicacion;
use App\Seguimiento\GpsReciente;
use App\Seguimiento\LecturaOsmAnd;
use App\Seguimiento\SeguimientoBuses;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mapa en tiempo real de buses en recorrido. Mismo permiso que el listado de
 * salidas (`salida.ver`): es otra vista de las mismas salidas.
 *
 * `/gps` recibe la posición de los dispositivos (Traccar Client, protocolo
 * OsmAnd). Sin Bearer: con la clave compartida en `?clave=` (env
 * `SEGUIMIENTO_GPS_CLAVE`), que va en la URL del servidor que se configura
 * en la app. El identificador del dispositivo es el código del bus.
 */
#[AsController]
#[Route("/api/seguimiento", name: "api_seguimiento_")]
final class SeguimientoController extends AbstractController
{
    public const VER = "salida.ver";

    public function __construct(
        private readonly SeguimientoBuses $seguimiento,
        private readonly Autorizacion $autorizacion,
        private readonly GpsReciente $gps,
        #[Autowire(env: "SEGUIMIENTO_GPS_CLAVE")] private readonly string $claveGps,
    ) {}

    #[Route("/buses", name: "buses", methods: ["GET"])]
    public function buses(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        $datos = $this->seguimiento->enRecorrido(new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala')));
        // Solo los buses de las empresas donde ve salidas (ADR-039).
        $alcance = $this->autorizacion->alcance(Capacidad::SALIDA_VER);
        $ve = static fn(int $empresa) => $alcance->contiene(Ubicacion::empresa($empresa ?: null));
        $datos['buses'] = array_values(array_filter($datos['buses'], static fn(array $b) => $ve((int) $b['empresaId'])));
        $datos['empresas'] = array_values(array_filter($datos['empresas'], static fn(array $e) => $ve((int) $e['id'])));

        return $this->json($datos);
    }

    #[Route("/gps", name: "gps", methods: ["GET", "POST"])]
    public function gps(Request $request): Response
    {
        if ($this->claveGps === "") {
            return new Response("GPS sin clave configurada (SEGUIMIENTO_GPS_CLAVE).", 503);
        }
        if (!hash_equals($this->claveGps, (string) $request->query->get("clave", ""))) {
            return new Response("Clave incorrecta.", 403);
        }

        $json = null;
        if (str_contains((string) $request->headers->get("Content-Type"), "json")) {
            try {
                $json = $request->toArray();
            } catch (\Throwable) {
                return new Response("JSON inválido.", 400);
            }
        }
        try {
            $lectura = LecturaOsmAnd::desde([...$request->query->all(), ...$request->request->all()], $json, time());
        } catch (\InvalidArgumentException $e) {
            return new Response($e->getMessage(), 400);
        }
        $this->gps->guardar($lectura->busCodigo, $lectura->posicion);

        return new Response("", 200);
    }
}
