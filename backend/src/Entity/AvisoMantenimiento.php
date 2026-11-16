<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un servicio por vencer o vencido que ya se avisó por el chat (ADR-065):
 * se avisa una vez al entrar en cada estado, no cada madrugada mientras
 * siga. Lo escribe `App\Mantenimiento\AvisosMantenimiento` por DBAL y se
 * borra cuando el bus vuelve a estar al día.
 */
#[ORM\Entity(readOnly: true)]
class AvisoMantenimiento
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Bus $bus;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private PlanMantenimiento $plan;

    /** `por_vencer` o `vencido` (`ReglasMantenimiento`). */
    #[ORM\Column(length: 12)]
    private string $estado;

    #[ORM\Column]
    private \DateTimeImmutable $avisadoEn;

    private function __construct() {}

    public function getEstado(): string
    {
        return $this->estado;
    }
}
