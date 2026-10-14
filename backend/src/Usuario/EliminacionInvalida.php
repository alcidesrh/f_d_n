<?php

declare(strict_types=1);

namespace App\Usuario;

/** Lo que pidió el borrado de usuarios no se puede hacer; el mensaje va al usuario. */
final class EliminacionInvalida extends \DomainException {}
