<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Repository\MenuRepository;
use App\Security\PermissionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
#[Route('/api/menus-by-area', name: 'api_menus_by_area', methods: ['GET'])]
class MenusByAreaController extends AbstractController
{
    public function __invoke(
        #[CurrentUser] ?Usuario $user,
        PermissionManager $permissionManager,
        MenuRepository $menuRepository,
    ): JsonResponse {
        if (!$user) {
            return $this->json(['areas' => []]);
        }

        $roles = $user->getUserRoles()->toArray();
        $allRoles = [];
        foreach ($roles as $role) {
            $this->collectRoleWithParents($role, $allRoles);
        }

        $grouped = $menuRepository->findMenusByRoles($allRoles);

        $result = [];
        foreach ($grouped as $area => $data) {
            $items = array_map(fn($menu) => [
                'id' => $menu->getId(),
                'nombre' => $menu->getNombre(),
                'label' => $menu->getLabel(),
                'icon' => $menu->getIcon(),
                'sort' => $menu->getSort(),
                'ruta' => $menu->getReferenciaVueRoute()?->getPath(),
                'routeName' => $menu->getReferenciaVueRoute()?->getVueRouteName(),
                'children' => array_map(fn($child) => [
                    'id' => $child->getId(),
                    'nombre' => $child->getNombre(),
                    'label' => $child->getLabel(),
                    'icon' => $child->getIcon(),
                    'sort' => $child->getSort(),
                    'ruta' => $child->getReferenciaVueRoute()?->getPath(),
                    'routeName' => $child->getReferenciaVueRoute()?->getVueRouteName(),
                ], $menu->getChildren()->toArray()),
            ], $data['items']);

            $result[] = [
                'area' => $area,
                'items' => $items,
            ];
        }

        return $this->json(['areas' => $result]);
    }

    private function collectRoleWithParents(\App\Entity\Role $role, array &$collection): void
    {
        if (isset($collection[$role->getId()])) {
            return;
        }
        $collection[$role->getId()] = $role;
        foreach ($role->getParents() as $parent) {
            $this->collectRoleWithParents($parent, $collection);
        }
    }
}
