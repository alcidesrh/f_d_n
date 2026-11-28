<?php

declare(strict_types=1);

namespace App\Salida\Cierre;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\OperacionEncomienda;
use App\Entity\Enum\OperacionSalida;
use App\Venta\PublicadorOcupacion;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Cierre por la hora (ADR-074): lo que el abordaje deja a mano y en la
 * práctica casi nadie marca. Cada pocos minutos, por la hora y no por lo
 * que hizo el usuario:
 *
 * - **Finaliza** la salida abierta (programada, abordando o iniciada) cuya
 *   llegada (la hora de su última parada) pasó hace `MINUTOS_LLEGADA`. Sus
 *   boletos vivos (emitido, chequeado, tránsito) de ventas confirmadas
 *   quedan finalizados: se da por hecho que viajaron (el no presentado del
 *   abordaje es una decisión de taquilla, no de la hora). Sus encomiendas a
 *   bordo se desembarcan en su destino si la salida pasa por él, y si no en
 *   la última parada.
 * - **Inicia** la programada o abordando cuya hora de salida pasó hace
 *   `MINUTOS_INICIO` y que aún no llega: chequeados → tránsito y embarcadas
 *   → tránsito, como `Abordaje::iniciar` (los emitidos siguen chequeables).
 *
 * Por SQL, una sentencia por paso y sin pasar por el workflow: no es exacto
 * (no sabe si el bus salió tarde) y no tiene que serlo. La bitácora anota la
 * salida y cada encomienda movida (sin usuario); los boletos no, por
 * volumen. La sincronización no lo lleva al legado (no pasa por el flush).
 */
final class CierreSalidas
{
    public const MINUTOS_INICIO = 15;
    public const MINUTOS_LLEGADA = 60;
    /** Sin horario, la llegada se supone a un día de la salida. */
    private const SIN_HORARIO = "1 day";
    /** Más que esto (la primera corrida, con todo el atraso) no se avisa por Mercure: nadie las está mirando. */
    private const MAX_AVISOS = 100;
    private const SISTEMA = "Sistema (por la hora)";

    public function __construct(
        private readonly Connection $db,
        private readonly ClockInterface $reloj,
        private readonly PublicadorOcupacion $ocupacion,
    ) {}

    /** @return array{finalizadas: int, iniciadas: int, boletos: int, encomiendas: int} */
    public function cerrar(): array
    {
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());
        [$finalizadas, $iniciadas, $boletos, $encomiendas] = $this->db->transactional(function () use ($ahora): array {
            $f = $this->finalizar($ahora);
            $i = $this->iniciar($ahora);

            return [$f["salidas"], $i["salidas"], $f["boletos"] + $i["boletos"], $f["encomiendas"] + $i["encomiendas"]];
        });
        $cambiadas = [...$finalizadas, ...$iniciadas];
        if (count($cambiadas) <= self::MAX_AVISOS) {
            foreach ($cambiadas as $id) {
                $this->ocupacion->cambio($id);
            }
        }

        return ["finalizadas" => count($finalizadas), "iniciadas" => count($iniciadas), "boletos" => $boletos, "encomiendas" => $encomiendas];
    }

    /** @return array{salidas: list<int>, boletos: int, encomiendas: int} */
    private function finalizar(\DateTimeImmutable $ahora): array
    {
        $fila = $this->db->fetchAssociative(
            "WITH salidas AS (
                 UPDATE salida s SET estado = :finalizada
                  WHERE s.estado IN (:abiertas) AND s.fecha < :inicio
                    AND COALESCE((SELECT MAX(p.hora) FROM salida_parada p WHERE p.salida_id = s.id), s.fecha + CAST(:sinHorario AS INTERVAL)) < :llegada
                 RETURNING s.id
             ), boletos AS (
                 UPDATE boleto_asiento b SET estado = :finalizado
                   FROM salidas s, boleto_venta v
                  WHERE b.salida_id = s.id AND v.id = b.boleto_venta_id AND v.estado = :confirmada AND b.estado IN (:vivos)
                 RETURNING b.id
             ), destinos AS (
                 SELECT e.id, COALESCE(
                            (SELECT p.enclave_id FROM salida_parada p WHERE p.salida_id = e.salida_id AND p.enclave_id = e.destino_id LIMIT 1),
                            (SELECT p.enclave_id FROM salida_parada p WHERE p.salida_id = e.salida_id ORDER BY p.orden DESC LIMIT 1),
                            e.destino_id) AS enclave_id
                   FROM encomienda e JOIN salidas s ON s.id = e.salida_id
                  WHERE e.estado IN (:aBordo)
             ), encomiendas AS (
                 UPDATE encomienda e SET estado = :desembarcada, ubicacion_id = d.enclave_id
                   FROM destinos d WHERE e.id = d.id
                 RETURNING e.id, e.ubicacion_id
             ), anotadas AS (
                 INSERT INTO bitacora (entidad, registro_id, operacion, usuario_nombre, fecha, detalle)
                 SELECT 'salida', s.id, :opFinalizada, :sistema, CAST(:ahora AS TIMESTAMP), CAST('{\"automatico\": true}' AS JSON) FROM salidas s
                 UNION ALL
                 SELECT 'encomienda', e.id, :opDesembarcada, :sistema, CAST(:ahora AS TIMESTAMP), json_build_object('estacion', en.nombre, 'automatico', true)
                   FROM encomiendas e JOIN enclave en ON en.id = e.ubicacion_id
                 RETURNING 1
             )
             SELECT (SELECT COALESCE(json_agg(id), '[]') FROM salidas) AS salidas,
                    (SELECT COUNT(*) FROM boletos) AS boletos,
                    (SELECT COUNT(*) FROM encomiendas) AS encomiendas,
                    (SELECT COUNT(*) FROM anotadas) AS anotadas",
            [
                "finalizada" => EstadoSalida::FINALIZADA->value,
                "abiertas" => [EstadoSalida::PROGRAMADA->value, EstadoSalida::ABORDANDO->value, EstadoSalida::INICIADA->value],
                "inicio" => self::fecha($ahora->modify("-" . self::MINUTOS_INICIO . " minutes")),
                "llegada" => self::fecha($ahora->modify("-" . self::MINUTOS_LLEGADA . " minutes")),
                "sinHorario" => self::SIN_HORARIO,
                "finalizado" => EstadoBoletoAsiento::FINALIZADO->value,
                "confirmada" => EstadoBoletoVenta::CONFIRMADA->value,
                "vivos" => [EstadoBoletoAsiento::EMITIDO->value, EstadoBoletoAsiento::CHEQUEADO->value, EstadoBoletoAsiento::TRANSITO->value],
                "aBordo" => [EstadoEncomienda::EMBARCADA->value, EstadoEncomienda::TRANSITO->value],
                "desembarcada" => EstadoEncomienda::DESEMBARCADA->value,
                "opFinalizada" => OperacionSalida::FINALIZADA->value,
                "opDesembarcada" => OperacionEncomienda::DESEMBARCADA->value,
                "sistema" => self::SISTEMA,
                "ahora" => self::fecha($ahora),
            ],
            ["abiertas" => ArrayParameterType::STRING, "vivos" => ArrayParameterType::STRING, "aBordo" => ArrayParameterType::STRING],
        );

        return self::resultado($fila);
    }

    /** @return array{salidas: list<int>, boletos: int, encomiendas: int} */
    private function iniciar(\DateTimeImmutable $ahora): array
    {
        $fila = $this->db->fetchAssociative(
            "WITH salidas AS (
                 UPDATE salida s SET estado = :iniciada
                  WHERE s.estado IN (:porIniciar) AND s.fecha < :inicio
                 RETURNING s.id
             ), boletos AS (
                 UPDATE boleto_asiento b SET estado = :transito
                   FROM salidas s WHERE b.salida_id = s.id AND b.estado = :chequeado
                 RETURNING b.id
             ), encomiendas AS (
                 UPDATE encomienda e SET estado = :encTransito
                   FROM salidas s WHERE e.salida_id = s.id AND e.estado = :embarcada
                 RETURNING e.id
             ), anotadas AS (
                 INSERT INTO bitacora (entidad, registro_id, operacion, usuario_nombre, fecha, detalle)
                 SELECT 'salida', s.id, :opIniciada, :sistema, CAST(:ahora AS TIMESTAMP), CAST('{\"automatico\": true}' AS JSON) FROM salidas s
                 UNION ALL
                 SELECT 'encomienda', e.id, :opTransito, :sistema, CAST(:ahora AS TIMESTAMP), CAST('{\"automatico\": true}' AS JSON) FROM encomiendas e
                 RETURNING 1
             )
             SELECT (SELECT COALESCE(json_agg(id), '[]') FROM salidas) AS salidas,
                    (SELECT COUNT(*) FROM boletos) AS boletos,
                    (SELECT COUNT(*) FROM encomiendas) AS encomiendas,
                    (SELECT COUNT(*) FROM anotadas) AS anotadas",
            [
                "iniciada" => EstadoSalida::INICIADA->value,
                "porIniciar" => [EstadoSalida::PROGRAMADA->value, EstadoSalida::ABORDANDO->value],
                "inicio" => self::fecha($ahora->modify("-" . self::MINUTOS_INICIO . " minutes")),
                "transito" => EstadoBoletoAsiento::TRANSITO->value,
                "chequeado" => EstadoBoletoAsiento::CHEQUEADO->value,
                "encTransito" => EstadoEncomienda::TRANSITO->value,
                "embarcada" => EstadoEncomienda::EMBARCADA->value,
                "opIniciada" => OperacionSalida::INICIADA->value,
                "opTransito" => OperacionEncomienda::TRANSITO->value,
                "sistema" => self::SISTEMA,
                "ahora" => self::fecha($ahora),
            ],
            ["porIniciar" => ArrayParameterType::STRING],
        );

        return self::resultado($fila);
    }

    /**
     * @param array<string, mixed>|false $fila
     *
     * @return array{salidas: list<int>, boletos: int, encomiendas: int}
     */
    private static function resultado(array|false $fila): array
    {
        return [
            "salidas" => array_map("intval", json_decode((string) ($fila["salidas"] ?? "[]"), true, flags: JSON_THROW_ON_ERROR)),
            "boletos" => (int) ($fila["boletos"] ?? 0),
            "encomiendas" => (int) ($fila["encomiendas"] ?? 0),
        ];
    }

    private static function fecha(\DateTimeInterface $d): string
    {
        return $d->format("Y-m-d H:i:s");
    }
}
