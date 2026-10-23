<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Carga `MenuItem` por lotes con su ícono y su ruta en la misma consulta
 * (cargador de sujetos para `TaxonomyTreeReader`), y los serializa. El destino
 * es `route` o `path` (enlace); los dos nulos, un grupo.
 */
final class MenuItemLoader
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<int> $ids
     *
     * @return array<int, MenuItem>
     */
    public function __invoke(string $subjectClass, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $items = $this->entityManager->createQueryBuilder()
            ->select('i', 'icon', 'route')
            ->from(MenuItem::class, 'i')
            ->join('i.icon', 'icon')
            ->leftJoin('i.route', 'route')
            ->where('i.id IN (:ids)')
            ->setParameter('ids', array_values(array_unique($ids)))
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($items as $item) {
            $byId[$item->getId()] = $item;
        }

        return $byId;
    }

    /**
     * @return array{id: int, label: string, icon: ?string, route: ?array{id: int, name: ?string, path: ?string, params: list<string>}, path: ?string}
     */
    public static function serialize(MenuItem $item): array
    {
        $route = $item->getRoute();

        return [
            'id' => $item->getId(),
            'label' => $item->getLabel(),
            'icon' => $item->getIcon()?->getIcon(),
            'route' => $route === null ? null : [
                'id' => $route->getId(),
                'name' => $route->getVueRouteName(),
                'path' => $route->getPath(),
                'params' => array_values($route->getParams() ?? []),
            ],
            'path' => $item->getPath(),
        ];
    }
}
