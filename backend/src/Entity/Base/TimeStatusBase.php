<?php

namespace App\Entity\Base;


use App\Entity\Base\Traits\StatusTrait;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class TimeStatusBase extends Base {

  use TimestampableEntity, StatusTrait;
}
