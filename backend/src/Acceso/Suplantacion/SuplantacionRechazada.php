<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

use App\Excepcion\Rechazo;

/** No se puede empezar (o configurar) una suplantación. */
final class SuplantacionRechazada extends Rechazo {}
