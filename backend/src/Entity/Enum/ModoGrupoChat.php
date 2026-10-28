<?php

namespace App\Entity\Enum;

/**
 * Quién escribe en un grupo del chat. Abierto: todos sus miembros. Dirigido:
 * todos reciben, pero solo escriben el dueño y los emisores que él elige
 * (personas o grupos miembros).
 */
enum ModoGrupoChat: string
{
    case Abierto = "abierto";
    case Dirigido = "dirigido";
}
