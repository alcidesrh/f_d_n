<?php

declare(strict_types=1);

namespace App\Sincronizacion\Comando;

use App\Sincronizacion\Interruptor;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * El interruptor de la sincronización desde la consola (lo mismo que el
 * botón del indicador): `encender`, `apagar` o, sin argumento, el estado.
 */
#[AsCommand(name: "app:sincronizacion:interruptor", description: "Enciende, apaga o muestra la sincronización con el sistema anterior")]
final class InterruptorCommand
{
    public function __construct(private readonly Interruptor $interruptor) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: "encender | apagar (vacío: mostrar el estado)")] ?string $accion = null,
    ): int {
        if ($accion !== null) {
            if (!in_array($accion, ["encender", "apagar"], true)) {
                $io->error("Use `encender` o `apagar`.");

                return 2;
            }
            $aviso = $this->interruptor->cambiar($accion === "encender", "consola");
            if ($aviso !== null) {
                $io->warning($aviso);
            }
        }
        $estado = $this->interruptor->estado();
        $io->writeln(sprintf("Sincronización: <info>%s</info>", $estado["activo"] ? "ENCENDIDA" : "apagada"));
        $io->writeln(json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return 0;
    }
}
