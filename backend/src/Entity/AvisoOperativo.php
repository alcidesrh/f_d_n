<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Una alerta del tablero que ya se avisó hoy (ADR-044): la vigilancia de la
 * operación avisa cada alerta una vez por día y ámbito (`e:<empresa>` o
 * `todas`), no cada media hora mientras siga. La escribe
 * `App\Vigilancia\VigilanciaOperativa` por DBAL; se borran a la semana.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\UniqueConstraint(name: "uq_aviso_operativo", columns: ["ambito", "codigo", "dia"])]
class AvisoOperativo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $ambito;

    /** `codigo` de la alerta del tablero (`facturas_pendientes`, `salidas_sin_cerrar`…). */
    #[ORM\Column(length: 40)]
    private string $codigo;

    #[ORM\Column(type: "date_immutable")]
    private \DateTimeImmutable $dia;

    #[ORM\Column]
    private \DateTimeImmutable $avisadoEn;

    private function __construct() {}

    public function getId(): ?int
    {
        return $this->id;
    }
}
