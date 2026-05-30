<?php

namespace App\Entity\Base;

use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ExcludeAttribute;
use App\Entity\Base\Traits\DataLoader;
use App\Services\Reflection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class Base {
    use DataLoader;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected int $id;

    #[ExcludeAttribute]
    public ?string $label = null;
    public function setId($id): self {
        $this->id = $id;
        return $this;
    }
    public function __construct() {
        $this->id = 9;
    }
    public function getId(): ?int {
        return $this->id;
    }
    public function getLabel() {

        $class = \get_class($this);

        $info = Reflection::extractor();

        $properties = $info->getProperties($class);
        if (!empty(\array_intersect($properties, ['nombre', 'name']))) {
            try {
                if ($nombre =  $this->getNombre()) {
                    return $nombre;
                }
            } catch (\Throwable $th) {
                try {
                    if ($name = $this->getName()) {
                        return $name;
                    }
                } catch (\Throwable $th) {
                    throw $th;
                }
            }
        }
        return $this->getId() ?? $class;
    }
    public function __toString() {

        return $this->getLabel();
    }
}
