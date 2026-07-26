<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\VueRoute;
use App\Services\VueRouteSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class SyncVueRoutesController extends AbstractController
{
    public function __construct(
        private readonly VueRouteSynchronizer $synchronizer,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/api/vue-routes/sync', name: 'api_vue_routes_sync', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload) || !isset($payload['routes'])) {
            return $this->json(['error' => 'Expected { "routes": [...] }'], 400);
        }

        $this->synchronizer->sync($payload['routes']);

        $routes = $this->entityManager->getRepository(VueRoute::class)
            ->findBy(['vueRoute' => null]);

        return $this->json($routes);
    }
}
