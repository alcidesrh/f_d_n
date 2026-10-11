<?php

declare(strict_types=1);

namespace App\Ruta;

use App\Entity\Enum\EstadoBoletoAsiento;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Fija el horario de las salidas (`salida_parada`) a partir de su ruta, su
 * fecha y la clase de su bus (ADR-030). Escribe con DBAL para que lo usen
 * igual la aplicación y la migración, en la transacción de quien llama.
 *
 * Una salida con boletos vivos o reservas conserva sus paradas (un boleto
 * nunca cambia de tramo): solo se recalculan las horas de las que siguen en
 * la ruta. Sin boletos, el horario se rehace entero con la ruta actual.
 */
final class Horarios
{
    public function __construct(private readonly Connection $db) {}

    public function fijar(int ...$salidaIds): void
    {
        if ($salidaIds === []) {
            return;
        }
        $salidas = $this->db->fetchAllAssociative(
            "SELECT s.id, s.fecha, s.ruta_id, b.clase_id,
                    EXISTS (SELECT 1 FROM boleto_asiento ba WHERE ba.salida_id = s.id AND ba.estado NOT IN (:libres))
                    OR EXISTS (SELECT 1 FROM reserva_asiento r WHERE r.salida_id = s.id) AS ocupada
               FROM salida s LEFT JOIN bus b ON b.id = s.bus_id
              WHERE s.id IN (:ids)",
            ["ids" => $salidaIds, "libres" => [EstadoBoletoAsiento::ANULADO->value, EstadoBoletoAsiento::REASIGNADO->value]],
            ["ids" => ArrayParameterType::INTEGER, "libres" => ArrayParameterType::STRING],
        );
        $paradas = $this->paradas(array_values(array_unique(array_filter(array_column($salidas, "ruta_id")))));

        foreach ($salidas as $s) {
            $id = (int) $s["id"];
            $deRuta = $s["ruta_id"] !== null ? ($paradas[(int) $s["ruta_id"]] ?? []) : [];
            $clase = $s["clase_id"] !== null ? (int) $s["clase_id"] : null;
            $lista = array_map(static fn(array $p) => [
                "orden" => $p["orden"],
                "enclave" => $p["enclave"],
                "minutos" => $clase !== null && isset($p["porClase"][$clase]) ? $p["porClase"][$clase] : $p["minutos"],
                "subir" => $p["subir"],
                "bajar" => $p["bajar"],
            ], $deRuta);
            $fecha = new \DateTimeImmutable((string) $s["fecha"]);

            if ((bool) $s["ocupada"] && $this->tieneHorario($id)) {
                $this->actualizarHoras($id, $fecha, $lista);
            } else {
                $this->reemplazar($id, $lista === [] ? [] : Horario::calcular($fecha, $lista));
            }
        }
    }

    /**
     * @param list<int> $rutaIds
     *
     * @return array<int, list<array{orden: int, enclave: int, minutos: ?int, porClase: array<int, int>, subir: bool, bajar: bool}>>
     */
    private function paradas(array $rutaIds): array
    {
        if ($rutaIds === []) {
            return [];
        }
        $filas = $this->db->fetchAllAssociative(
            "SELECT p.id, p.ruta_id, p.orden, p.enclave_id, p.minutos, p.permite_subir, p.permite_bajar
               FROM parada p WHERE p.ruta_id IN (:r) ORDER BY p.ruta_id, p.orden",
            ["r" => $rutaIds],
            ["r" => ArrayParameterType::INTEGER],
        );
        $porClase = [];
        foreach ($this->db->fetchAllAssociative(
            "SELECT t.parada_id, t.bus_clase_id, t.minutos FROM parada_tiempo t JOIN parada p ON p.id = t.parada_id WHERE p.ruta_id IN (:r)",
            ["r" => $rutaIds],
            ["r" => ArrayParameterType::INTEGER],
        ) as $t) {
            $porClase[(int) $t["parada_id"]][(int) $t["bus_clase_id"]] = (int) $t["minutos"];
        }
        $porRuta = [];
        foreach ($filas as $f) {
            $porRuta[(int) $f["ruta_id"]][] = [
                "orden" => (int) $f["orden"],
                "enclave" => (int) $f["enclave_id"],
                "minutos" => $f["minutos"] !== null ? (int) $f["minutos"] : null,
                "porClase" => $porClase[(int) $f["id"]] ?? [],
                "subir" => (bool) $f["permite_subir"],
                "bajar" => (bool) $f["permite_bajar"],
            ];
        }

        return $porRuta;
    }

    private function tieneHorario(int $salidaId): bool
    {
        return $this->db->fetchOne("SELECT 1 FROM salida_parada WHERE salida_id = :s LIMIT 1", ["s" => $salidaId]) !== false;
    }

    /**
     * @param list<array{orden: int, enclave: int, hora: \DateTimeImmutable, precision: \App\Entity\Enum\PrecisionHora, subir: bool, bajar: bool}> $horario
     */
    private function reemplazar(int $salidaId, array $horario): void
    {
        $this->db->executeStatement("DELETE FROM salida_parada WHERE salida_id = :s", ["s" => $salidaId]);
        foreach ($horario as $h) {
            $this->db->insert("salida_parada", [
                "salida_id" => $salidaId,
                "orden" => $h["orden"],
                "enclave_id" => $h["enclave"],
                "hora" => $h["hora"]->format("Y-m-d H:i:s"),
                "precision" => $h["precision"]->value,
                "permite_subir" => $h["subir"] ? "true" : "false",
                "permite_bajar" => $h["bajar"] ? "true" : "false",
            ]);
        }
    }

    /**
     * Conserva las paradas de la salida y recalcula las horas de las que la
     * ruta todavía tiene (por enclave); las demás se quedan como estaban.
     *
     * @param list<array{orden: int, enclave: int, minutos: ?int, subir: bool, bajar: bool}> $ruta
     */
    private function actualizarHoras(int $salidaId, \DateTimeImmutable $fecha, array $ruta): void
    {
        $minutos = array_column($ruta, "minutos", "enclave");
        $actual = $this->db->fetchAllAssociative(
            "SELECT orden, enclave_id, permite_subir, permite_bajar FROM salida_parada WHERE salida_id = :s ORDER BY orden",
            ["s" => $salidaId],
        );
        $lista = array_map(static fn(array $a) => [
            "orden" => (int) $a["orden"],
            "enclave" => (int) $a["enclave_id"],
            "minutos" => $minutos[(int) $a["enclave_id"]] ?? null,
            "subir" => (bool) $a["permite_subir"],
            "bajar" => (bool) $a["permite_bajar"],
        ], $actual);
        foreach (Horario::calcular($fecha, $lista) as $h) {
            $this->db->executeStatement(
                "UPDATE salida_parada SET hora = :hora, precision = :p WHERE salida_id = :s AND orden = :o",
                ["hora" => $h["hora"]->format("Y-m-d H:i:s"), "p" => $h["precision"]->value, "s" => $salidaId, "o" => $h["orden"]],
            );
        }
    }
}
