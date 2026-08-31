<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\LayoutArea;
use App\Entity\Menu;
use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    /**
     * Returns menus grouped by layout area for the given roles.
     *
     * @param Role[] $roles
     * @return array<string, array{area: string, items: Menu[]}>
     */
    public function findMenusByRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.allowRoles', 'r')
            ->innerJoin('m.layoutAssignments', 'la')
            ->where('r IN (:roles)')
            ->setParameter('roles', $roles)
            ->orderBy('la.position', 'ASC')
            ->addOrderBy('m.sort', 'ASC')
            ->addOrderBy('m.nombre', 'ASC')
            ->distinct();

        $menus = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($menus as $menu) {
            foreach ($menu->getLayoutAssignments() as $assignment) {
                $area = $assignment->getLayoutArea()->value;
                if (!isset($grouped[$area])) {
                    $grouped[$area] = [
                        'area' => $area,
                        'items' => [],
                    ];
                }
                $grouped[$area]['items'][] = $menu;
            }
        }

        uasort($grouped, fn($a, $b) => $a['area'] <=> $b['area']);

        return $grouped;
    }

    /**
     * Returns only root menus (no parents) grouped by layout area for the given roles.
     *
     * @param Role[] $roles
     * @return array<string, array{area: string, items: Menu[]}>
     */
    public function findRootMenusByRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.allowRoles', 'r')
            ->innerJoin('m.layoutAssignments', 'la')
            ->leftJoin('m.parents', 'p')
            ->where('r IN (:roles)')
            ->andWhere('p IS NULL')
            ->setParameter('roles', $roles)
            ->orderBy('la.position', 'ASC')
            ->addOrderBy('m.sort', 'ASC')
            ->addOrderBy('m.nombre', 'ASC')
            ->distinct();

        $menus = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($menus as $menu) {
            foreach ($menu->getLayoutAssignments() as $assignment) {
                $area = $assignment->getLayoutArea()->value;
                if (!isset($grouped[$area])) {
                    $grouped[$area] = [
                        'area' => $area,
                        'items' => [],
                    ];
                }
                $grouped[$area]['items'][] = $menu;
            }
        }

        uasort($grouped, fn($a, $b) => $a['area'] <=> $b['area']);

        return $grouped;
    }
}
