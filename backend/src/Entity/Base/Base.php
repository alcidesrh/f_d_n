<?php

namespace App\Entity\Base;

use App\Entity\Base\Traits\DataLoader;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

#[ORM\MappedSuperclass]
class Base {
    use DataLoader;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected int $id;

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

        $extractor = [new ReflectionExtractor()];
        $info = new PropertyInfoExtractor(typeExtractors: $extractor, accessExtractors: $extractor, initializableExtractors: $extractor, listExtractors: $extractor);

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
