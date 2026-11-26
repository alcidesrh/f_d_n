<?php

declare(strict_types=1);

namespace App\Afectacion\Validacion;

use Symfony\Component\Validator\Constraint;

/**
 * El valor es el código de una capacidad que existe (`App\Acceso\Catalogo`,
 * ADR-039). Lo usa el tipo de afectación (ADR-070): quién la reporta, quién
 * la atiende y a quién se escala son capacidades, no roles ni usuarios.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class CapacidadDelCatalogo extends Constraint
{
    public string $message = "«{{ codigo }}» no es una capacidad del sistema.";
}
