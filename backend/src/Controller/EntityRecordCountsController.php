<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class EntityRecordCountsController extends AbstractController {

    private const ENTITIES = [
        'Action',
        'Role',
        'Permiso',
        'Status',
        'ApiToken',
        'Usuario',
        'Localidad',
        'Nacion',
        'Asiento',
        'Bus',
        'Empresa',
        'Piloto',
        'BusMarca',
        'Boleto',
        'Salida',
        'Trayecto',
        'Enclave',
        'Tarifa',
        'Cliente',
        'Estacion',
        'Parada',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/entity-record-counts', name: 'api_entity_record_counts', methods: ['GET'])]
    public function __invoke(): JsonResponse {
        $result = [];

        foreach (self::ENTITIES as $shortName) {
            $className = 'App\\Entity\\' . $shortName;
            try {
                $count = $this->entityManager->getRepository($className)->count([]);
            } catch (\Exception) {
                $count = 0;
            }
            $result[$shortName] = $count;
        }

        return $this->json($result);
    }
}
