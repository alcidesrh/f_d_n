<?php

namespace App\Entity;

use App\Repository\ErrorFdnRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ErrorFdnRepository::class)]
class ErrorFdn {

    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private $error;

    #[ORM\ManyToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'set null')]
    private ?Reservacion $reservacion = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getError() {
        return $this->error;
    }

    public function setError($error) {
        $this->error = \is_array($error) ? \json_encode($error) : $error;
        return $this;
    }

    public function getReservacion() {
        return $this->reservacion;
    }

    public function setReservacion($reservacion) {
        $this->reservacion = $reservacion;
        return $this;
    }
}
