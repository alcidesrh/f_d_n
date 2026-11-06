<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

use App\Entity\ApiToken;
use App\Entity\Usuario;
use Symfony\Contracts\Service\ResetInterface;

/**
 * El token con el que se autenticó la petición, si es de suplantación. Lo
 * fija `App\Security\ApiTokenHandler` y se olvida al terminar la petición
 * (`ResetInterface`, worker de FrankenPHP).
 */
final class SuplantacionActual implements ResetInterface
{
    private ?ApiToken $token = null;

    public function fijar(?ApiToken $token): void
    {
        $this->token = $token?->esSuplantacion() ? $token : null;
    }

    public function token(): ?ApiToken
    {
        return $this->token;
    }

    /** Quién está suplantando; null si la petición es del propio usuario. */
    public function actor(): ?Usuario
    {
        return $this->token?->getSuplantadoPor();
    }

    public function activa(): bool
    {
        return $this->token !== null;
    }

    public function reset(): void
    {
        $this->token = null;
    }
}
