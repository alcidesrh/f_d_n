<?php

declare(strict_types=1);

namespace App\Services;

use App\Acceso\Catalogo\Catalogo;
use App\Acceso\Catalogo\DefinicionRecurso;
use App\Entity\VueRoute;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Refleja en `VueRoute` el árbol de rutas del router del frontend: nombre,
 * path, parámetros obligatorios y lo que exige cada pantalla (`capacidad`,
 * `recurso`; ADR-049). Las que ya no están se borran (y con ellas los ítems
 * de menú que llevaban a ellas).
 */
final class VueRouteSynchronizer
{
    /** @var list<string> códigos que el router exige y no están en el catálogo */
    private array $desconocidas = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Catalogo $catalogo,
    ) {}

    /**
     * Las capacidades que el router exige y no existen (una pantalla así no
     * la abre nadie): para avisar a quien sincroniza.
     *
     * @return list<string>
     */
    public function desconocidas(): array
    {
        return $this->desconocidas;
    }

    public function sync(array $routes, ?VueRoute $parent = null): array
    {
        if ($parent === null) {
            $this->desconocidas = [];
        }
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
        if ($parent === null) {
            $this->desconocidas = array_values(array_unique($this->desconocidas));
            sort($this->desconocidas);
        }

        return $handledNames;
    }

    private function createRoute(array $route, ?VueRoute $parent): VueRoute
    {
        $entity = new VueRoute();
        $entity->setVueRouteName($route['name'] ?? '');
        $entity->setNombre($route['name'] ?? '');
        $entity->setPath($route['path'] ?? null);
        $entity->setVueRoute($parent);
        $this->aplicarExigencias($entity, $route);

        $this->entityManager->persist($entity);

        return $entity;
    }

    private function updateRoute(VueRoute $entity, array $route): void
    {
        $entity->setVueRouteName($route['name'] ?? $entity->getVueRouteName());
        $entity->setNombre($route['name'] ?? $entity->getNombre());
        $entity->setPath($route['path'] ?? $entity->getPath());
        $this->aplicarExigencias($entity, $route);
    }

    /** Parámetros obligatorios, capacidades y regla del CRUD genérico de la pantalla. */
    private function aplicarExigencias(VueRoute $entity, array $route): void
    {
        $entity->setParams(self::extractParams($route['path'] ?? ''));
        $capacidades = self::capacidades($route['capacidad'] ?? null);
        $recurso = self::recurso($route['recurso'] ?? null);
        $entity->setCapacidades($capacidades);
        $entity->setRecurso($recurso);
        foreach ([...$capacidades, ...(isset($recurso['selector']) ? [$recurso['selector']] : [])] as $codigo) {
            if (!$this->catalogo->existe($codigo)) {
                $this->desconocidas[] = $codigo;
            }
        }
    }

    /**
     * `meta.capacidad`: un código o una lista; cualquier otra cosa, ninguna.
     *
     * @return list<string>
     */
    public static function capacidades(mixed $capacidad): array
    {
        $codigos = is_string($capacidad) ? [$capacidad] : (is_array($capacidad) ? $capacidad : []);

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $c) => is_string($c) ? trim($c) : '',
            $codigos,
        ), static fn(string $c) => $c !== '')));
    }

    /**
     * `meta.recurso`: `{ parametro, operacion: 'ver'|'editar', registro?, selector? }`;
     * si no tiene esa forma, la pantalla no es del CRUD genérico.
     *
     * @return array{parametro: string, operacion: string, registro?: string, selector?: string}|null
     */
    public static function recurso(mixed $recurso): ?array
    {
        if (!is_array($recurso)) {
            return null;
        }
        $parametro = $recurso['parametro'] ?? null;
        $operacion = $recurso['operacion'] ?? null;
        if (!is_string($parametro) || !preg_match('/^\w+$/', $parametro) || !in_array($operacion, [DefinicionRecurso::VER, DefinicionRecurso::EDITAR], true)) {
            return null;
        }
        $limpio = ['parametro' => $parametro, 'operacion' => $operacion];
        if (is_string($recurso['registro'] ?? null) && preg_match('/^\w+$/', $recurso['registro'])) {
            $limpio['registro'] = $recurso['registro'];
        }
        if (is_string($recurso['selector'] ?? null) && trim($recurso['selector']) !== '') {
            $limpio['selector'] = trim($recurso['selector']);
        }

        return $limpio;
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
     * Parámetros obligatorios de la ruta: `:entity?` (opcional) no cuenta, porque
     * la ruta funciona sin él (p. ej. `/lista` muestra el selector de entidad).
     *
     * @return list<string>
     */
    public static function extractParams(string $path): array
    {
        // Grupos: 1 = nombre, 2 = restricción opcional `(\d+)`, 3 = `?` si es opcional.
        preg_match_all('/:(\w+)(\([^)]*\))?(\?)?/', $path, $matches, PREG_SET_ORDER);

        $required = [];
        foreach ($matches as $match) {
            if (($match[3] ?? '') === '') {
                $required[] = $match[1];
            }
        }

        return $required;
    }
}
