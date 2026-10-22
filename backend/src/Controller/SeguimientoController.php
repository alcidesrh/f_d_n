<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Ubicacion;
use App\Seguimiento\SeguimientoBuses;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mapa en tiempo real de buses en recorrido. Mismo permiso que el listado de
 * salidas (`salida.ver`): es otra vista de las mismas salidas.
 */
#[AsController]
#[Route("/api/seguimiento", name: "api_seguimiento_")]
final class SeguimientoController extends AbstractController
{
    public const VER = "salida.ver";

    public function __construct(
        private readonly SeguimientoBuses $seguimiento,
        private readonly Autorizacion $autorizacion,
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
}
