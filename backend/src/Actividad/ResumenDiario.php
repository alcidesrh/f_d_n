<?php

declare(strict_types=1);

namespace App\Actividad;

use Doctrine\DBAL\Connection;

/**
 * Resumen de un día por usuario (`actividad_resumen_diario`): primera y
 * última actividad, minutos con actividad, sesiones, peticiones, errores,
 * consultas automáticas, acciones de pantalla y lo que cambió en la
 * bitácora de negocio (por `entidad.operacion`). "El día" es de medianoche a
 * medianoche en Guatemala, como `ConteoDiario`.
 *
 * `calcular()` lo arma sin guardar (el día en curso); `guardar()` lo deja en
 * la tabla (la tarea `bitacora_diaria`, para el día anterior).
 */
class ResumenDiario
{
    public const COLUMNAS = ["primera", "ultima", "minutos_activos", "sesiones", "peticiones", "escrituras", "errores", "sondeos", "pantallas", "cambios", "operaciones"];

    public function __construct(private readonly Connection $db) {}

    /**
     * @return list<array<string, mixed>> una fila por usuario con actividad ese día (o solo la de `$usuario`)
     */
    public function calcular(\DateTimeInterface $dia, ?int $usuario = null): array
    {
        [$sql, $parametros] = $this->consulta($dia, $usuario);
        $filas = $this->db->fetchAllAssociative($sql . " ORDER BY usuario_id", $parametros);

        return array_map(static fn(array $f) => [...$f, "operaciones" => json_decode((string) ($f["operaciones"] ?? "{}"), true) ?: []], $filas);
    }

    /** @return int usuarios resumidos */
    public function guardar(\DateTimeInterface $dia): int
    {
        [$sql, $parametros] = $this->consulta($dia, null);
        $columnas = implode(", ", self::COLUMNAS);
        $actualizar = implode(", ", array_map(static fn(string $c) => "{$c} = EXCLUDED.{$c}", ["empresa_id", "estacion_id", ...self::COLUMNAS]));

        return (int) $this->db->executeStatement(
            "INSERT INTO actividad_resumen_diario (dia, usuario_id, empresa_id, estacion_id, {$columnas})
             SELECT dia, usuario_id, empresa_id, estacion_id, {$columnas} FROM ({$sql}) r
             ON CONFLICT (dia, usuario_id) DO UPDATE SET {$actualizar}",
            $parametros,
        );
    }

    public function hay(\DateTimeInterface $dia): bool
    {
        return (bool) $this->db->fetchOne("SELECT 1 FROM actividad_resumen_diario WHERE dia = :dia LIMIT 1", ["dia" => self::dia($dia)->format("Y-m-d")]);
    }

    /** @return int filas borradas */
    public function purgar(\DateTimeInterface $antesDe): int
    {
        return (int) $this->db->executeStatement("DELETE FROM actividad_resumen_diario WHERE dia < :dia", ["dia" => self::dia($antesDe)->format("Y-m-d")]);
    }

    /** Medianoche de ese día en Guatemala. */
    public static function dia(\DateTimeInterface $fecha): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($fecha)->setTimezone(new \DateTimeZone(Particiones::ZONA))->setTime(0, 0);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function consulta(\DateTimeInterface $dia, ?int $usuario): array
    {
        $desde = self::dia($dia);
        $hasta = $desde->modify("+1 day");
        $parametros = [
            "dia" => $desde->format("Y-m-d"),
            // `actividad.fecha` tiene zona; `bitacora.fecha`, hora local del servidor (Guatemala).
            "desde" => $desde->format("Y-m-d H:i:sP"),
            "hasta" => $hasta->format("Y-m-d H:i:sP"),
            "desde_local" => $desde->format("Y-m-d H:i:s"),
            "hasta_local" => $hasta->format("Y-m-d H:i:s"),
        ];
        $deUsuario = "";
        if ($usuario !== null) {
            $deUsuario = " AND usuario_id = :usuario";
            $parametros["usuario"] = $usuario;
        }

        $sql = <<<SQL
            WITH act AS (
                SELECT usuario_id,
                       min(fecha) AS primera,
                       max(fecha) AS ultima,
                       count(DISTINCT date_trunc('minute', fecha)) FILTER (WHERE tipo <> 'sondeo') AS minutos_activos,
                       count(*) FILTER (WHERE tipo = 'sesion' AND accion = 'inicio') AS sesiones,
                       count(*) FILTER (WHERE tipo = 'peticion') AS peticiones,
                       count(*) FILTER (WHERE tipo = 'peticion' AND metodo IN ('POST', 'PUT', 'PATCH', 'DELETE') AND (accion IS NULL OR accion NOT LIKE 'query %')) AS escrituras,
                       count(*) FILTER (WHERE tipo = 'peticion' AND estado >= 400) AS errores,
                       coalesce(sum((detalle->>'cantidad')::int) FILTER (WHERE tipo = 'sondeo'), 0) AS sondeos,
                       count(*) FILTER (WHERE tipo = 'pantalla') AS pantallas,
                       max(empresa_id) AS empresa_id,
                       max(estacion_id) AS estacion_id
                  FROM actividad
                 WHERE fecha >= :desde AND fecha < :hasta AND usuario_id IS NOT NULL{$deUsuario}
                 GROUP BY usuario_id
            ), neg AS (
                SELECT usuario_id, sum(n) AS cambios, jsonb_object_agg(op, n) AS operaciones
                  FROM (
                      SELECT usuario_id, entidad || '.' || operacion AS op, count(*) AS n
                        FROM bitacora
                       WHERE fecha >= :desde_local AND fecha < :hasta_local AND usuario_id IS NOT NULL{$deUsuario}
                       GROUP BY usuario_id, entidad || '.' || operacion
                  ) x
                 GROUP BY usuario_id
            )
            SELECT CAST(:dia AS DATE) AS dia,
                   coalesce(act.usuario_id, neg.usuario_id) AS usuario_id,
                   act.empresa_id, act.estacion_id, act.primera, act.ultima,
                   coalesce(act.minutos_activos, 0) AS minutos_activos,
                   coalesce(act.sesiones, 0) AS sesiones,
                   coalesce(act.peticiones, 0) AS peticiones,
                   coalesce(act.escrituras, 0) AS escrituras,
                   coalesce(act.errores, 0) AS errores,
                   coalesce(act.sondeos, 0) AS sondeos,
                   coalesce(act.pantallas, 0) AS pantallas,
                   coalesce(neg.cambios, 0) AS cambios,
                   coalesce(neg.operaciones, '{}'::jsonb) AS operaciones
              FROM act FULL JOIN neg ON neg.usuario_id = act.usuario_id
            SQL;

        return [$sql, $parametros];
    }
}
