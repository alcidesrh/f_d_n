<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Latido de una tarea del programador (ADR-044): una fila por `Tarea`, con
 * su última corrida. La escribe `App\Programador\RegistroTareas` por DBAL al
 * empezar y al terminar cada corrida (también si falla); el tablero avisa si
 * alguna lleva más de su tolerancia sin una corrida buena.
 */
#[ORM\Entity(readOnly: true)]
class EstadoTarea
{
    public const OK = 'ok';
    public const ERROR = 'error';
    /** Otra corrida de la misma tarea seguía (cerrojo tomado): esta no hizo nada. */
    public const OMITIDA = 'omitida';

    /** `App\Programador\Tarea` */
    #[ORM\Id]
    #[ORM\Column(length: 40)]
    private string $codigo;

    /** La primera vez que empezó: desde cuándo el programador la corre. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $primerInicio = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoInicio = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoFin = null;

    /** Fin de la última corrida que terminó bien. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ultimoExito = null;

    #[ORM\Column(nullable: true)]
    private ?int $duracionMs = null;

    /** `ok`, `error` u `omitida`; null mientras corre la primera. */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $resultado = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    /** Lo que hizo la última corrida buena (lo devuelve su handler). */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $resumen = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $corridas = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $fallosSeguidos = 0;

    private function __construct() {}

    public function getCodigo(): string
    {
        return $this->codigo;
    }
}
