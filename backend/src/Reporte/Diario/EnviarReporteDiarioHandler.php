<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Arma y entrega un reporte diario. La suscripción y el alcance se miran de
 * nuevo aquí (pudieron cambiar desde el reparto); sin nada que mandar (un
 * día sin movimiento), no se manda nada.
 */
#[AsMessageHandler]
final class EnviarReporteDiarioHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Suscripciones $suscripciones,
        private readonly GeneradorReportes $generador,
        private readonly EntregaReportes $entrega,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(EnviarReporteDiario $m): void
    {
        $reporte = ReporteProgramado::tryFrom($m->reporte);
        $usuario = $this->em->find(Usuario::class, $m->usuarioId);
        $dia = \DateTimeImmutable::createFromFormat("!Y-m-d", $m->dia);
        if ($reporte === null || $usuario === null || $dia === false) {
            return;
        }
        $vigente = $this->suscripciones->vigente($usuario, $reporte);
        if ($vigente === null) {
            return;
        }
        $entrega = $this->generador->generar($reporte, $dia, $vigente["celdas"]);
        if ($entrega === null) {
            $this->logger->info("Reporte {reporte} del {dia} para {usuario}: sin movimiento, no se envía.", ["reporte" => $reporte->value, "dia" => $m->dia, "usuario" => $usuario->getUsername()]);

            return;
        }
        $this->entrega->entregar($usuario, $vigente["suscripcion"], $reporte, $dia, $entrega);
    }
}
