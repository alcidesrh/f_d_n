<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Catalogo\Catalogo;
use App\Acceso\Catalogo\DefinicionCapacidad;
use App\Afectacion\Afectaciones;
use App\Afectacion\ConsultaAfectaciones;
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
 * Afectaciones de la logística (ADR-070). Cualquier usuario con sesión
 * entra: lo que ve y lo que puede hacer lo decide el tipo de cada una
 * (`owner` para reportar, `target` para editar y resolver, donde cae).
 * Los tipos se mantienen por el CRUD genérico (`TipoAfectacion`,
 * `afectacion.tipos`). Errores: `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/afectaciones", name: "api_afectaciones_")]
final class AfectacionController extends AbstractController
{
    public const TIPOS = "afectacion.tipos";

    public function __construct(
        private readonly Afectaciones $afectaciones,
        private readonly ConsultaAfectaciones $consulta,
        private readonly ClockInterface $reloj,
    ) {}

    /** `?vista=abiertas|resueltas|todas&tipo=&pagina=` */
    #[Route("", name: "listar", methods: ["GET"])]
    public function listar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $vista = (string) $request->query->get("vista", "abiertas");
        if (!in_array($vista, ConsultaAfectaciones::VISTAS, true)) {
            $vista = "abiertas";
        }
        $tipo = $request->query->getInt("tipo");

        return $this->json($this->consulta->listar($usuario, $vista, $tipo > 0 ? $tipo : null, $request->query->getInt("pagina", 1)) + [
            "puede" => ["tipos" => $this->isGranted(self::TIPOS), "supervisar" => $this->consulta->supervisa($usuario)],
        ]);
    }

    /**
     * Para el formulario de reportar: `{ tipos: [{ id, nombre, sujeto, prioridad, empresas, estaciones }], estacion, empresas, estaciones, buses }`
     * (`empresas`/`estaciones` de un tipo: su alcance para reportarlo, null = todas).
     */
    #[Route("/para-reportar", name: "para_reportar", methods: ["GET"])]
    public function paraReportar(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        $datos = $this->consulta->paraReportar($usuario);
        $sujetos = array_column($datos["tipos"], "sujeto");

        return $this->json($datos + [
            "empresas" => $datos["tipos"] === [] ? [] : $this->consulta->empresas(),
            "estaciones" => in_array("estacion", $sujetos, true) ? $this->consulta->estaciones() : [],
            "buses" => in_array("bus", $sujetos, true) ? $this->consulta->buses() : [],
        ]);
    }

    /**
     * Las capacidades del catálogo, para elegir `owner`, `target` y
     * `escalamiento` al editar un tipo: `[{ codigo, nombre, area }]`, las de
     * Logística primero.
     */
    #[Route("/capacidades", name: "capacidades", methods: ["GET"])]
    public function capacidades(Catalogo $catalogo): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::TIPOS);
        $filas = array_map(
            static fn(DefinicionCapacidad $d) => ["codigo" => $d->codigo, "nombre" => $d->nombre, "area" => $d->area],
            array_values($catalogo->definiciones()),
        );
        usort($filas, static fn(array $a, array $b) => [$a["area"] !== "Logística", $a["area"], $a["nombre"]] <=> [$b["area"] !== "Logística", $b["area"], $b["nombre"]]);

        return $this->json($filas);
    }

    #[Route("/{id<\d+>}", name: "ver", methods: ["GET"])]
    public function ver(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json($this->consulta->una($id, $usuario));
    }

    /** `{ tipo, descripcion, empresa?, estacion?, bus? }` */
    #[Route("", name: "reportar", methods: ["POST"])]
    public function reportar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $a = $this->afectaciones->reportar($usuario, $request->toArray());

        return $this->json($this->consulta->fila($a, $usuario, $this->ahora()), Response::HTTP_CREATED);
    }

    /** `{ descripcion }` */
    #[Route("/{id<\d+>}/editar", name: "editar", methods: ["POST"])]
    public function editar(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $a = $this->afectaciones->editar($id, $usuario, (string) (self::texto($request, "descripcion") ?? ""));

        return $this->json($this->consulta->fila($a, $usuario, $this->ahora()));
    }

    /** `{ resolucion? }` */
    #[Route("/{id<\d+>}/resolver", name: "resolver", methods: ["POST"])]
    public function resolver(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $a = $this->afectaciones->resolver($id, $usuario, self::texto($request, "resolucion"));

        return $this->json($this->consulta->fila($a, $usuario, $this->ahora()));
    }

    private static function texto(Request $request, string $campo): ?string
    {
        $valor = $request->getContent() === "" ? null : ($request->toArray()[$campo] ?? null);

        return is_string($valor) ? $valor : null;
    }

    private function ahora(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->reloj->now());
    }
}
