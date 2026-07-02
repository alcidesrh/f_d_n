<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Repository\IconRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: IconRepository::class)]
#[ApiResourceNoPagination]
class Icon extends Base {

    #[Groups(['read', 'write', 'icon:read', 'icon:write', 'iconcategory:read', 'iconcategory:write'])]
    #[ORM\Column(length: 50)]
    private ?string $icon = null;

    #[Groups(['read', 'write', 'icon:read', 'icon:write', 'iconcategory:read', 'iconcategory:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[Groups(['read', 'icon:read'])]
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $codepoint = null;

    #[Groups(['read', 'icon:read'])]
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $popularity = null;

    #[Groups(['read', 'icon:read'])]
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags = null;

    public function getIcon(): ?string {
        return $this->icon;
    }

    public function setIcon(string $icon): static {
        $this->icon = $icon;

        return $this;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(?string $name): static {
        $this->name = $name;

        return $this;
    }

    public function getCodepoint(): ?int {
        return $this->codepoint;
    }

    public function setCodepoint(?int $codepoint): static {
        $this->codepoint = $codepoint;

        return $this;
    }

    public function getPopularity(): ?int {
        return $this->popularity;
    }

    public function setPopularity(?int $popularity): static {
        $this->popularity = $popularity;

        return $this;
    }

    public function getTags(): ?array {
        return $this->tags;
    }

    public function setTags(?array $tags): static {
        $this->tags = $tags;

        return $this;
    }
}
