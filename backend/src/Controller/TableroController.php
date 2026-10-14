<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Tablero\ConsultaTablero;
use App\Tablero\Periodo;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Tablero de gestión de la portada: venta, ocupación, rutas, demanda,
 * operación del día y alertas. Mismo permiso que los reportes de venta
 * (`reporte.ventas`): son las mismas cifras, resumidas. Un usuario con
 * empresa asignada solo ve la suya; sin ella, puede elegir una
 * (`?empresa=`) o mirar todas. `?dias=` 1, 7 (por defecto), 30 o 90.
 */
#[AsController]
#[Route("/api/tablero", name: "api_tablero")]
final class TableroController extends AbstractController
{
    public function __construct(
        private readonly ConsultaTablero $consulta,
        private readonly ClockInterface $reloj,
    ) {}

    #[Route("", name: "", methods: ["GET"])]
    public function __invoke(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(ReporteController::VER);

        try {
            $periodo = Periodo::desde($request->query->get("dias"), \DateTimeImmutable::createFromInterface($this->reloj->now()));
        } catch (\InvalidArgumentException $e) {
            return $this->json(["error" => $e->getMessage(), "codigo" => "periodo_invalido"], 400);
        }
        $fija = $usuario->getEmpresa()?->getId();
        $pedida = $request->query->get("empresa");
        $empresa = $fija ?? (is_scalar($pedida) && ctype_digit((string) $pedida) ? (int) $pedida : null);

        return $this->json([
            "alcance" => ["empresa" => $empresa, "fija" => $fija !== null],
            "empresas" => $this->consulta->empresas($fija),
            "moneda" => ConsultaTablero::MONEDA,
        ] + $this->consulta->tablero($periodo, $empresa)->toArray());
    }
}
