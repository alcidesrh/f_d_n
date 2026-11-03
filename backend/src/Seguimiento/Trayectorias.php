<?php

declare(strict_types=1);

namespace App\Seguimiento;

use App\Entity\Salida;
use Doctrine\DBAL\Connection;

/**
 * Trayectorias GPS de salidas pasadas o en curso, para reproducirlas en el mapa
 * (ADR-050): qué salidas de un día tienen lecturas y la trayectoria de una.
 * La salida se reconoce por el bus y su horario (`VentanaTrayectoria`).
 */
final class Trayectorias
{
    private const ZONA = 'America/Guatemala';

    public function __construct(
        private readonly Connection $db,
        private readonly HistorialGps $historial,
        private readonly SalidasEnRecorrido $salidas,
    ) {}

    /**
     * Salidas que salen ese día (hora local) y tienen lecturas GPS, de las empresas que `$ve`.
     *
     * @param callable(int): bool $ve por id de empresa
     * @return array{fecha: string, salidas: list<array<string, mixed>>, sinGps: int}
     */
    public function delDia(\DateTimeImmutable $dia, callable $ve): array
    {
        $desde = $dia->setTime(0, 0);
        $filas = array_values(array_filter(
            $this->filas('s.fecha >= :desde AND s.fecha < :hasta', ['desde' => $desde->format('Y-m-d H:i:s'), 'hasta' => $desde->modify('+1 day')->format('Y-m-d H:i:s')]),
            static fn(array $f) => $ve((int) $f['empresa_id']),
        ));

        $ventanas = [];
        foreach ($filas as $f) {
            $v = $this->ventana($f);
            $ventanas[] = ['salida' => (int) $f['id'], 'bus' => (int) $f['bus_id'], 'desde' => $v->desde, 'hasta' => $v->hasta];
        }
        $resumen = $this->historial->resumen($ventanas);

        $salidas = [];
        foreach ($filas as $f) {
            if (isset($resumen[(int) $f['id']])) {
                $salidas[] = [...$this->datos($f), ...$resumen[(int) $f['id']]];
            }
        }

        return ['fecha' => $desde->format('Y-m-d'), 'salidas' => $salidas, 'sinGps' => count($filas) - count($salidas)];
    }

    /**
     * La trayectoria de una salida: sus lecturas, su ruta y sus estaciones (si se pueden ubicar).
     * Quien llama ya comprobó que el usuario la ve.
     *
     * @return array<string, mixed>
     */
    public function deSalida(Salida $salida): array
    {
        $fila = $this->filas('s.id = :id', ['id' => (int) $salida->getId()])[0]
            ?? throw new \DomainException('La salida no tiene bus.');
        $ventana = $this->ventana($fila);
        $puntos = $this->historial->lecturas((int) $fila['bus_id'], $ventana->desde, $ventana->hasta);

        $paradas = $this->salidas->paradas([$salida])[(int) $salida->getId()] ?? [];
        $trazado = TrazadoDeRuta::trazar($paradas, (float) $salida->getRuta()->getKilometros()) ?? [];
        $piloto = $salida->getBus()?->getPiloto();

        return [
            ...$this->datos($fila),
            'piloto' => $piloto ? trim($piloto->getNombre() . ' ' . $piloto->getApellido()) : null,
            'desde' => $ventana->desde,
            'hasta' => $ventana->hasta,
            'lecturas' => count($puntos),
            'primera' => $puntos[0][0] ?? null,
            'ultima' => [] === $puntos ? null : $puntos[count($puntos) - 1][0],
            // Sin estaciones ubicables solo se dibujan las lecturas.
            'trazado' => array_map(static fn(array $p) => [round($p['lat'], 6), round($p['lng'], 6)], $trazado),
            'estaciones' => array_map(static fn(array $p) => ['nombre' => $p['nombre'], 'lat' => round($p['lat'], 6), 'lng' => round($p['lng'], 6), 'origen' => $p['origen']], $trazado),
            'puntos' => array_map(static fn(array $p) => [$p[0], round($p[1], 6), round($p[2], 6), $p[3], $p[4]], $puntos),
        ];
    }

    /**
     * Salidas no canceladas con bus, con lo necesario para su ventana: llegada programada (la hora
     * de la última parada) y la hora de la siguiente salida del mismo bus.
     *
     * @param array<string, int|string> $parametros
     * @return list<array<string, mixed>>
     */
    private function filas(string $condicion, array $parametros): array
    {
        return $this->db->fetchAllAssociative(<<<SQL
            SELECT s.id, s.fecha, s.estado, s.bus_id, s.empresa_id, s.ruta_id,
                   b.codigo AS bus, b.matricula AS placa,
                   COALESCE(NULLIF(e.alias, ''), e.nombre) AS empresa,
                   r.nombre AS ruta_nombre,
                   (SELECT MAX(sp.hora) FROM salida_parada sp WHERE sp.salida_id = s.id) AS llegada,
                   (SELECT en.nombre FROM salida_parada sp JOIN enclave en ON en.id = sp.enclave_id WHERE sp.salida_id = s.id ORDER BY sp.orden LIMIT 1) AS origen,
                   (SELECT en.nombre FROM salida_parada sp JOIN enclave en ON en.id = sp.enclave_id WHERE sp.salida_id = s.id ORDER BY sp.orden DESC LIMIT 1) AS destino,
                   (SELECT MIN(s2.fecha) FROM salida s2 WHERE s2.bus_id = s.bus_id AND s2.fecha > s.fecha AND s2.estado <> 'cancelada') AS siguiente
            FROM salida s
            JOIN bus b ON b.id = s.bus_id
            JOIN ruta r ON r.id = s.ruta_id
            LEFT JOIN empresa e ON e.id = s.empresa_id
            WHERE $condicion AND s.estado <> 'cancelada'
            ORDER BY s.fecha, s.id
            SQL, $parametros);
    }

    /** @param array<string, mixed> $f */
    private function ventana(array $f): VentanaTrayectoria
    {
        return VentanaTrayectoria::de(self::local($f['fecha']) ?? throw new \LogicException('Salida sin fecha.'), self::local($f['llegada']), self::local($f['siguiente']));
    }

    /**
     * @param array<string, mixed> $f
     * @return array<string, mixed>
     */
    private function datos(array $f): array
    {
        $origen = trim((string) $f['origen']);
        $destino = trim((string) $f['destino']);

        return [
            'salidaId' => (int) $f['id'],
            'estadoSistema' => (string) $f['estado'],
            'empresaId' => (int) $f['empresa_id'],
            'empresa' => (string) ($f['empresa'] ?? 'Empresa ' . $f['empresa_id']),
            'bus' => (string) $f['bus'],
            'placa' => $f['placa'],
            'ruta' => (string) $f['ruta_id'],
            'rutaNombre' => trim((string) $f['ruta_nombre']) ?: "$origen → $destino",
            'origen' => $origen,
            'destino' => $destino,
            'partida' => self::local($f['fecha'])?->getTimestamp(),
            'llegadaProgramada' => self::local($f['llegada'])?->getTimestamp(),
        ];
    }

    /** Las horas de salida y del horario se guardan en hora local, sin zona. */
    private static function local(mixed $valor): ?\DateTimeImmutable
    {
        return is_string($valor) && '' !== $valor ? new \DateTimeImmutable($valor, new \DateTimeZone(self::ZONA)) : null;
    }
}
