<?php

declare(strict_types=1);

namespace App\Mantenimiento\Tarea;

use App\Mantenimiento\AvisosMantenimiento;
use App\Mantenimiento\EstadoFlota;
use App\Mantenimiento\Odometros;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RevisarMantenimientoHandler
{
    public function __construct(
        private readonly Odometros $odometros,
        private readonly EstadoFlota $estado,
        private readonly AvisosMantenimiento $avisos,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{buses: int, km: int, nuevos: int, avisados: int} */
    public function __invoke(RevisarMantenimiento $mensaje): array
    {
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now())->setTimezone(new \DateTimeZone("America/Guatemala"));
        $sumado = $this->odometros->actualizar($ahora);

        return $sumado + $this->avisos->avisar($this->estado->buses($ahora), $this->estado->planes(), $ahora);
    }
}
