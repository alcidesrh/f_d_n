<?php

namespace App\Entity\Enum;

enum LayoutArea: string
{
    case TopbarRight = 'topbar_right';
    case SidebarLeft = 'sidebar_left';
    case SidebarRight = 'sidebar_right';
}
