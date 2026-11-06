<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Suplantacion\SuplantacionActual;
use App\Actividad\AjustesBitacora;
use App\Actividad\BitacoraRechazada;
use App\Actividad\CapturaPeticion;
use App\Actividad\Detalle;
use App\Actividad\RegistroActividad;
use App\Actividad\RutaNormalizada;
use App\Actividad\TipoActividad;
use App\Entity\Usuario;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Acciones de pantalla de la SPA para la bitácora de actividad (familia
 * `pantalla`, nivel máximo). Cualquier usuario con sesión: lo que manda es lo
 * suyo.
 *
 * - `GET /api/actividad/ajustes`: si su SPA debe mandar acciones y cada cuánto.
 * - `POST /api/actividad` `{ eventos: [{ accion, pantalla, fecha?, duracionMs?, detalle? }] }`
 *   (hasta `MAXIMO_EVENTOS`): van al búfer y se escriben al terminar, con la petición.
 */
#[AsController]
#[Route("/api/actividad", name: "api_actividad_")]
final class ActividadController extends AbstractController
{
    public const MAXIMO_EVENTOS = 200;

    public function __construct(
        private readonly AjustesBitacora $ajustes,
        private readonly CapturaPeticion $captura,
        private readonly RegistroActividad $registro,
        private readonly ClockInterface $reloj,
        private readonly SuplantacionActual $suplantacion,
    ) {}

    #[Route("/ajustes", name: "ajustes", methods: ["GET"])]
    public function ajustes(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        $politica = $this->ajustes->politica();

        return $this->json([
            "registrar" => $politica->registra(TipoActividad::PANTALLA) && $this->captura->contexto($usuario, $politica) !== null,
            "intervaloSegundos" => $politica->intervaloPantallaSegundos,
        ]);
    }

    #[Route("", name: "registrar", methods: ["POST"])]
    public function registrar(Request $request, #[CurrentUser] Usuario $usuario): Response
    {
        $politica = $this->ajustes->politica();
        if (!$politica->registra(TipoActividad::PANTALLA)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }
        $contexto = $this->captura->contexto($usuario, $politica);
        if ($contexto === null) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }
        $eventos = $request->toArray()["eventos"] ?? null;
        if (!is_array($eventos) || !array_is_list($eventos) || count($eventos) > self::MAXIMO_EVENTOS) {
            throw new BitacoraRechazada(sprintf("Se esperaba una lista de hasta %d eventos.", self::MAXIMO_EVENTOS), "eventos_invalidos", 400);
        }

        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());
        foreach ($eventos as $e) {
            if (!is_array($e) || !is_string($e["accion"] ?? null) || $e["accion"] === "") {
                continue;
            }
            $pantalla = is_string($e["pantalla"] ?? null) ? (string) parse_url($e["pantalla"], PHP_URL_PATH) : "";
            $this->registro->anotar(TipoActividad::PANTALLA, [
                "fecha" => self::fecha($e["fecha"] ?? null, $ahora),
                "usuario_id" => (int) $usuario->getId(),
                "empresa_id" => $contexto["empresa"],
                "estacion_id" => $contexto["estacion"],
                "accion" => mb_substr($e["accion"], 0, 60),
                "ruta" => $pantalla !== "" ? RutaNormalizada::de($pantalla)->ruta : null,
                "duracion_ms" => is_int($e["duracionMs"] ?? null) && $e["duracionMs"] >= 0 ? min($e["duracionMs"], 86_400_000) : null,
                "ip" => $request->getClientIp(),
                "detalle" => is_array($e["detalle"] ?? null) ? Detalle::json(Detalle::sinSecretos($e["detalle"]), $politica->maxDetalleBytes) : null,
                "suplantado_por_id" => $this->suplantacion->actor()?->getId(),
            ]);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** La hora del navegador si es razonable (últimas 24 h, no más de 5 min en el futuro); si no, ahora. */
    private static function fecha(mixed $ms, \DateTimeImmutable $ahora): string
    {
        if (is_int($ms) || is_float($ms)) {
            $segundos = (float) $ms / 1000;
            if ($segundos >= $ahora->getTimestamp() - 86_400 && $segundos <= $ahora->getTimestamp() + 300) {
                $f = \DateTimeImmutable::createFromFormat("U.u", sprintf("%.3F", $segundos));
                if ($f !== false) {
                    return $f->setTimezone($ahora->getTimezone())->format("Y-m-d H:i:s.vP");
                }
            }
        }

        return $ahora->format("Y-m-d H:i:s.vP");
    }
}
