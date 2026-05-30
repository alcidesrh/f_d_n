<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Attribute\ApiResourcePaginationPage;
use App\Attribute\CollectionMetadataAttribute;
use App\Attribute\FormMetadataAttribute;
use App\Entity\Base\Base;
use App\Entity\Base\Traits\LegacyTrait;
use App\Filter\OrFilter;
use App\Repository\LocalidadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalidadRepository::class)]
#[ApiResourcePaginationPage]

#[ApiFilter(OrFilter::class, alias: 'or.filter', properties: ['id', 'nombre'], arguments: ['searchFilterProperties' => ['id' => SearchFilterInterface::STRATEGY_EXACT,  'nombre' => SearchFilterInterface::STRATEGY_IPARTIAL]])]

#[ApiFilter(OrderFilter::class, alias: 'order.filter', properties: ['id'], arguments: ['orderParameterName' => 'order'])]

#[CollectionMetadataAttribute(
    class: 'col-wraper',
    props: [
        ['name' => 'id', 'label' => 'Id', 'sort' => true, 'filter' => true,],
        ['name' => 'nombre', 'label' => 'Nombre', 'sort' => true, 'filter' => true],
        ['name' => 'nacion', 'label' => 'Pais', 'sort' => false],
    ]
)]

class Localidad extends Base {

    use LegacyTrait;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[FormMetadataAttribute(merge: ['options' => '$naciones', 'label' => 'país'])]
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
