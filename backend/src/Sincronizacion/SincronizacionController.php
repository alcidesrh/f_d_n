<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Acceso\Capacidad;
use App\Sincronizacion\Legado\ApartadosLegado;
use App\Venta\Excepcion\VentaRechazada;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La sincronización con el legado (ADR-042).
 *
 * - `GET /api/sincronizacion`: si está encendida y cómo va (el indicador).
 * - `PUT /api/sincronizacion` `{activo}`: el interruptor (`config.editar`).
 * - `POST /api/sincronizacion/legado/*`: lo que llama el bundle del legado
 *   antes y después de vender. Sin sesión: con la clave compartida en
 *   `X-Sincronizacion-Clave` (env `SINCRONIZACION_CLAVE`).
 */
#[AsController]
#[Route("/api/sincronizacion")]
class SincronizacionController extends AbstractController
{
    public const CABECERA_CLAVE = "X-Sincronizacion-Clave";

    public function __construct(
        private readonly Interruptor $interruptor,
        private readonly ApartadosLegado $apartados,
        private readonly LoggerInterface $logger,
        #[Autowire(env: "SINCRONIZACION_CLAVE")] private readonly string $clave,
    ) {}

    #[Route("", name: "api_sincronizacion_estado", methods: ["GET"])]
    public function estado(): JsonResponse
    {
        return $this->json($this->interruptor->estado());
    }

    #[Route("", name: "api_sincronizacion_cambiar", methods: ["PUT"])]
    public function cambiar(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_EDITAR->value);
        $datos = $request->toArray();
        if (!is_bool($datos["activo"] ?? null)) {
            return $this->json(["error" => "Falta `activo` (true o false)."], 422);
        }
        try {
            $aviso = $this->interruptor->cambiar($datos["activo"], $this->getUser()?->getUserIdentifier() ?? "?");
        } catch (\RuntimeException $e) {
            return $this->json(["error" => $e->getMessage()], 503);
        }

        return $this->json([...$this->interruptor->estado(), "aviso" => $aviso]);
    }

    /** `{salida, origen?, destino?, asientos: [números]}` → `{token, apartados, expira}` o 409 con los ocupados. */
    #[Route("/legado/apartar", name: "api_sincronizacion_legado_apartar", methods: ["POST"])]
    public function apartar(Request $request): JsonResponse
    {
        return $this->delLegado($request, function (array $d) {
            if (!is_numeric($d["salida"] ?? null) || !is_array($d["asientos"] ?? null)) {
                return $this->json(["error" => "Faltan `salida` y `asientos`."], 422);
            }

            return $this->json($this->apartados->apartar(
                (int) $d["salida"],
                is_numeric($d["origen"] ?? null) ? (int) $d["origen"] : null,
                is_numeric($d["destino"] ?? null) ? (int) $d["destino"] : null,
                array_map("intval", array_values($d["asientos"])),
            ));
        });
    }

    /** `{token}`: el legado guardó la venta. */
    #[Route("/legado/confirmar", name: "api_sincronizacion_legado_confirmar", methods: ["POST"])]
    public function confirmar(Request $request): JsonResponse
    {
        return $this->delLegado($request, fn(array $d) => $this->json(["importado" => $this->apartados->confirmar((string) ($d["token"] ?? ""))]));
    }

    /** `{token}`: el legado no guardó la venta. */
    #[Route("/legado/liberar", name: "api_sincronizacion_legado_liberar", methods: ["POST"])]
    public function liberar(Request $request): JsonResponse
    {
        return $this->delLegado($request, function (array $d) {
            $this->apartados->liberar((string) ($d["token"] ?? ""));

            return $this->json(["ok" => true]);
        });
    }

    /** @param \Closure(array<string, mixed>): JsonResponse $accion */
    private function delLegado(Request $request, \Closure $accion): JsonResponse
    {
        if ($this->interruptor->soloTraer()) {
            return $this->json(["error" => "Esta instancia solo trae del legado (SINCRONIZACION_SOLO_TRAER): no aparta asientos."], 503);
        }
        if ($this->clave === "") {
            return $this->json(["error" => "La sincronización no tiene clave configurada (SINCRONIZACION_CLAVE)."], 503);
        }
        if (!hash_equals($this->clave, (string) $request->headers->get(self::CABECERA_CLAVE, ""))) {
            return $this->json(["error" => "Clave incorrecta."], 403);
        }
        try {
            return $accion($request->toArray());
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        } catch (\Throwable $e) {
            $this->logger->error("Sincronización: el legado llamó y falló: {error}", ["error" => $e->getMessage(), "exception" => $e]);

            return $this->json(["error" => $e->getMessage()], 500);
        }
    }
}
