<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EnviarReportesDiariosHandler
{
    public function __construct(
        private readonly Suscripciones $suscripciones,
        private readonly MessageBusInterface $bus,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array<string, int> destinatarios por reporte */
    public function __invoke(EnviarReportesDiarios $mensaje): array
    {
        $ayer = \DateTimeImmutable::createFromInterface($this->reloj->now())->setTime(0, 0)->modify("-1 day")->format("Y-m-d");
        $resumen = [];
        foreach (ReporteProgramado::cases() as $reporte) {
            if (!$reporte->diario()) {
                continue;
            }
            $destinatarios = $this->suscripciones->destinatarios($reporte);
            foreach ($destinatarios as ["usuario" => $u]) {
                $this->bus->dispatch(new EnviarReporteDiario((int) $u->getId(), $reporte->value, $ayer));
            }
            $resumen[$reporte->value] = count($destinatarios);
        }

        return $resumen;
    }
}
