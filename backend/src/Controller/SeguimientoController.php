<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Ubicacion;
use App\Acceso\Ubicador;
use App\Entity\Salida;
use App\Seguimiento\GpsReciente;
use App\Seguimiento\HistorialGps;
use App\Seguimiento\LecturaOsmAnd;
use App\Seguimiento\SeguimientoBuses;
use App\Seguimiento\TelefonosDePrueba;
use App\Seguimiento\Trayectorias;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
 * en la app. El identificador del dispositivo es el código del bus. Cada
 * lectura queda además en el historial, con el que se reproduce la
 * trayectoria de una salida (`/trayectorias`, `/salidas/{id}/trayectoria`,
 * ADR-050). Temporal: los teléfonos de prueba (`TelefonosDePrueba`, nombre
 * acordado en vez de código de bus) van en las mismas respuestas, solo para
 * quien ve todas las empresas, y su trayectoria en `/telefonos/trayectoria`.
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
        private readonly HistorialGps $historial,
        private readonly Trayectorias $trayectorias,
        private readonly TelefonosDePrueba $telefonos,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
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
        $datos['telefonos'] = $alcance->sinRestriccion() ? $this->telefonos->enVivo($datos['generado']) : [];

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
        try {
            $this->telefonos->es($lectura->busCodigo)
                ? $this->telefonos->registrar($lectura->busCodigo, $lectura->posicion)
                : $this->historial->registrar($lectura->busCodigo, $lectura->posicion);
        } catch (\Throwable $e) {
            // El mapa en vivo no depende del historial: con un error, Traccar reenviaría y se atrasaría.
            $this->logger->error("No se guardó la lectura GPS en el historial: {mensaje}", ["mensaje" => $e->getMessage(), "bus" => $lectura->busCodigo]);
        }

        return new Response("", 200);
    }

    /** Salidas de un día (`?fecha=AAAA-MM-DD`, hoy si falta) con lecturas GPS, para elegir cuál reproducir. */
    #[Route("/trayectorias", name: "trayectorias", methods: ["GET"])]
    public function trayectorias(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        $zona = new \DateTimeZone("America/Guatemala");
        $texto = (string) $request->query->get("fecha", "");
        $dia = "" === $texto ? new \DateTimeImmutable("today", $zona) : \DateTimeImmutable::createFromFormat("!Y-m-d", $texto, $zona);
        if (false === $dia || ("" !== $texto && $dia->format("Y-m-d") !== $texto)) {
            return $this->json(["error" => "Fecha inválida (AAAA-MM-DD)."], 400);
        }

        $alcance = $this->autorizacion->alcance(Capacidad::SALIDA_VER);

        return $this->json([
            ...$this->trayectorias->delDia($dia, static fn(int $empresa) => $alcance->contiene(Ubicacion::empresa($empresa ?: null))),
            'telefonos' => $alcance->sinRestriccion() ? $this->telefonos->delDia($dia) : [],
        ]);
    }

    /** Trayectoria GPS de una salida para reproducirla: lecturas, ruta y estaciones. */
    #[Route("/salidas/{id<\d+>}/trayectoria", name: "trayectoria", methods: ["GET"])]
    public function trayectoria(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        $salida = $this->em->find(Salida::class, $id);
        if (null === $salida || null === $salida->getBus()) {
            return $this->json(["error" => "La salida no existe."], 404);
        }
        if (!$this->autorizacion->puede(Capacidad::SALIDA_VER, Ubicador::salida($salida))) {
            return $this->json(["error" => Autorizacion::mensaje(Capacidad::SALIDA_VER, Ubicador::salida($salida))], 403);
        }

        return $this->json($this->trayectorias->deSalida($salida));
    }

    /** Trayectoria de un teléfono de prueba que empieza en `?desde=` (epoch), para reproducirla. */
    #[Route("/telefonos/trayectoria", name: "telefono_trayectoria", methods: ["GET"])]
    public function trayectoriaDeTelefono(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);
        if (!$this->autorizacion->alcance(Capacidad::SALIDA_VER)->sinRestriccion()) {
            return $this->json(["error" => "Los teléfonos de prueba solo los ve quien ve las salidas de todas las empresas."], 403);
        }

        $dispositivo = (string) $request->query->get("dispositivo", "");
        $desde = (string) $request->query->get("desde", "");
        if (!$this->telefonos->es($dispositivo)) {
            return $this->json(["error" => "No es un teléfono de prueba."], 404);
        }
        if (!ctype_digit($desde)) {
            return $this->json(["error" => "Falta el inicio (?desde=, epoch en segundos)."], 400);
        }

        return $this->json($this->telefonos->trayectoria($dispositivo, (int) $desde));
    }
}
