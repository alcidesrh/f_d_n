<?php

declare(strict_types=1);

namespace App\Mantenimiento;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Suma al odómetro de cada bus lo que recorrió según el GPS (ADR-065).
 *
 * Cada bus recuerda hasta qué lectura ya se sumó (`bus.odometro_al`): la
 * corrida siguiente arranca en esa misma lectura, así el tramo que cruza de
 * una corrida a otra no se pierde ni se cuenta dos veces. Un bus que nunca
 * se sumó arranca `INICIO` atrás, no desde su primera lectura guardada. El
 * odómetro se corrige a mano en el bus: lo del GPS se sigue sumando encima.
 */
class Odometros
{
    public const INICIO = "-1 day";

    public function __construct(private readonly Connection $db) {}

    /** @return array{buses: int, km: int} buses con lecturas nuevas y kilómetros sumados */
    public function actualizar(\DateTimeImmutable $ahora): array
    {
        $inicio = $ahora->modify(self::INICIO)->getTimestamp();
        $pendientes = $this->db->fetchAllNumeric(<<<'SQL'
            SELECT b.id, COALESCE(CAST(EXTRACT(EPOCH FROM b.odometro_al) AS BIGINT), CAST(:inicio AS BIGINT))
            FROM bus b
            WHERE EXISTS (
                SELECT 1 FROM lectura_gps l
                WHERE l.bus_id = b.id AND l.instante > COALESCE(b.odometro_al, to_timestamp(CAST(:inicio AS BIGINT))))
            ORDER BY b.id
            SQL, ["inicio" => $inicio], ["inicio" => ParameterType::INTEGER]);

        $buses = 0;
        $total = 0;
        foreach ($pendientes as [$bus, $desde]) {
            $r = Kilometraje::de($this->lecturas((int) $bus, (int) $desde));
            if ($r["ultima"] === null) {
                continue;
            }
            $km = (int) round($r["km"]);
            $this->db->executeStatement(
                "UPDATE bus SET odometro_km = odometro_km + :km, odometro_al = to_timestamp(:al) WHERE id = :bus",
                ["km" => $km, "al" => $r["ultima"], "bus" => (int) $bus],
                ["km" => ParameterType::INTEGER, "al" => ParameterType::INTEGER, "bus" => ParameterType::INTEGER],
            );
            $buses++;
            $total += $km;
        }

        return ["buses" => $buses, "km" => $total];
    }

    /** @return \Generator<int, array{0: int, 1: float, 2: float}> */
    private function lecturas(int $bus, int $desde): \Generator
    {
        $filas = $this->db->iterateNumeric(<<<'SQL'
            SELECT CAST(EXTRACT(EPOCH FROM instante) AS BIGINT), lat, lng
            FROM lectura_gps
            WHERE bus_id = :bus AND instante >= to_timestamp(:desde)
            ORDER BY instante
            SQL, ["bus" => $bus, "desde" => $desde], ["bus" => ParameterType::INTEGER, "desde" => ParameterType::INTEGER]);
        foreach ($filas as $f) {
            yield [(int) $f[0], (float) $f[1], (float) $f[2]];
        }
    }
}
