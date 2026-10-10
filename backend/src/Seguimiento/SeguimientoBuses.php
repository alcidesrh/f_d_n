<?php

declare(strict_types=1);

namespace App\Seguimiento;

use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;

/**
 * Buses en recorrido ahora: las salidas que ya salieron (por horario), ubicadas con
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
        private readonly SalidasEnRecorrido $salidas,
        private readonly FuenteGps $gps,
        private readonly ParametrosSimulacion $parametros = new ParametrosSimulacion(),
    ) {}

    /**
     * @return array{generado: int, buses: list<array<string, mixed>>, empresas: list<array{id: int, nombre: string}>, sinTrazado: int}
     */
    public function enRecorrido(\DateTimeImmutable $ahora): array
    {
        $filas = $this->salidas->buscar(
            $ahora->modify('-' . self::VENTANA_HORAS . ' hours'),
            $ahora,
            $ahora->modify('+' . self::ADELANTO_MINUTOS . ' minutes'),
        );

        $t = $ahora->getTimestamp();
        $trazos = [];
        $buses = [];
        $empresas = [];
        $sinTrazado = 0;

        foreach ($filas as $f) {
            /** @var Salida $salida */
            $salida = $f['salida'];
            $trayecto = $salida->getTrayecto();
            $codigo = (string) $trayecto->getId();
            $trazos[$codigo] ??= TrazadoDeRuta::trazar($f['paradas'], $f['kilometros']);
            if (null === $trazos[$codigo]) {
                ++$sinTrazado;
                continue;
            }

            $partida = new \DateTimeImmutable($salida->getFecha()->format('Y-m-d H:i:s'), $ahora->getTimezone());
            $plan = PlanDeViaje::construir($trazos[$codigo], $partida, (int) $salida->getId(), $this->parametros);
            if ($t > $plan->llegada() + self::GRACIA_LLEGADA_MINUTOS * 60) {
                continue;
            }

            $bus = $salida->getBus();
            $real = $this->gps->ultima((string) $bus->getCodigo());
            $vigente = null !== $real && $t - $real->instante <= self::GPS_VIGENCIA_SEGUNDOS;
            $pos = $vigente ? $real : $plan->posicionEn($t);
            $proxima = $plan->proximaParada($t);

            $empresa = $salida->getEmpresa();
            $empresaId = (int) $empresa?->getId();
            $empresas[$empresaId] = ['id' => $empresaId, 'nombre' => (string) ($empresa?->getNombreCorto() ?? "Empresa $empresaId")];
            $piloto = $bus->getPiloto();
            $estado = $salida->getEstado();

            $buses[] = [
                'salidaId' => (int) $salida->getId(),
                // Si el sistema ya la marcó iniciada; si no, se asume por la hora y los boletos vendidos.
                'marcadaIniciada' => EstadoSalida::INICIADA === $estado,
                'estadoSistema' => match ($estado) { EstadoSalida::PROGRAMADA => 'programada', EstadoSalida::ABORDANDO => 'abordando', EstadoSalida::INICIADA => 'iniciada', default => 'otro' },
                'empresaId' => $empresaId,
                'empresa' => $empresas[$empresaId]['nombre'],
                'bus' => (string) $bus->getCodigo(),
                'placa' => $bus->getMatricula(),
                'piloto' => $piloto ? trim($piloto->getNombre() . ' ' . $piloto->getApellido()) : null,
                'ruta' => $codigo,
                'rutaNombre' => (string) ($trayecto->getNombre() ?? $plan->paradas[0]->nombre . ' → ' . $plan->paradas[count($plan->paradas) - 1]->nombre),
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
}
