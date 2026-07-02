<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Attribute\ApiResourcePaginationPage;
use App\Repository\IconCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: IconCategoryRepository::class)]
#[ApiResourceNoPagination]
class IconCategory {

    #[Groups(['iconcategory:read', 'iconcategory:write'])]
    public ?int $totalIcons;

    #[Groups(['iconcategory:read', 'iconcategory:write'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['iconcategory:read', 'iconcategory:write'])]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Icon>
     */
    #[Groups(['iconcategory:read', 'iconcategory:write'])]
    #[ORM\ManyToMany(targetEntity: Icon::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $icons;


    public function __construct() {
        $this->icons = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): static {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Icon>
     */
    public function getIcons(): Collection {
        return $this->icons;
    }

    public function addIcon(Icon $icon): static {
        if (!$this->icons->contains($icon)) {
            $this->icons->add($icon);
        }

        return $this;
    }

    public function removeIcon(Icon $icon): static {
        $this->icons->removeElement($icon);

        return $this;
    }
}
