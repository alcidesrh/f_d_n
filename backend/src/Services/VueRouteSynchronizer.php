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
        $handledNames = [];

        foreach ($routes as $route) {
            $vueRouteName = (string) ($route['name'] ?? '');
            $existing = $existingByParent[$vueRouteName] ?? null;

            if ($existing) {
                $this->updateRoute($existing, $route);
                $entity = $existing;
            } else {
                $entity = $this->createRoute($route, $parent);
            }

            $handledNames[] = $vueRouteName;

            if (!empty($route['children'])) {
                if ($entity->getId() === null) {
                    $this->entityManager->flush();
                }

                $this->sync($route['children'], $entity);
            }
        }

        $this->deleteRemoved($existingByParent, $handledNames);

        $this->entityManager->flush();

        return $handledNames;
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

        return $entity;
    }

    private function updateRoute(VueRoute $entity, array $route): void
    {
        $entity->setVueRouteName($route['name'] ?? $entity->getVueRouteName());
        $entity->setNombre($route['name'] ?? $entity->getNombre());
        $entity->setPath($route['path'] ?? $entity->getPath());
        $entity->setParams(self::extractParams($route['path'] ?? ''));
    }

    private function deleteRemoved(array $existingByParent, array $handledNames): void
    {
        foreach ($existingByParent as $entity) {
            if (!in_array($entity->getVueRouteName(), $handledNames, true)) {
                $this->removeRouteWithDescendants($entity);
            }
        }
    }

    /**
     * Elimina una ruta y todos sus descendientes de abajo hacia arriba para
     * respetar la restricción de clave foránea auto-referencial
     * (`vue_route.vue_route_id` no tiene ON DELETE en cascada).
     */
    private function removeRouteWithDescendants(VueRoute $entity): void
    {
        foreach ($entity->getHijos() as $hijo) {
            $this->removeRouteWithDescendants($hijo);
        }

        $this->entityManager->remove($entity);
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
