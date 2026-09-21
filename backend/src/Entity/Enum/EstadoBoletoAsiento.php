<?php

namespace App\Entity\Enum;

enum EstadoBoletoAsiento: string
{
    case EMITIDO = "emitido";
    case CHEQUEADO = "chequeado";
    case TRANSITO = "transito";
    case FINALIZADO = "finalizado";
    case ANULADO = "anulado";
    case REASIGNADO = "reasignado";

    /**
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::EMITIDO => [
                self::CHEQUEADO,
                self::ANULADO,
                self::REASIGNADO,
            ],
            self::CHEQUEADO => [self::TRANSITO],
            self::TRANSITO => [self::FINALIZADO],
            self::FINALIZADO, self::ANULADO, self::REASIGNADO => [],
        };
    }

    public function puedeTransicionarA(self $siguiente): bool
    {
        return in_array($siguiente, $this->transicionesPermitidas(), true);
    }
}
