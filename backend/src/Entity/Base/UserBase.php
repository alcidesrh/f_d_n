<?php

namespace App\Entity\Base;

use App\Entity\Base\Traits\LegacyTrait;
use App\Entity\Base\Traits\PersonaTrait;
use App\Entity\Base\Traits\StatusTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class UserBase extends Base {
  use TimestampableEntity,
    StatusTrait,
    LegacyTrait,
    PersonaTrait;
}
