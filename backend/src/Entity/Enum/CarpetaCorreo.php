<?php

namespace App\Entity\Enum;

/**
 * Dónde está un correo en el buzón de su cuenta. "Destacados" no es una
 * carpeta: es una marca que se filtra en todas.
 */
enum CarpetaCorreo: string
{
    case Entrada = "entrada";
    case Enviados = "enviados";
    case Borradores = "borradores";
    case Papelera = "papelera";
}
