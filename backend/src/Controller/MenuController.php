<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\Usuario;
use App\Navigation\InvalidMenuLayout;
use App\Navigation\MenuLayout;
use App\Navigation\MenuTree;
use App\Navigation\UserMenus;
use App\Taxonomy\InvalidTaxonomyTree;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Navegación por menús (ADR-018). El CRUD de `Menu` y `MenuItem` es el
 * genérico de GraphQL; aquí van el árbol de cada menú, su distribución por
 * áreas de la UI y los menús visibles para el usuario actual.
 */
#[AsController]
class MenuController extends AbstractController
{
    /** Editar menús y su distribución: la capacidad del CRUD de `Menu` (ADR-039). */
    public const EDITAR = 'menu.editar';

    public function __construct(
        private readonly MenuTree $menuTree,
        private readonly MenuLayout $menuLayout,
        private readonly UserMenus $userMenus,
    ) {}

    /** Menús del usuario actual por área: `{ area: [{ id, nombre, items: [...] }] }`. */
    #[Route('/api/me/menus', name: 'api_me_menus', methods: ['GET'])]
    public function mine(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        return $this->json($user ? $this->userMenus->for($user) : MenuLayout::emptyLayout());
    }

    /** Árbol del menú: `[{ id, label, icon, route, children: [...] }]`. */
    #[Route('/api/menus/{id<\d+>}/tree', name: 'api_menu_tree', methods: ['GET'])]
    public function tree(Menu $menu): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);

        return $this->json($this->menuTree->read($menu));
    }

    /** Reemplaza el árbol: `[{ id: menuItemId, children: [...] }]`; responde el árbol guardado. */
    #[Route('/api/menus/{id<\d+>}/tree', name: 'api_menu_tree_save', methods: ['PUT'])]
    public function saveTree(Menu $menu, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);

        try {
            $this->menuTree->write($menu, $request->toArray());
        } catch (InvalidTaxonomyTree $error) {
            return $this->json(['error' => $error->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->menuTree->read($menu));
    }

    /** Menús por área, en orden: `{ area: [menuId, ...] }`. */
    #[Route('/api/menu-layout', name: 'api_menu_layout', methods: ['GET'])]
    public function layout(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);

        return $this->json($this->menuLayout->get());
    }

    /** Reemplaza las áreas enviadas (las omitidas no cambian); responde la distribución completa. */
    #[Route('/api/menu-layout', name: 'api_menu_layout_save', methods: ['PUT'])]
    public function saveLayout(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);

        try {
            $this->menuLayout->replace($request->toArray());
        } catch (InvalidMenuLayout $error) {
            return $this->json(['error' => $error->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->menuLayout->get());
    }

}
