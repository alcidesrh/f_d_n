<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Autorizacion;
use App\Mantenimiento\EstadoFlota;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mantenimiento de flota (ADR-065): en qué va cada bus con cada plan. Los
 * planes y las órdenes se mantienen por el CRUD genérico
 * (`PlanMantenimiento`, `OrdenMantenimiento`); aquí solo se lee el estado,
 * con el permiso de ver las órdenes y acotado a las empresas de su alcance.
 */
#[AsController]
#[Route("/api/mantenimiento", name: "api_mantenimiento_")]
final class MantenimientoController extends AbstractController
{
    public const VER = "orden_mantenimiento.ver";
    /** Registrar órdenes; a quien la tiene se le avisa lo que vence. */
    public const EDITAR = "orden_mantenimiento.editar";
    public const PLANES = "plan_mantenimiento.editar";
    public const BUSES = "bus.editar";

    public function __construct(
        private readonly EstadoFlota $estado,
        private readonly Autorizacion $autorizacion,
    ) {}

    #[Route("/estado", name: "estado", methods: ["GET"])]
    public function estado(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);
        $hoy = new \DateTimeImmutable("now", new \DateTimeZone("America/Guatemala"));

        return $this->json([
            "generado" => $hoy->format(DATE_ATOM),
            "planes" => $this->estado->planes(),
            "buses" => $this->estado->buses($hoy, $this->autorizacion->alcance(self::VER)),
            "puede" => [
                "registrar" => $this->isGranted(self::EDITAR),
                "planes" => $this->isGranted(self::PLANES),
                // Corregir el odómetro es editar el bus.
                "odometro" => $this->isGranted(self::BUSES),
            ],
        ]);
    }
}
