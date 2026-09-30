<?php

namespace App\Entity;

use App\Repository\MonitorDatosRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: MonitorDatosRepository::class)]
class MonitorDatos {

    use TimestampableEntity;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $visitas = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $session_id = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getVisitas(): ?string {
        return $this->visitas;
    }

    public function setVisitas(): static {
        $this->visitas++;

        return $this;
    }

    public function getIp(): ?string {
        return $this->ip;
    }

    public function setIp(?string $ip): static {
        $this->ip = $ip;

        return $this;
    }

    public function getSessionId(): ?string {
        return $this->session_id;
    }

    public function setSessionId(?string $session_id): static {
        $this->session_id = $session_id;

        return $this;
    }
}
