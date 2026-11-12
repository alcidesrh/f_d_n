<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Entity\BoletoAsiento;
use App\Entity\Encomienda;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Dispensas en curso: los registros que la operación de esta petición opera
 * con una autorización. Fuera de hora (ADR-040): los boletos que anula o
 * reasigna con la salida ya pasada de hora. Fuera de estado (ADR-058): los
 * boletos que anula o reasigna, y las encomiendas que anula, como lo haría el
 * rol de sistema. Los guards de los flujos (`GuardiaBoleto`,
 * `GuardiaEncomienda`) las consultan; se olvidan al terminar la petición
 * (`ResetInterface`, el worker atiende la siguiente sin ellas).
 */
final class DispensasEnCurso implements ResetInterface
{
    /** @var array<int, true> */
    private array $fueraDeHora = [];

    /** @var array<int, true> */
    private array $fueraDeEstado = [];

    /** @var array<int, true> */
    private array $encomiendasFueraDeEstado = [];

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

    public function permitirFueraDeEstado(int ...$boletoIds): void
    {
        foreach ($boletoIds as $id) {
            $this->fueraDeEstado[$id] = true;
        }
    }

    public function fueraDeEstado(BoletoAsiento $b): bool
    {
        return isset($this->fueraDeEstado[(int) $b->getId()]);
    }

    public function permitirEncomiendaFueraDeEstado(int ...$encomiendaIds): void
    {
        foreach ($encomiendaIds as $id) {
            $this->encomiendasFueraDeEstado[$id] = true;
        }
    }

    public function encomiendaFueraDeEstado(Encomienda $e): bool
    {
        return isset($this->encomiendasFueraDeEstado[(int) $e->getId()]);
    }

    public function reset(): void
    {
        $this->fueraDeHora = [];
        $this->fueraDeEstado = [];
        $this->encomiendasFueraDeEstado = [];
    }
}
