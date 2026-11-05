<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Capacidad;
use App\Actividad\ConsultaBitacoraDiaria;
use App\Actividad\GestionBitacora;
use App\Entity\Usuario;
use App\Http\Parametros;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Bitácora diaria de los usuarios.
 *
 * Consulta (`bitacora.usuarios`):
 * - `GET /api/bitacora-diaria/dia?fecha=AAAA-MM-DD`: quiénes tuvieron actividad y su resumen.
 * - `GET /api/bitacora-diaria/dia/{usuario}?fecha=AAAA-MM-DD`: su resumen y la línea de tiempo.
 *
 * Configuración (`bitacora.configurar`):
 * - `GET|PUT /api/bitacora-diaria/configuracion`.
 * - `GET /api/bitacora-diaria/usuarios?q=`: usuarios para la selección.
 */
#[AsController]
#[Route("/api/bitacora-diaria", name: "api_bitacora_diaria_")]
final class BitacoraDiariaController extends AbstractController
{
    public function __construct(
        private readonly ConsultaBitacoraDiaria $consulta,
        private readonly GestionBitacora $gestion,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
    ) {}

    #[Route("/dia", name: "dia", methods: ["GET"])]
    public function dia(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::BITACORA_USUARIOS->value);

        return $this->json($this->consulta->dia($this->fecha($request)));
    }

    #[Route("/dia/{usuario}", name: "usuario", requirements: ["usuario" => "\d+"], methods: ["GET"])]
    public function usuario(int $usuario, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::BITACORA_USUARIOS->value);

        return $this->json($this->consulta->deUsuario($usuario, $this->fecha($request)));
    }

    #[Route("/configuracion", name: "configuracion", methods: ["GET"])]
    public function configuracion(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::BITACORA_CONFIGURAR->value);

        return $this->json($this->gestion->datos());
    }

    #[Route("/configuracion", name: "configuracion_guardar", methods: ["PUT"])]
    public function guardar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::BITACORA_CONFIGURAR->value);
        $this->gestion->guardar($request->toArray(), $this->em->find(Usuario::class, $usuario->getId()));

        return $this->json($this->gestion->datos());
    }

    #[Route("/usuarios", name: "usuarios", methods: ["GET"])]
    public function usuarios(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::BITACORA_CONFIGURAR->value);
        $q = Parametros::texto($request->query->get("q"), 60) ?? "";
        $filas = $this->em->getConnection()->fetchAllAssociative(
            "SELECT id, username, full_name FROM usuario
              WHERE activo = true AND (:q = '' OR username ILIKE :patron OR full_name ILIKE :patron)
              ORDER BY coalesce(full_name, username) LIMIT 20",
            ["q" => $q, "patron" => "%" . addcslashes($q, "%_\\") . "%"],
        );

        return $this->json(array_map(static fn(array $f) => [
            "id" => (int) $f["id"],
            "nombre" => (string) ($f["full_name"] ?: $f["username"]),
            "username" => (string) $f["username"],
        ], $filas));
    }

    private function fecha(Request $request): \DateTimeImmutable
    {
        return Parametros::dia($request->query->get("fecha"), "la fecha") ?? \DateTimeImmutable::createFromInterface($this->reloj->now());
    }
}
