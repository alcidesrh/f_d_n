<?php

declare(strict_types=1);

namespace App\Acceso\Administracion;

/** Lo que se pidió sobre accesos no se puede hacer; el mensaje va al usuario. */
final class AccesoRechazado extends \DomainException
{
    public function __construct(
        string $mensaje,
        public readonly string $codigo = 'acceso_invalido',
        public readonly int $estadoHttp = 422,
    ) {
        parent::__construct($mensaje);
    }

    public static function permiso(string $mensaje): self
    {
        return new self($mensaje, 'permiso', 403);
    }

    public static function noExiste(string $mensaje): self
    {
        return new self($mensaje, 'no_encontrado', 404);
    }

    /** @return array{error: string, codigo: string} */
    public function toArray(): array
    {
        return ['error' => $this->getMessage(), 'codigo' => $this->codigo];
    }
}
