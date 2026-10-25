<?php

declare(strict_types=1);

namespace App\Sincronizacion\Comando;

use App\Sincronizacion\Conciliador;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Compara un día de los dos sistemas y dice qué no cuadra (ADR-042). Con
 * `--reparar` arregla lo que no necesita decisión (ver `Conciliador`).
 */
#[AsCommand(name: "app:sincronizacion:conciliar", description: "Compara boletos y encomiendas de un día entre el sistema anterior y este")]
final class ConciliarCommand
{
    private const ETIQUETAS = [
        "boletosFaltanAqui" => "Boletos del legado que faltan aquí",
        "boletosHuerfanosAlla" => "Boletos FDN: huérfanos en el legado (venta de aquí no confirmada)",
        "boletosFaltanAlla" => "Boletos de aquí (en salidas del legado) que faltan allá",
        "estadosDistintos" => "Boletos con estado distinto",
        "encomiendasFaltanAqui" => "Encomiendas del legado que faltan aquí",
        "encomiendasFaltanAlla" => "Encomiendas de aquí que faltan allá",
    ];

    public function __construct(private readonly Conciliador $conciliador) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: "Día (AAAA-MM-DD); por defecto hoy")] ?string $dia = null,
        #[Option(description: "Reparar lo que no necesita decisión")] bool $reparar = false,
    ): int {
        $fecha = new \DateTimeImmutable($dia ?? "today");
        $diferencias = $this->conciliador->comparar($fecha);
        $io->title("Conciliación del " . $fecha->format("Y-m-d"));
        $total = 0;
        foreach (self::ETIQUETAS as $clave => $etiqueta) {
            $lista = $diferencias[$clave];
            $total += count($lista);
            $io->writeln(sprintf("%s: <info>%d</info>", $etiqueta, count($lista)));
            if ($lista !== [] && $io->isVerbose()) {
                $io->writeln("  " . json_encode(array_slice($lista, 0, 50), JSON_UNESCAPED_UNICODE));
            }
        }
        if ($total === 0) {
            $io->success("Los dos sistemas cuadran.");

            return 0;
        }
        if ($reparar) {
            $io->writeln(json_encode($this->conciliador->reparar($diferencias), JSON_UNESCAPED_UNICODE));
        }

        return 1;
    }
}
