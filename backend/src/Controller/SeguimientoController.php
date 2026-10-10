<?php

declare(strict_types=1);

namespace App\Controller;

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

    public function __construct(private readonly SeguimientoBuses $seguimiento) {}

    #[Route("/buses", name: "buses", methods: ["GET"])]
    public function buses(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->json($this->seguimiento->enRecorrido(new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala'))));
    }
}
