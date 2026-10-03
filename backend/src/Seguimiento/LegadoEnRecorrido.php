<?php

declare(strict_types=1);

namespace App\Seguimiento;

use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Lectura (solo lectura) del SQL Server legado: salidas que ya salieron y las
 * estaciones de sus rutas. El PDO viene con el preflight TCP de `SondaLegado`,
 * así que si el legado no responde falla en segundos y no cuelga la petición.
 */
#[Lazy]
class LegadoEnRecorrido
{
    /** Estados de `salida_estado` (ver `EntitySistemaFdn\EstadoSalida`). */
    private const INICIADA = 3;
    private const CANCELADA = 4;
    private const FINALIZADA = 5;
    /** Estados de `boleto_estado` que cuentan como asiento vendido: emitido, chequeado y en tránsito (no anulado, reasignado ni cancelado). */
    private const BOLETO_VIGENTE = '1, 2, 3';

    public function __construct(#[Target('oldPdo')] private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /**
     * Salidas que ya salieron, con fecha desde `$desde` hasta `$ahora` (hora local del legado).
     * El criterio es el horario, no el estado: el personal casi nunca marca "iniciada".
     * Salió toda salida que pasó su hora, no está cancelada ni finalizada y tiene al menos
     * un boleto vigente; las marcadas "iniciada" salen aunque no tengan boletos (y hasta
     * `$adelanto` antes de su hora). El legado casi nunca marca `finalizada`, así que quien
     * llama descarta las que ya llegaron.
     *
     * @return list<array<string, mixed>>
     */
    public function salidasEnRecorrido(\DateTimeInterface $desde, \DateTimeInterface $ahora, \DateTimeInterface $adelanto): array
    {
        $st = $this->pdo->prepare(
            'SELECT s.id, s.fecha, s.estado_id, s.empresa_id, COALESCE(NULLIF(e.alias, \'\'), e.nombre) AS empresa, s.bus_codigo, b.placa,
                    p.nombre AS piloto_nombre, p.apellidos AS piloto_apellidos, i.ruta_codigo, r.nombre AS ruta, r.kilometros,
                    r.estacion_origen_id, r.estacion_destino_id
             FROM salida s
             JOIN itineario i ON i.id = s.itinerario_id
             JOIN ruta r ON r.codigo = i.ruta_codigo
             LEFT JOIN empresa e ON e.id = s.empresa_id
             LEFT JOIN bus b ON b.codigo = s.bus_codigo
             LEFT JOIN piloto p ON p.id = s.piloto_id
             WHERE s.fecha >= :desde AND (
                 (s.estado_id = ' . self::INICIADA . ' AND s.fecha <= :adelanto)
                 OR (s.estado_id NOT IN (' . self::INICIADA . ', ' . self::CANCELADA . ', ' . self::FINALIZADA . ') AND s.fecha <= :ahora
                     AND EXISTS (SELECT 1 FROM boleto bo WHERE bo.salida_id = s.id AND bo.estado_id IN (' . self::BOLETO_VIGENTE . ')))
             )
             ORDER BY s.fecha',
        );
        $st->execute([
            'desde' => $desde->format('Y-m-d H:i:s'),
            'ahora' => $ahora->format('Y-m-d H:i:s'),
            'adelanto' => $adelanto->format('Y-m-d H:i:s'),
        ]);

        return array_map(self::utf8(...), $st->fetchAll());
    }

    /**
     * Estaciones de las rutas dadas, origen → intermedias → destino.
     *
     * @param list<array{ruta_codigo: string, estacion_origen_id: int|string, estacion_destino_id: int|string}> $rutas
     * @return array<string, list<array{id: int, nombre: string, latitude: mixed, longitude: mixed, departamento: ?string}>>
     */
    public function estacionesPorRuta(array $rutas): array
    {
        if ([] === $rutas) {
            return [];
        }

        $codigos = array_values(array_unique(array_column($rutas, 'ruta_codigo')));
        $marcas = implode(',', array_fill(0, count($codigos), '?'));
        $st = $this->pdo->prepare(
            "SELECT x.ruta_codigo, x.posicion, es.id, es.nombre, es.latitude, es.longitude, d.nombre AS departamento
             FROM ruta_estacion_item x
             JOIN estacion es ON es.id = x.estacion_id
             LEFT JOIN departamento d ON d.id = es.departamento_id
             WHERE x.ruta_codigo IN ($marcas)
             ORDER BY x.ruta_codigo, x.posicion",
        );
        $st->execute($codigos);
        $intermedias = [];
        foreach ($st->fetchAll() as $f) {
            $intermedias[$f['ruta_codigo']][] = self::estacion(self::utf8($f));
        }

        $ids = [];
        foreach ($rutas as $r) {
            $ids[] = (int) $r['estacion_origen_id'];
            $ids[] = (int) $r['estacion_destino_id'];
        }
        $ids = array_values(array_unique($ids));
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare(
            "SELECT es.id, es.nombre, es.latitude, es.longitude, d.nombre AS departamento
             FROM estacion es LEFT JOIN departamento d ON d.id = es.departamento_id
             WHERE es.id IN ($marcas)",
        );
        $st->execute($ids);
        $porId = [];
        foreach ($st->fetchAll() as $f) {
            $porId[(int) $f['id']] = self::estacion(self::utf8($f));
        }

        $out = [];
        foreach ($rutas as $r) {
            $origen = $porId[(int) $r['estacion_origen_id']] ?? null;
            $destino = $porId[(int) $r['estacion_destino_id']] ?? null;
            if (null === $origen || null === $destino) {
                continue;
            }
            $out[$r['ruta_codigo']] = [$origen, ...($intermedias[$r['ruta_codigo']] ?? []), $destino];
        }

        return $out;
    }

    /** El legado entrega texto en ISO-8859-1. @param array<string, mixed> $fila */
    private static function utf8(array $fila): array
    {
        foreach ($fila as $k => $v) {
            if (is_string($v)) {
                $fila[$k] = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
            }
        }

        return $fila;
    }

    /** @param array<string, mixed> $f */
    private static function estacion(array $f): array
    {
        return [
            'id' => (int) $f['id'],
            'nombre' => trim((string) $f['nombre']),
            'latitude' => $f['latitude'],
            'longitude' => $f['longitude'],
            'departamento' => $f['departamento'] ?? null,
        ];
    }
}
