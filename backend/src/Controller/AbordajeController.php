<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Salida\Abordaje\Abordaje;
use App\Salida\SalidaRechazada;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Abordaje de salidas (ADR-032), el "Chequear boletos por salida" del
 * legado: salidas por abordar o en curso, sus boletos y las operaciones
 * abordar, chequear (presentes / no presentados), iniciar y finalizar. Errores como
 * `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/abordaje", name: "api_abordaje_")]
final class AbordajeController extends AbstractController
{
    public const CHEQUEAR = "boleto.chequear";
    public const ABORDAR = "salida.abordar";
    public const INICIAR = "salida.iniciar";
    public const FINALIZAR = "salida.finalizar";

    public function __construct(private readonly Abordaje $abordaje) {}

    /** Salidas del selector y qué puede hacer el usuario. */
    #[Route("/salidas", name: "salidas", methods: ["GET"])]
    public function salidas(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->exigirAlguno();

        return $this->json([
            "items" => $this->abordaje->salidas($usuario),
            "puede" => $this->puede(),
        ]);
    }

    /** La salida y sus boletos emitidos, chequeados, en tránsito y finalizados. */
    #[Route("/salidas/{id<\d+>}", name: "detalle", methods: ["GET"])]
    public function detalle(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->exigirAlguno();

        return $this->responder(fn() => $this->abordaje->detalle($id, $usuario));
    }

    #[Route("/salidas/{id<\d+>}/abordar", name: "abordar", methods: ["POST"])]
    public function abordar(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ABORDAR);

        return $this->responder(fn() => $this->abordaje->abordar($id, $usuario));
    }

    /** `{ si: number[], no: number[] }`: ids de boleto presentes y no presentados. */
    #[Route("/salidas/{id<\d+>}/chequeo", name: "chequear", methods: ["POST"])]
    public function chequear(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CHEQUEAR);
        $datos = $request->toArray();

        return $this->responder(fn() => $this->abordaje->chequear(
            $id,
            array_values((array) ($datos["si"] ?? [])),
            array_values((array) ($datos["no"] ?? [])),
            $usuario,
        ));
    }

    #[Route("/salidas/{id<\d+>}/iniciar", name: "iniciar", methods: ["POST"])]
    public function iniciar(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::INICIAR);

        return $this->responder(fn() => $this->abordaje->iniciar($id, $usuario));
    }

    #[Route("/salidas/{id<\d+>}/finalizar", name: "finalizar", methods: ["POST"])]
    public function finalizar(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::FINALIZAR);

        return $this->responder(fn() => $this->abordaje->finalizar($id, $usuario));
    }

    /** @return array{chequear: bool, abordar: bool, iniciar: bool, finalizar: bool, manifiestos: bool} */
    private function puede(): array
    {
        return [
            "chequear" => $this->isGranted(self::CHEQUEAR),
            "abordar" => $this->isGranted(self::ABORDAR),
            "iniciar" => $this->isGranted(self::INICIAR),
            "finalizar" => $this->isGranted(self::FINALIZAR),
            "manifiestos" => $this->isGranted(SalidaController::VER),
        ];
    }

    private function exigirAlguno(): void
    {
        if (!in_array(true, [$this->isGranted(self::CHEQUEAR), $this->isGranted(self::ABORDAR), $this->isGranted(self::INICIAR), $this->isGranted(self::FINALIZAR)], true)) {
            throw $this->createAccessDeniedException();
        }
    }

    /** @param callable(): mixed $operacion */
    private function responder(callable $operacion): JsonResponse
    {
        try {
            return $this->json($operacion());
        } catch (SalidaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }
}
