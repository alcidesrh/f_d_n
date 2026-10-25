<?php

declare(strict_types=1);

namespace App\Sincronizacion\Comando;

use App\Sincronizacion\Ciclo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * El ciclo de la sincronización con el legado (ADR-042). Sin `--cada`, una
 * vuelta (cron); con `--cada=60`, se queda corriendo (el worker). Con el
 * interruptor apagado no hace nada, pero sigue esperando a que se encienda.
 */
#[AsCommand(name: "app:sincronizar", description: "Sincroniza con el sistema anterior: trae sus cambios y lleva los de aquí")]
final class SincronizarCommand
{
    public function __construct(
        private readonly Ciclo $ciclo,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "Repetir cada N segundos (0 = una sola vez)")] int $cada = 0,
        #[Option(description: "Con --cada, terminar tras N segundos (0 = nunca); el supervisor lo reinicia")] int $durante = 3600,
    ): int {
        $fin = $durante > 0 ? time() + $durante : PHP_INT_MAX;
        do {
            $inicio = time();
            try {
                $resumen = $this->ciclo->correr();
                if ($resumen === null) {
                    $cada === 0 && $io->writeln("La sincronización está apagada.");
                } elseif ($cada === 0 || self::hayAlgo($resumen)) {
                    $io->writeln(sprintf("[%s] %s", date("H:i:s"), json_encode($resumen, JSON_UNESCAPED_UNICODE)));
                }
            } catch (\Throwable $e) {
                $io->error($e->getMessage());
                if ($cada === 0) {
                    return 1;
                }
            }
            $this->em->clear();
            if ($cada > 0) {
                sleep(max(1, $cada - (time() - $inicio)));
            }
        } while ($cada > 0 && time() < $fin);

        return 0;
    }

    /** @param array<string, mixed> $resumen */
    private static function hayAlgo(array $resumen): bool
    {
        foreach ($resumen as $parte) {
            if (is_array($parte) && array_filter($parte) !== []) {
                return true;
            }
        }

        return false;
    }
}
