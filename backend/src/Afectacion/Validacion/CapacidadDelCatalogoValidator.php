<?php

declare(strict_types=1);

namespace App\Afectacion\Validacion;

use App\Acceso\Catalogo\Catalogo;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class CapacidadDelCatalogoValidator extends ConstraintValidator
{
    public function __construct(private readonly Catalogo $catalogo) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CapacidadDelCatalogo) {
            throw new UnexpectedTypeException($constraint, CapacidadDelCatalogo::class);
        }
        if ($value === null || $value === "") {
            return;
        }
        if (!is_string($value) || !$this->catalogo->existe($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter("{{ codigo }}", is_scalar($value) ? (string) $value : get_debug_type($value))
                ->addViolation();
        }
    }
}
