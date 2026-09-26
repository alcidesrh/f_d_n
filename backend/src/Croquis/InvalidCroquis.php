<?php

declare(strict_types=1);

namespace App\Croquis;

/** Croquis de bus inválido (el controlador lo traduce a 422). */
final class InvalidCroquis extends \InvalidArgumentException
{
}
