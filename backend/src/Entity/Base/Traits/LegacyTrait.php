<?php

namespace App\Entity\Base\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

trait LegacyTrait {
  #[ORM\Column(nullable: true)]
  #[Ignore]
  protected ?int $legacyId = null;

  #[Ignore]
  public function getLegacyId(): ?int {
    return $this->legacyId;
  }

  public function setLegacyId(int $legacyId): ?int {
    return $this->legacyId = $legacyId;
  }
}
