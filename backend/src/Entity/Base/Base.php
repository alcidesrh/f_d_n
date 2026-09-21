<?php

namespace App\Entity\Base;

use App\Entity\Base\Traits\DataLoader;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
class Base
{
    use DataLoader;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected int $id;

    /**
     * Getter usado por getLabel() para cada clase concreta, resuelto una sola
     * vez por clase (evita reflexión/PropertyInfo en cada llamada).
     *
     * @var array<class-string, string|null>
     */
    private static array $labelMethodCache = [];

    public ?string $label = null;
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getLabel(): string
    {
        $class = static::class;

        $labelMethod = self::$labelMethodCache[
            $class
        ] ??= self::resolveLabelMethod($class);

        if ($labelMethod !== null && ($value = $this->$labelMethod())) {
            return $value;
        }

        return $this->getId() ?? $class;
    }

    private static function resolveLabelMethod(string $class): ?string
    {
        return match (true) {
            method_exists($class, "getNombre") => "getNombre",
            method_exists($class, "getName") => "getName",
            default => null,
        };
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
