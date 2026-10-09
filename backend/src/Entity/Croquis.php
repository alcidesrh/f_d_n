<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Molde de una distribución de asientos (ADR-027): la forma que comparten
 * los buses con el mismo croquis (`App\Croquis\Croquis::firma`). Solo sirve
 * para que, al crear una salida, el usuario elija la distribución y luego un
 * bus compatible. No tiene asientos propios (son de cada bus), no se edita y
 * no hay dos iguales; solo `Bus` se relaciona con él. Lo mantiene
 * `App\Croquis\Moldes`; no se publica en GraphQL.
 */
#[ORM\Entity]
class Croquis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Huella de la distribución: única. */
    #[ORM\Column(length: 40, unique: true)]
    private string $firma;

    #[ORM\Column(type: "smallint")]
    private int $asientos;

    /** Asientos clase B (los demás son A). */
    #[ORM\Column(name: "asientos_b", type: "smallint")]
    private int $asientosB;

    #[ORM\Column(type: "smallint")]
    private int $plantas;

    /**
     * Elementos sin ids, en orden de lectura (forma de `ElementoCroquis::toArray`).
     *
     * @var list<array<string, int|string|null>>
     */
    #[ORM\Column(type: "json")]
    private array $elementos;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    private function __construct() {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirma(): string
    {
        return $this->firma;
    }

    public function getAsientos(): int
    {
        return $this->asientos;
    }

    public function getAsientosB(): int
    {
        return $this->asientosB;
    }

    public function getPlantas(): int
    {
        return $this->plantas;
    }

    /** @return list<array<string, int|string|null>> */
    public function getElementos(): array
    {
        return $this->elementos;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }
}
