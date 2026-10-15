<?php

declare(strict_types=1);

namespace App\Command;

use App\Ruta\KilometrosCarretera;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Calcula los km por carretera de cada ruta (OSRM, entre enclaves consecutivos)
 * y los guarda en `parada.km` y `ruta.kilometros`. Una ruta cuyo tramo no se
 * puede calcular (falta coordenada o el servicio no responde) no se toca.
 * Los tramos repetidos entre rutas (ida y regreso, rutas que comparten tramo)
 * se consultan una sola vez.
 */
#[AsCommand(name: "app:ruta:kilometros", description: "Calcula los km por carretera de las rutas (OSRM) y los guarda en parada.km y ruta.kilometros")]
final class RutaKilometrosCommand
{
    public function __construct(
        private readonly Connection $db,
        private readonly KilometrosCarretera $carretera,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Solo esta ruta (id)')] ?int $ruta = null,
        #[Option(description: 'No escribe en la base, solo informa')] bool $dryRun = false,
    ): int {
        $filas = $this->db->fetchAllAssociative(
            "SELECT r.id AS ruta_id, r.nombre AS ruta, p.id AS parada_id, e.nombre AS lugar, e.latitud, e.longitud
               FROM parada p
               JOIN ruta r ON r.id = p.ruta_id
               JOIN enclave e ON e.id = p.enclave_id
              WHERE CAST(:ruta AS INTEGER) IS NULL OR r.id = CAST(:ruta AS INTEGER)
              ORDER BY r.id, p.orden",
            ["ruta" => $ruta],
        );
        $porRuta = [];
        foreach ($filas as $fila) {
            $porRuta[(int) $fila["ruta_id"]][] = $fila;
        }

        $memo = [];
        $calculadas = 0;
        $sinCalcular = [];
        foreach ($porRuta as $rutaId => $paradas) {
            $tramos = [];
            for ($i = 1; $i < count($paradas); $i++) {
                $a = $paradas[$i - 1];
                $b = $paradas[$i];
                $clave = implode("|", [$a["latitud"], $a["longitud"], $b["latitud"], $b["longitud"]]);
                if (!array_key_exists($clave, $memo)) {
                    $memo[$clave] = $this->carretera->entre(
                        self::coordenada($a["latitud"]), self::coordenada($a["longitud"]),
                        self::coordenada($b["latitud"]), self::coordenada($b["longitud"]),
                    );
                }
                $tramos[] = $memo[$clave];
                $memo[$clave] === null && $sinCalcular[$rutaId][] = sprintf("%s → %s", $a["lugar"], $b["lugar"]);
            }

            $km = KilometrosCarretera::acumular($tramos);
            $nombre = $paradas[0]["ruta"];
            if ($km === null) {
                $io->warning(sprintf("%4d %s: sin calcular (%s)", $rutaId, $nombre, implode(", ", $sinCalcular[$rutaId])));
                continue;
            }
            $calculadas++;
            $io->writeln(sprintf("%4d %-50s %5d km", $rutaId, $nombre, end($km)));
            if ($dryRun) {
                continue;
            }
            $this->db->transactional(function () use ($paradas, $km, $rutaId): void {
                foreach ($paradas as $i => $parada) {
                    $this->db->update("parada", ["km" => $km[$i]], ["id" => $parada["parada_id"]]);
                }
                $this->db->update("ruta", ["kilometros" => end($km)], ["id" => $rutaId]);
            });
        }

        $io->success(sprintf(
            "%s: %d rutas con km, %d sin calcular, %d tramos distintos.",
            $dryRun ? "Simulación" : "Hecho",
            $calculadas,
            count($porRuta) - $calculadas,
            count($memo),
        ));

        return Command::SUCCESS;
    }

    private static function coordenada(?string $valor): ?float
    {
        return $valor !== null && is_numeric($valor) ? (float) $valor : null;
    }
}
