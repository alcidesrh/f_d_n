<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Reporte\Diario\Suscripciones;
use App\Reporte\ReporteRechazado;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * "Mi cuenta → Reportes y avisos" (ADR-044): los reportes programados que el
 * usuario puede recibir y por dónde los quiere. Cualquiera con sesión; cada
 * reporte exige su capacidad al suscribirse y al enviarse.
 */
#[AsController]
final class MisReportesController extends AbstractController
{
    public function __construct(private readonly Suscripciones $suscripciones) {}

    #[Route("/api/me/reportes", name: "api_me_reportes", methods: ["GET"])]
    public function listar(#[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->json(["reportes" => $this->suscripciones->de($yo), "correo" => $yo->getEmail()]);
    }

    /** `{ porChat: bool, porCorreo: bool }`; los dos en false: deja de recibirlo. */
    #[Route("/api/me/reportes/{codigo}", name: "api_me_reportes_cambiar", methods: ["PUT"], requirements: ["codigo" => "[a-z_]+"])]
    public function cambiar(string $codigo, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $datos = $request->toArray();
        try {
            $this->suscripciones->cambiar($yo, $codigo, (bool) ($datos["porChat"] ?? false), (bool) ($datos["porCorreo"] ?? false));
        } catch (ReporteRechazado $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        return $this->json(["reportes" => $this->suscripciones->de($yo), "correo" => $yo->getEmail()]);
    }
}
