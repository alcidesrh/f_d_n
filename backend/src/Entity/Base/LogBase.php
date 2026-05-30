<?php

namespace App\Entity\Base;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\DBAL\Types\Types;

#[ORM\MappedSuperclass]
class LogBase extends Base
{

    use TimestampableEntity;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\ManyToOne]
    private ?User $user = null;


    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user1 = $user;

        return $this;
    }
}
