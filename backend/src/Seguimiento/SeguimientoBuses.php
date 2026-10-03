<?php

declare(strict_types=1);

namespace App\Seguimiento;

use Doctrine\DBAL\Connection;

/**
 * Buses en recorrido ahora: las salidas del legado que ya salieron (por horario), ubicadas con
 * GPS si hay lectura reciente y, si no, con la simulación de `PlanDeViaje`.
 */
final class SeguimientoBuses
{
    /** Cuánto tiempo atrás puede haber salido un bus todavía en ruta. */
    private const VENTANA_HORAS = 30;
    /** Una salida iniciada hasta tanto antes de su hora se muestra en el origen. */
    private const ADELANTO_MINUTOS = 30;
    /** Un bus que llegó se sigue mostrando este rato. */
    private const GRACIA_LLEGADA_MINUTOS = 10;
    /** Una lectura GPS más vieja que esto se ignora a favor de la simulación. */
    private const GPS_VIGENCIA_SEGUNDOS = 300;

    public function __construct(
        private readonly LegadoEnRecorrido $legado,
        private readonly FuenteGps $gps,
        private readonly Connection $db,
        private readonly ParametrosSimulacion $parametros = new ParametrosSimulacion(),
    ) {}

    /**
     * @return array{generado: int, buses: list<array<string, mixed>>, empresas: list<array{id: int, nombre: string}>, sinTrazado: int}
     */
    public function enRecorrido(\DateTimeImmutable $ahora): array
    {
        $filas = $this->legado->salidasEnRecorrido(
            $ahora->modify('-' . self::VENTANA_HORAS . ' hours'),
            $ahora,
            $ahora->modify('+' . self::ADELANTO_MINUTOS . ' minutes'),
        );
        $estaciones = $this->conCoordenadasNuevas($this->legado->estacionesPorRuta($filas));

        $t = $ahora->getTimestamp();
        $trazos = [];
        $buses = [];
        $empresas = [];
        $sinTrazado = 0;

        foreach ($filas as $f) {
            $codigo = (string) $f['ruta_codigo'];
            $trazos[$codigo] ??= TrazadoDeRuta::trazar($estaciones[$codigo] ?? [], (float) $f['kilometros']);
            if (null === $trazos[$codigo]) {
                ++$sinTrazado;
                continue;
            }

            $partida = new \DateTimeImmutable((string) $f['fecha'], $ahora->getTimezone());
            $plan = PlanDeViaje::construir($trazos[$codigo], $partida, (int) $f['id'], $this->parametros);
            if ($t > $plan->llegada() + self::GRACIA_LLEGADA_MINUTOS * 60) {
                continue;
            }

            $real = $this->gps->ultima((string) $f['bus_codigo']);
            $vigente = null !== $real && $t - $real->instante <= self::GPS_VIGENCIA_SEGUNDOS;
            $pos = $vigente ? $real : $plan->posicionEn($t);
            $proxima = $plan->proximaParada($t);

            $empresaId = (int) $f['empresa_id'];
            $empresas[$empresaId] = ['id' => $empresaId, 'nombre' => (string) ($f['empresa'] ?? "Empresa $empresaId")];

            $buses[] = [
                'salidaId' => (int) $f['id'],
                // Si el sistema ya la marcó iniciada; si no, se asume por la hora y los boletos vendidos.
                'marcadaIniciada' => 3 === (int) $f['estado_id'],
                'estadoSistema' => match ((int) $f['estado_id']) { 1 => 'programada', 2 => 'abordando', 3 => 'iniciada', default => 'otro' },
                'empresaId' => $empresaId,
                'empresa' => $empresas[$empresaId]['nombre'],
                'bus' => (string) $f['bus_codigo'],
                'placa' => $f['placa'] ?? null,
                'piloto' => trim(($f['piloto_nombre'] ?? '') . ' ' . ($f['piloto_apellidos'] ?? '')) ?: null,
                'ruta' => $codigo,
                'rutaNombre' => (string) $f['ruta'],
                'origen' => $plan->paradas[0]->nombre,
                'destino' => $plan->paradas[count($plan->paradas) - 1]->nombre,
                'partida' => $plan->partida(),
                'llegadaEstimada' => $plan->llegada(),
                'kilometros' => round($plan->paradas[count($plan->paradas) - 1]->km, 1),
                'progreso' => round($pos->km / max(1.0, $plan->paradas[count($plan->paradas) - 1]->km), 4),
                'proxima' => $proxima ? ['nombre' => $proxima->nombre, 'llegada' => $proxima->llegada] : null,
                'posicion' => [
                    'lat' => round($pos->lat, 6),
                    'lng' => round($pos->lng, 6),
                    'rumbo' => round($pos->rumbo, 1),
                    'velocidadKmh' => round($pos->velocidadKmh, 1),
                    'km' => round($pos->km, 2),
                    'estado' => $pos->estado,
                    'instante' => $pos->instante,
                    'fuente' => $pos->fuente,
                ],
                // El cronograma permite al navegador animar el bus entre consultas; con GPS no se usa.
                'paradas' => $vigente ? [] : array_map(static fn(Parada $p) => [
                    'nombre' => $p->nombre,
                    'lat' => round($p->lat, 6),
                    'lng' => round($p->lng, 6),
                    'km' => round($p->km, 2),
                    'llegada' => $p->llegada,
                    'salida' => $p->salida,
                    'origen' => $p->origenCoordenada,
                ], $plan->paradas),
                'trazado' => array_map(static fn(Parada $p) => [round($p->lat, 6), round($p->lng, 6)], $plan->paradas),
                'estaciones' => array_map(static fn(Parada $p) => ['nombre' => $p->nombre, 'lat' => round($p->lat, 6), 'lng' => round($p->lng, 6), 'origen' => $p->origenCoordenada], $plan->paradas),
            ];
        }

        usort($empresas, static fn(array $a, array $b) => strcmp($a['nombre'], $b['nombre']));

        return ['generado' => $t, 'buses' => $buses, 'empresas' => array_values($empresas), 'sinTrazado' => $sinTrazado];
    }

    /**
     * Las estaciones sin GPS en el legado toman la coordenada del enclave del modelo
     * nuevo (mismo id que la estación), geocodificada con `app:enclave:geocodificar`.
     *
     * @param array<string, list<array<string, mixed>>> $porRuta
     * @return array<string, list<array<string, mixed>>>
     */
    private function conCoordenadasNuevas(array $porRuta): array
    {
        $sin = [];
        foreach ($porRuta as $estaciones) {
            foreach ($estaciones as $e) {
                if (null === TrazadoDeRuta::normalizarGps($e['latitude'], $e['longitude'])) {
                    $sin[$e['id']] = true;
                }
            }
        }
        if ([] === $sin) {
            return $porRuta;
        }

        $coords = [];
        foreach ($this->db->fetchAllAssociative('SELECT id, latitud, longitud FROM enclave WHERE latitud IS NOT NULL AND longitud IS NOT NULL AND id IN (?)', [array_keys($sin)], [\Doctrine\DBAL\ArrayParameterType::INTEGER]) as $c) {
            $coords[(int) $c['id']] = $c;
        }
        foreach ($porRuta as &$estaciones) {
            foreach ($estaciones as &$e) {
                if (isset($coords[$e['id']]) && null === TrazadoDeRuta::normalizarGps($e['latitude'], $e['longitude'])) {
                    $e['latitude'] = $coords[$e['id']]['latitud'];
                    $e['longitude'] = $coords[$e['id']]['longitud'];
                }
            }
            unset($e);
        }
        unset($estaciones);

        return $porRuta;
    }
}
