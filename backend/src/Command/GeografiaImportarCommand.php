<?php

declare(strict_types=1);

namespace App\Command;

use App\Geografia\ImportadorGeografia;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Regiones y ciudades para la dirección de facturación de la página web
 * (ADR-041), desde la base de la página anterior (dataset
 * countries-states-cities). La URL de esa base va en `--origen` o en
 * `GEOGRAFIA_ORIGEN_URL` (`.env.local`, nunca en git). Reemplaza el
 * catálogo: se puede correr las veces que haga falta.
 */
#[AsCommand(name: "app:geografia:importar", description: "Importa regiones y ciudades desde la base de la página anterior")]
final class GeografiaImportarCommand
{
    public function __construct(private readonly ImportadorGeografia $importador) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "URL de la base de la página anterior (postgres://…)")] ?string $origen = null,
    ): int {
        $url = $origen ?? ($_SERVER["GEOGRAFIA_ORIGEN_URL"] ?? $_ENV["GEOGRAFIA_ORIGEN_URL"] ?? null);
        if (!is_string($url) || $url === "") {
            $io->error("Indique la base de origen con --origen o GEOGRAFIA_ORIGEN_URL.");

            return 1;
        }
        $parametros = (new DsnParser(["postgres" => "pdo_pgsql", "postgresql" => "pdo_pgsql", "pgsql" => "pdo_pgsql"]))->parse($url);
        $conexion = DriverManager::getConnection($parametros);
        try {
            // Solo lectura en el origen: es la base en producción de la página anterior.
            $conexion->executeStatement("SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY");
            $total = $this->importador->importar($conexion);
        } finally {
            $conexion->close();
        }
        $io->success(sprintf("Regiones: %d, ciudades: %d", $total["regiones"], $total["ciudades"]));

        return 0;
    }
}
