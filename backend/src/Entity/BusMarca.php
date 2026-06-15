<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourceNoPagination]
class BusMarca extends Base {


    #[ORM\Column(type: 'string', length: 20, unique: true)]
    private string $nombre;

    public function getNombre(): string {
        return $this->nombre;
    }
}
