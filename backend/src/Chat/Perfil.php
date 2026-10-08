<?php

declare(strict_types=1);

namespace App\Chat;

use App\Cuenta\FotoPerfil;
use App\Entity\Usuario;

/**
 * Lo que el chat sabe de un usuario para decidir con quién habla y cómo se
 * presenta: su ámbito (administración, estación o agencia) y su lugar.
 */
final class Perfil
{
    public const ADMINISTRACION = "administracion";
    public const ESTACION = "estacion";
    public const AGENCIA = "agencia";

    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly ?int $agencia = null,
        public readonly ?int $estacion = null,
        public readonly ?string $lugar = null,
        /** URL firmada de la foto de perfil, relativa a la API (`FotoPerfil::url`). */
        public readonly ?string $foto = null,
    ) {}

    public static function de(Usuario $u, ?FotoPerfil $fotos = null): self
    {
        $nombre = trim(($u->getNombre() ?? "") . " " . ($u->getApellido() ?? ""));
        $agencia = $u->getAgencia();
        $estacion = $u->getEstacion();

        return new self(
            (int) $u->getId(),
            $nombre !== "" ? $nombre : $u->getUsername(),
            $agencia?->getId(),
            $estacion?->getId(),
            $agencia?->getNombre() ?? $estacion?->getNombre() ?? $u->getEmpresa()?->getNombre(),
            $fotos?->url($u),
        );
    }

    public function ambito(): string
    {
        return match (true) {
            $this->agencia !== null => self::AGENCIA,
            $this->estacion !== null => self::ESTACION,
            default => self::ADMINISTRACION,
        };
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            "id" => $this->id,
            "nombre" => $this->nombre,
            "ambito" => $this->ambito(),
            "lugar" => $this->lugar,
            "foto" => $this->foto,
        ];
    }
}
