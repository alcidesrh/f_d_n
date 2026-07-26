<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\VueRoute;
use Doctrine\ORM\EntityManagerInterface;

final class VueRouteSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function sync(array $routes, ?VueRoute $parent = null): array
    {
        $existingByParent = $this->getExistingByParent($parent);
        $handledIds = [];

        foreach ($routes as $route) {
            $vueRouteName = $route['name'] ?? '';
            $existing = $existingByParent[$vueRouteName] ?? null;

            if ($existing) {
                $this->updateRoute($existing, $route);
                $entity = $existing;
            } else {
                $entity = $this->createRoute($route, $parent);
            }

            $handledIds[] = $entity->getId();

            if (!empty($route['children'])) {
                $childIds = $this->sync($route['children'], $entity);
                $handledIds = array_merge($handledIds, $childIds);
            }
        }

        $this->deleteRemoved($existingByParent, $handledIds);

        return $handledIds;
    }

    private function createRoute(array $route, ?VueRoute $parent): VueRoute
    {
        $entity = new VueRoute();
        $entity->setVueRouteName($route['name'] ?? '');
        $entity->setNombre($route['name'] ?? '');
        $entity->setPath($route['path'] ?? null);
        $entity->setParams(self::extractParams($route['path'] ?? ''));
        $entity->setVueRoute($parent);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    private function updateRoute(VueRoute $entity, array $route): void
    {
        $entity->setVueRouteName($route['name'] ?? $entity->getVueRouteName());
        $entity->setNombre($route['name'] ?? $entity->getNombre());
        $entity->setPath($route['path'] ?? $entity->getPath());
        $entity->setParams(self::extractParams($route['path'] ?? ''));

        $this->entityManager->flush();
    }

    private function deleteRemoved(array $existingByParent, array $handledIds): void
    {
        foreach ($existingByParent as $entity) {
            if (!in_array($entity->getId(), $handledIds, true)) {
                $this->entityManager->remove($entity);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @return array<string, VueRoute>
     */
    private function getExistingByParent(?VueRoute $parent): array
    {
        $repository = $this->entityManager->getRepository(VueRoute::class);

        $routes = $repository->findBy(['vueRoute' => $parent]);

        $result = [];
        foreach ($routes as $route) {
            $name = $route->getVueRouteName();
            if ($name !== null) {
                $result[$name] = $route;
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function extractParams(string $path): array
    {
        preg_match_all('/:(\w+)/', $path, $matches);

        return $matches[1];
    }
}
