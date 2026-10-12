<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Enum\EstadoBoletoAsiento;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Salidas que pasan por una parada y después por otra (ADR-030), con la
 * **hora en la parada de subida** dentro del rango pedido: una salida que
 * arranca a las 22:00 y pasa por la estación a la 01:00 es del día
 * siguiente. Busca sobre el horario de las salidas (`salida_parada`) por
 * índice; taquilla y página usan la misma consulta.
 */
final class BuscadorSalidas
{
    public function __construct(private readonly Connection $db) {}

    /**
     * Sin origen, la parada de subida es la primera de cada salida. Nunca se
     * sube en la última parada. Con destino, solo las que bajan ahí después.
     * `$hasta` es exclusivo.
     *
     * @return list<array{salida: int, sube: int, baja: ?int, hora: \DateTimeImmutable}> por hora de subida
     */
    public function pasanPor(\DateTimeImmutable $desde, \DateTimeImmutable $hasta, ?int $origenId, ?int $destinoId = null): array
    {
        $params = ["desde" => $desde->format("Y-m-d H:i:s"), "hasta" => $hasta->format("Y-m-d H:i:s")];
        $sube = $origenId === null ? "o.orden = 0" : "o.enclave_id = :origen";
        $origenId !== null && $params["origen"] = $origenId;
        $baja = "NULL::smallint";
        $join = "";
        if ($destinoId !== null) {
            $baja = "d.orden";
            $join = "JOIN salida_parada d ON d.salida_id = o.salida_id AND d.enclave_id = :destino AND d.orden > o.orden AND d.permite_bajar";
            $params["destino"] = $destinoId;
        }

        return array_map(static fn(array $f) => [
            "salida" => (int) $f["salida_id"],
            "sube" => (int) $f["sube"],
            "baja" => $f["baja"] !== null ? (int) $f["baja"] : null,
            "hora" => new \DateTimeImmutable((string) $f["hora"]),
        ], $this->db->fetchAllAssociative(
            "SELECT o.salida_id, o.orden AS sube, {$baja} AS baja, o.hora
               FROM salida_parada o
               {$join}
              WHERE {$sube} AND o.permite_subir
                AND o.hora >= :desde AND o.hora < :hasta
                AND EXISTS (SELECT 1 FROM salida_parada u WHERE u.salida_id = o.salida_id AND u.orden > o.orden)
              ORDER BY o.hora, o.salida_id",
            $params,
        ));
    }

    /**
     * Asientos con boleto vivo que se solapa con el tramo de cada salida.
     *
     * @param list<array{salida: int, sube: int, baja: int}> $tramos
     *
     * @return array<int, int> por id de salida (las que no tienen ninguno no aparecen)
     */
    public function vendidosEnTramos(array $tramos): array
    {
        if ($tramos === []) {
            return [];
        }
        $valores = [];
        $params = ["libres" => EstadoBoletoAsiento::liberanAsiento()];
        foreach ($tramos as $i => $t) {
            $valores[] = "(:s{$i}::int, :a{$i}::int, :b{$i}::int)";
            $params["s{$i}"] = $t["salida"];
            $params["a{$i}"] = $t["sube"];
            $params["b{$i}"] = $t["baja"];
        }

        return array_map("intval", $this->db->fetchAllKeyValue(
            "SELECT b.salida_id, COUNT(DISTINCT b.asiento_id)
               FROM boleto_asiento b
               JOIN (VALUES " . implode(", ", $valores) . ") AS t(salida, sube, baja)
                 ON t.salida = b.salida_id AND b.orden_sube < t.baja AND t.sube < b.orden_baja
              WHERE b.estado NOT IN (:libres)
              GROUP BY b.salida_id",
            $params,
            ["libres" => ArrayParameterType::STRING],
        ));
    }
}
