<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Attribute\ApiResourceNoPagination;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Repository\IconRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: IconRepository::class)]
#[ApiResourcePaginationPage(
    graphQlOperations: [
        new QueryCollection(
            // paginationEnabled: false,
            parameters: [
                'icon' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'icon'
                ),
                'name' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'name'
                ),
            ]
        )
    ]
)]
class Icon extends Base
{

    #[Groups(['read', 'write', 'icon:read', 'icon:write'])]
    #[ORM\Column(length: 50)]
    private ?string $icon = null;

    #[Groups(['read', 'write', 'icon:read', 'icon:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
