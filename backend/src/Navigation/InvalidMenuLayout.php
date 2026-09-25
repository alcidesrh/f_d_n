<?php

declare(strict_types=1);

namespace App\Navigation;

/** Distribución de menús por área inválida (el controlador lo traduce a 422). */
final class InvalidMenuLayout extends \InvalidArgumentException
{
}
