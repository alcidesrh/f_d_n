<?php

namespace App\Entity\Base;

use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Entity\Usuario;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\DBAL\Types\Types;

#[ORM\MappedSuperclass]
class LogBase extends Base {

    use TimestampableEntityTrait;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\ManyToOne]
    private ?Usuario $user = null;


    public function getDescripcion(): ?string {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getUser(): ?Usuario {
        return $this->user;
    }

    public function setUser(?Usuario $user): static {
        $this->user1 = $user;

        return $this;
    }
}
