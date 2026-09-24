<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Base\Traits\LegacyTrait;
use App\Repository\LocalidadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalidadRepository::class)]
#[ApiResourcePaginationPage]
#[ApiFilter(OrderFilter::class, alias: 'order.filter', properties: ['id'], arguments: ['orderParameterName' => 'order'])]
class Localidad extends Base {

    use LegacyTrait;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\ManyToOne]
    private ?Nacion $nacion = null;

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static {
        $this->nombre = $nombre;

        return $this;
    }

    public function getNacion(): ?Nacion {
        return $this->nacion;
    }

    public function setNacion(?Nacion $nacion): static {
        $this->nacion = $nacion;

        return $this;
    }
}
