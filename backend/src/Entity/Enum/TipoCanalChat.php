<?php

namespace App\Entity\Enum;

/**
 * Directo: dos personas (único por par). Grupo: con nombre, varios miembros.
 * Sistema: avisos automáticos para un usuario (solo lectura, uno por usuario).
 */
enum TipoCanalChat: string
{
    case Directo = "directo";
    case Grupo = "grupo";
    case Sistema = "sistema";
}
