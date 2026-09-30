<?php

declare(strict_types=1);

namespace App\Venta\Pago;

/**
 * La pasarela no respondió a un paso que cobra: no se sabe si el cargo se
 * hizo. Hay que revisarlo en el portal del banco antes de cobrar de nuevo.
 */
final class PagoIncierto extends \RuntimeException {}
