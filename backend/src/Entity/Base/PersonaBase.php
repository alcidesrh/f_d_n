<?php

namespace App\Entity\Base;

use App\Entity\Base\Traits\PersonaTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class PersonaBase extends TimeLegacyStatusBase {
  use PersonaTrait;
}
