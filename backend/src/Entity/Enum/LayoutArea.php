<?php

namespace App\Entity\Enum;

/**
 * Áreas del shell que despliegan menús (`MenuPlacement`); una por slot
 * `menu-content`: `AppHeader`, `SidebarLeft` y `SidebarRight`.
 */
enum LayoutArea: string
{
    case TopbarRight = 'topbar_right';
    case SidebarLeft = 'sidebar_left';
    case SidebarRight = 'sidebar_right';
}
