<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Suplantacion\ModoChat;
use Doctrine\ORM\Mapping as ORM;

/**
 * Configuración de la suplantación de usuarios (un administrador del sistema
 * entra como otro usuario): una sola fila; sin fila valen los valores por
 * defecto. Se lee con `App\Acceso\Suplantacion\AjustesSuplantacion` y se
 * edita en la administración (`GestionSuplantacion`); no es un recurso de la
 * API.
 */
#[ORM\Entity]
class ConfiguracionSuplantacion
{
    public const ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    /** Apagada, nadie puede suplantar (las suplantaciones abiertas siguen hasta vencer o salir). */
    #[ORM\Column(options: ["default" => true])]
    private bool $activa = true;

    /** Cuánto dura el token de suplantación, en minutos (no se renueva). */
    #[ORM\Column(options: ["default" => 60])]
    private int $duracionMinutos = 60;

    /** `ModoChat`: qué puede hacer en el chat y el correo del usuario mientras lo suplanta. */
    #[ORM\Column(length: 10, options: ["default" => "lectura"])]
    private string $chat = "lectura";

    /** Se le avisa al usuario (en su Bitácora del sistema del chat) que un administrador entró a su cuenta. */
    #[ORM\Column(options: ["default" => true])]
    private bool $avisarUsuario = true;

    /** Hay que escribir un motivo al empezar (queda en la bitácora). */
    #[ORM\Column(options: ["default" => true])]
    private bool $exigirMotivo = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $actualizadaEn = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $actualizadaPor = null;

    public function isActiva(): bool
    {
        return $this->activa;
    }

    public function setActiva(bool $activa): static
    {
        $this->activa = $activa;

        return $this;
    }

    public function getDuracionMinutos(): int
    {
        return $this->duracionMinutos;
    }

    public function setDuracionMinutos(int $minutos): static
    {
        $this->duracionMinutos = $minutos;

        return $this;
    }

    public function getChat(): ModoChat
    {
        return ModoChat::tryFrom($this->chat) ?? ModoChat::LECTURA;
    }

    public function setChat(ModoChat $chat): static
    {
        $this->chat = $chat->value;

        return $this;
    }

    public function isAvisarUsuario(): bool
    {
        return $this->avisarUsuario;
    }

    public function setAvisarUsuario(bool $avisar): static
    {
        $this->avisarUsuario = $avisar;

        return $this;
    }

    public function isExigirMotivo(): bool
    {
        return $this->exigirMotivo;
    }

    public function setExigirMotivo(bool $exigir): static
    {
        $this->exigirMotivo = $exigir;

        return $this;
    }

    public function getActualizadaEn(): ?\DateTimeImmutable
    {
        return $this->actualizadaEn;
    }

    public function getActualizadaPor(): ?Usuario
    {
        return $this->actualizadaPor;
    }

    public function actualizada(?Usuario $por, \DateTimeImmutable $en): static
    {
        $this->actualizadaPor = $por;
        $this->actualizadaEn = $en;

        return $this;
    }
}
