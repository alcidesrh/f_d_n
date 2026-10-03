<?php

declare(strict_types=1);

namespace App\Venta;

use Money\Money;

/** Una `BoletoTarifa` reducida a lo que decide su especificidad. */
final readonly class CandidatoTarifa
{
    public function __construct(
        public int $id,
        public Money $precio,
        public string $clase,
        public \DateTimeImmutable $vigenteDesde,
        public ?int $empresaId = null,
        public ?int $trayectoId = null,
        /** `H:i` */
        public ?string $horaDesde = null,
        /** `H:i` */
        public ?string $horaHasta = null,
        public ?int $busClaseId = null,
        public ?int $busId = null,
    ) {}

    /** ¿Una salida a esa hora (`H:i`) cae en el horario? Si desde > hasta cruza la medianoche. */
    public function enHorario(string $hora): bool
    {
        if ($this->horaDesde !== null && $this->horaHasta !== null && $this->horaDesde > $this->horaHasta) {
            return $hora >= $this->horaDesde || $hora <= $this->horaHasta;
        }

        return ($this->horaDesde === null || $hora >= $this->horaDesde)
            && ($this->horaHasta === null || $hora <= $this->horaHasta);
    }
}
