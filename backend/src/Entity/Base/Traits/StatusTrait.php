<?php

namespace App\Entity\Base\Traits;

use App\Entity\Status;
use Doctrine\ORM\Mapping as ORM;

trait StatusTrait {


    #[ORM\ManyToOne]
    protected ?Status $status = null;

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function setStatus(?Status $status): static {
        $this->status = $status;

        return $this;
    }
}
