<?php

declare(strict_types=1);

namespace App\Controller;

use App\Tarifa\MapaTarifas;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tarifas para el mapa de tarifas (`/tarifas/mapa`). Verlas pide `salida.ver`,
 * igual que las rutas; cambiarlas va por el formulario de `BoletoTarifa`.
 */
#[Route("/api/gestion-tarifas", name: "api_gestion_tarifas_")]
final class TarifaMapaController extends AbstractController
{
    public function __construct(private readonly MapaTarifas $mapa) {}

    #[Route("/ruta/{id<\d+>}", name: "ruta", methods: ["GET"])]
    public function ruta(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(SalidaController::VER);

        $datos = $this->mapa->ruta($id);
        if ($datos === null) {
            throw $this->createNotFoundException("Ruta no encontrada.");
        }

        return $this->json($datos);
    }

    /** Origen y destino son enclaves (ids distintos). */
    #[Route("/trayecto/{origen<\d+>}/{destino<\d+>}", name: "trayecto", methods: ["GET"])]
    public function trayecto(int $origen, int $destino): JsonResponse
    {
        $this->denyAccessUnlessGranted(SalidaController::VER);

        $datos = $this->mapa->trayecto($origen, $destino);
        if ($datos === null) {
            throw $this->createNotFoundException("Trayecto no válido.");
        }

        return $this->json($datos);
    }
}
