<?php

namespace App\Entity\Base\Traits;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait SluggableTrait {
    #[ORM\Column(length: 128, unique: true, nullable: true)]
    #[Gedmo\Slug(fields: ['nombre'])]
    protected string $slug;

    public function getSlug() {
        return $this->slug;
    }

    public function setSlug($slug) {
        return $this->slug =  $slug;
    }
}
