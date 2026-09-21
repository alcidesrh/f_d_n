<?php

namespace App\Entity\Enum;

enum EstadoRecorrido: string
{
    case PROGRAMADA = "programada";
    case ABORDANDO = "abordando";
    case INICIADA = "iniciada";
    case FINALIZADA = "finalizada";
    case CANCELADA = "cancelada";

    /**
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::PROGRAMADA => [self::ABORDANDO, self::CANCELADA],
            self::ABORDANDO => [self::INICIADA],
            self::INICIADA => [self::FINALIZADA],
            self::FINALIZADA, self::CANCELADA => [],
        };
    }

    public function puedeTransicionarA(self $siguiente): bool
    {
        return in_array($siguiente, $this->transicionesPermitidas(), true);
    }
}
