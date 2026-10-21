<?php

declare(strict_types=1);

namespace App\Metadata;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Entidades cuya configuración se guardó en esta petición o comando
 * (`VigilanteConfiguracion`). Se publican juntas al terminar
 * (`PublicarAlTerminar`): una sincronización que guarda 60 entidades da una
 * sola revisión, no 60.
 */
final class ConfiguracionesTocadas implements ResetInterface
{
    /** @var array<string, true> */
    private array $entidades = [];

    /** Algo cambió sin que se sepa de qué entidad: se revisan todas. */
    private bool $todas = false;

    public function anotar(?string $entidad): void
    {
        if ($entidad === null || $entidad === "") {
            $this->todas = true;
        } else {
            $this->entidades[$entidad] = true;
        }
    }

    public function hay(): bool
    {
        return $this->todas || $this->entidades !== [];
    }

    /**
     * Las anotadas, y las olvida.
     *
     * @return list<string>|null null: todas
     */
    public function tomar(): ?array
    {
        $entidades = $this->todas ? null : array_map("strval", array_keys($this->entidades));
        if ($entidades !== null) {
            sort($entidades);
        }
        $this->reset();

        return $entidades;
    }

    public function reset(): void
    {
        $this->entidades = [];
        $this->todas = false;
    }
}
