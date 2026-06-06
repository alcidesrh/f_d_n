<?php

namespace App\Entity\Base\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

trait LegacyTrait {
  #[ORM\Column(nullable: true, unique: true)]
  #[Ignore]
  protected ?string $legacyId = null;

  #[Ignore]
  public function getLegacyId(): ?string {
    return $this->legacyId;
  }

  public function setLegacyId(?string $legacyId): ?string {
    return $this->legacyId = $legacyId;
  }
}
