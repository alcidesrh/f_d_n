<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Base\Traits\LegacyTrait;
use App\Repository\NacionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NacionRepository::class)]
#[ORM\Table(name: 'pais')]

#[ApiResourcePaginationPage]
class Nacion extends Base {

    use LegacyTrait;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static {
        $this->nombre = $nombre;

        return $this;
    }
}
