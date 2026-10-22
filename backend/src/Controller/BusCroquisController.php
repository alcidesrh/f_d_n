<?php

declare(strict_types=1);

namespace App\Controller;

use App\Croquis\Croquis;
use App\Croquis\CroquisBus;
use App\Croquis\InvalidCroquis;
use App\Croquis\PlantillasCroquis;
use App\Entity\Bus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Croquis de los buses (asientos, chofer y puertas por planta). Los datos
 * descriptivos del bus van por el CRUD genérico de GraphQL; la distribución,
 * que se guarda entera de una vez, por aquí.
 */
#[AsController]
class BusCroquisController extends AbstractController
{
    /** Las del bus en la API genérica, sobre su empresa (ADR-039). */
    public const VER = "bus.ver";
    public const EDITAR = "bus.editar";

    public function __construct(
        private readonly CroquisBus $croquis,
        private readonly PlantillasCroquis $plantillas,
    ) {}

    /** `{ elementos: [{ tipo, id, planta, fila, columna, numero?, clase?, conBoletos? }] }`. */
    #[Route("/api/buses/{id<\d+>}/croquis", name: "api_bus_croquis", methods: ["GET"])]
    public function show(Bus $bus): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER, $bus);

        return $this->json($this->croquis->leer($bus));
    }

    /** Reemplaza el croquis: `{ elementos: [...] }`; responde el croquis guardado. */
    #[Route("/api/buses/{id<\d+>}/croquis", name: "api_bus_croquis_save", methods: ["PUT"])]
    public function save(Bus $bus, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR, $bus);

        try {
            $this->croquis->guardar($bus, Croquis::desdeArray($request->toArray()));
        } catch (InvalidCroquis $error) {
            return $this->json(
                ["error" => $error->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->json($this->croquis->leer($bus));
    }

    /** Croquis distintos de la flota con los buses que los usan (plantillas). */
    #[Route("/api/croquis/plantillas", name: "api_croquis_plantillas", methods: ["GET"])]
    public function plantillas(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->json($this->plantillas->listar());
    }

}
