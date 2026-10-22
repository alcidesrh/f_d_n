<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Entity\BoletoAsiento;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Dispensas de hora en curso (ADR-040): los boletos que la operación de esta
 * petición anula o reasigna fuera de hora con una autorización. El guard del
 * flujo del boleto (`GuardiaBoleto`) las consulta; se olvidan al terminar la
 * petición (`ResetInterface`, el worker atiende la siguiente sin ellas).
 */
final class DispensasEnCurso implements ResetInterface
{
    /** @var array<int, true> */
    private array $fueraDeHora = [];

    public function permitirFueraDeHora(int ...$boletoIds): void
    {
        foreach ($boletoIds as $id) {
            $this->fueraDeHora[$id] = true;
        }
    }

    public function fueraDeHora(BoletoAsiento $b): bool
    {
        return isset($this->fueraDeHora[(int) $b->getId()]);
    }

    public function reset(): void
    {
        $this->fueraDeHora = [];
    }
}
