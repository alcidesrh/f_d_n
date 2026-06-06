<?php

namespace App\Entity\Base;

use App\Enum\StatusType;
use App\Entity\Base\Interfaces\EstadoVentaInterface;

use App\Entity\Base\Traits\StatusTrait;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class BoletoBase extends Base {

  use StatusTrait, TimestampableEntityTrait;
}
