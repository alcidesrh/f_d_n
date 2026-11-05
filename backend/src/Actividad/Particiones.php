<?php

declare(strict_types=1);

namespace App\Actividad;

use Doctrine\DBAL\Connection;

/**
 * Particiones mensuales de `actividad` (`actividad_AAAA_MM`, en hora de
 * Guatemala) y su retención. `actividad_otros` (la partición por defecto)
 * recibe lo que llegue sin partición; al crear la del mes, sus filas se
 * mudan ahí.
 */
class Particiones
{
    public const ZONA = "America/Guatemala";
    /** Meses por delante que quedan creados. */
    public const ADELANTE = 2;
    private const OTROS = "actividad_otros";
    private const LOTE_EXPORTACION = 5000;

    public function __construct(private readonly Connection $db) {}

    public static function nombre(\DateTimeInterface $mes): string
    {
        return "actividad_" . self::inicioDeMes($mes)->format("Y_m");
    }

    public static function inicioDeMes(\DateTimeInterface $fecha): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($fecha)->setTimezone(new \DateTimeZone(self::ZONA))->modify("first day of this month")->setTime(0, 0);
    }

    /**
     * Las del mes de `$ahora` y las `ADELANTE` siguientes.
     *
     * @return list<string> las que creó
     */
    public function asegurar(\DateTimeInterface $ahora): array
    {
        $existentes = $this->existentes();
        $creadas = [];
        $mes = self::inicioDeMes($ahora);
        for ($i = 0; $i <= self::ADELANTE; ++$i) {
            $desde = $mes->modify("+{$i} months");
            $nombre = self::nombre($desde);
            if (in_array($nombre, $existentes, true)) {
                continue;
            }
            $this->crear($nombre, $desde, $desde->modify("+1 month"));
            $creadas[] = $nombre;
        }

        return $creadas;
    }

    /**
     * Borra las particiones que terminan antes de `$antesDe` (y lo de
     * `actividad_otros` anterior a esa fecha). Si `$exportarEn` no es null,
     * antes deja cada una en `<dir>/actividad_AAAA_MM.csv.gz`.
     *
     * @return list<string> las que borró
     */
    public function purgar(\DateTimeInterface $antesDe, ?string $exportarEn): array
    {
        $corte = self::inicioDeMes($antesDe);
        $borradas = [];
        foreach ($this->existentes() as $nombre) {
            $mes = \DateTimeImmutable::createFromFormat("!Y_m", substr($nombre, strlen("actividad_")), new \DateTimeZone(self::ZONA));
            if ($mes === false || $mes->modify("+1 month") > $corte) {
                continue;
            }
            if ($exportarEn !== null) {
                $this->exportar($nombre, $exportarEn);
            }
            $this->db->executeStatement(sprintf("DROP TABLE %s", $this->db->quoteIdentifier($nombre)));
            $borradas[] = $nombre;
        }
        $this->db->executeStatement(
            sprintf("DELETE FROM %s WHERE fecha < :corte", self::OTROS),
            ["corte" => $corte->format("Y-m-d H:i:sP")],
        );

        return $borradas;
    }

    /** @return list<string> las particiones mensuales que existen, de la más vieja a la más nueva */
    public function existentes(): array
    {
        $nombres = $this->db->fetchFirstColumn(<<<'SQL'
            SELECT c.relname FROM pg_inherits i
              JOIN pg_class c ON c.oid = i.inhrelid
              JOIN pg_class p ON p.oid = i.inhparent
             WHERE p.relname = 'actividad' AND c.relname ~ '^actividad_[0-9]{4}_[0-9]{2}$'
             ORDER BY c.relname
            SQL);

        return array_map("strval", $nombres);
    }

    /** Crea la partición; si `actividad_otros` tiene filas de ese mes, las muda. */
    private function crear(string $nombre, \DateTimeImmutable $desde, \DateTimeImmutable $hasta): void
    {
        $tabla = $this->db->quoteIdentifier($nombre);
        $rango = ["desde" => $desde->format("Y-m-d H:i:sP"), "hasta" => $hasta->format("Y-m-d H:i:sP")];
        $this->db->transactional(function (Connection $db) use ($tabla, $rango): void {
            $db->executeStatement(sprintf("CREATE TABLE %s (LIKE actividad INCLUDING DEFAULTS INCLUDING CONSTRAINTS)", $tabla));
            $db->executeStatement(
                sprintf("WITH m AS (DELETE FROM %s WHERE fecha >= :desde AND fecha < :hasta RETURNING *) INSERT INTO %s SELECT * FROM m", self::OTROS, $tabla),
                $rango,
            );
            $db->executeStatement(sprintf(
                "ALTER TABLE actividad ATTACH PARTITION %s FOR VALUES FROM (%s) TO (%s)",
                $tabla,
                $db->quote($rango["desde"]),
                $db->quote($rango["hasta"]),
            ));
        });
    }

    /** CSV comprimido, por lotes (una partición puede pesar gigas). */
    private function exportar(string $nombre, string $directorio): void
    {
        if (!is_dir($directorio) && !mkdir($directorio, 0o770, true) && !is_dir($directorio)) {
            throw new \RuntimeException(sprintf("No se pudo crear %s para exportar la bitácora.", $directorio));
        }
        $archivo = sprintf("%s/%s.csv.gz", rtrim($directorio, "/"), $nombre);
        $gz = gzopen($archivo . ".parcial", "wb6");
        if ($gz === false) {
            throw new \RuntimeException(sprintf("No se pudo escribir %s.", $archivo));
        }
        try {
            $tabla = $this->db->quoteIdentifier($nombre);
            $ultimo = 0;
            $encabezado = true;
            do {
                $filas = $this->db->fetchAllAssociative(sprintf("SELECT * FROM %s WHERE id > :ultimo ORDER BY id LIMIT %d", $tabla, self::LOTE_EXPORTACION), ["ultimo" => $ultimo]);
                foreach ($filas as $fila) {
                    if ($encabezado) {
                        gzwrite($gz, self::csv(array_keys($fila)));
                        $encabezado = false;
                    }
                    gzwrite($gz, self::csv(array_values($fila)));
                    $ultimo = (int) $fila["id"];
                }
            } while (count($filas) === self::LOTE_EXPORTACION);
        } finally {
            gzclose($gz);
        }
        rename($archivo . ".parcial", $archivo);
    }

    /** @param list<mixed> $valores */
    private static function csv(array $valores): string
    {
        return implode(",", array_map(static function (mixed $v): string {
            if ($v === null) {
                return "";
            }
            $s = (string) $v;

            return preg_match('/[",\r\n]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
        }, $valores)) . "\n";
    }
}
