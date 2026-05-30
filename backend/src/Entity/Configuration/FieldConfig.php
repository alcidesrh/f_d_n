<?php

namespace App\Entity\Configuration;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Base\Traits\DataLoader;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\MappedSuperclass]
class FieldConfig {
  use DataLoader;
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  public ?int $id = null;

  #[ORM\Column(length: 255)]
  #[Groups(['read:dto'])]
  public string $field;

  #[ORM\Column(type: 'integer')]
  #[Groups(['read:dto'])]
  public int $position;

  #[ORM\Column(nullable: true)]
  #[Groups(['read:dto'])]
  public bool $visible = true;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['read:dto'])]
  public ?string $label = null;

  #[ORM\Column(type: Types::JSON, nullable: true)]
  #[Groups(['read:dto'])]
  public ?array $attrs = null;

  public function getId(): ?int {
    return $this->id;
  }

  public function getField(): string {
    return $this->field;
  }

  public function setField(string $field): self {
    $this->field = $field;
    return $this;
  }
  #[Groups(['read:dto'])]
  public function getName(): string {
    return $this->field;
  }
  public function getPosition(): int {
    return $this->position;
  }

  public function setPosition(int $position): self {
    $this->position = $position;
    return $this;
  }

  public function isVisible(): bool {
    return $this->visible;
  }

  public function setVisible(bool $visible): self {
    $this->visible = $visible;
    return $this;
  }
  public function getLabel(): ?string {
    return $this->label ?? $this->field;
  }

  public function setLabel(?string $label): self {
    $this->label = $label;
    return $this;
  }

  public function getAttrs(): ?array {
    return $this->attrs;
  }

  public function setAttrs(?array $attrs): self {
    $this->attrs = $attrs;
    return $this;
  }
}
