<?php

namespace App\Entity\Base;

use App\Enum\StatusType;
use App\Entity\Base\Interfaces\EstadoVentaInterface;

use App\Entity\Base\Traits\StatusTrait;
use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\DBAL\Types\Types;

#[ORM\MappedSuperclass]
class BoletoBase extends Base {

  use StatusTrait, TimestampableEntity;

  public const ASIENTO_CLASE_A = 'A';
  public const ASIENTO_CLASE_B = 'B';
}
