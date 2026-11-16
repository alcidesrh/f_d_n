<?php

declare(strict_types=1);

namespace App\Seguimiento;

use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\PrecisionHora;
use App\Entity\Salida;
use App\Entity\SalidaParada;

/**
 * Hora estimada de llegada de una salida a sus paradas (ADR-064): su horario
 * (`SalidaParada`) corregido con la última lectura GPS de su bus
 * (`HoraEstimada`). La lectura solo cuenta mientras la salida está en
 * recorrido: el mismo bus hace otras salidas antes y después.
 */
class HorasEstimadas
{
    /** Desde cuánto antes de su hora la lectura del bus es de esta salida. */
    private const ANTES_MINUTOS = 30;

    public function __construct(
        private readonly FuenteGps $gps,
    ) {}

    /**
     * @return array{fuente: 'gps'|'horario', atrasoMinutos: ?int, lectura: ?int, paradas: list<array{enclave: int, nombre: string, programada: ?int, estimada: ?int, paso: bool}>}
     */
    public function deSalida(Salida $salida, \DateTimeImmutable $ahora): array
    {
        $zona = $ahora->getTimezone();
        $horario = [];
        /** @var SalidaParada $p */
        foreach ($salida->getHorario() as $p) {
            $enclave = $p->getEnclave();
            $horario[] = [
                "enclave" => (int) $enclave->getId(),
                "nombre" => trim((string) $enclave->getNombre()),
                "lat" => is_numeric($enclave->getLatitud()) ? (float) $enclave->getLatitud() : null,
                "lng" => is_numeric($enclave->getLongitud()) ? (float) $enclave->getLongitud() : null,
                // Las horas se guardan sin zona, en hora local (como `SeguimientoBuses`).
                "hora" => $p->getPrecision() === PrecisionHora::DESCONOCIDA ? null : (new \DateTimeImmutable($p->getHora()->format("Y-m-d H:i:s"), $zona))->getTimestamp(),
            ];
        }

        $t = $ahora->getTimestamp();
        $lectura = $this->lectura($salida, $horario, $t);
        $estimacion = HoraEstimada::calcular($horario, $lectura, $t);

        return [...$estimacion, "lectura" => $estimacion["fuente"] === "gps" && $lectura !== null ? $lectura["instante"] : null];
    }

    /**
     * La lectura vigente del bus, si la salida está en recorrido: no
     * cancelada ni finalizada, y desde un rato antes de su hora.
     *
     * @param list<array{hora: ?int}> $horario
     *
     * @return array{lat: float, lng: float, instante: int}|null
     */
    private function lectura(Salida $salida, array $horario, int $ahora): ?array
    {
        $partida = $horario[0]["hora"] ?? null;
        $bus = $salida->getBus();
        if ($partida === null || $bus === null
            || in_array($salida->getEstado(), [EstadoSalida::CANCELADA, EstadoSalida::FINALIZADA], true)
            || $ahora < $partida - self::ANTES_MINUTOS * 60) {
            return null;
        }
        $posicion = $this->gps->ultima((string) $bus->getCodigo());
        if ($posicion === null || $posicion->fuente !== "gps" || $ahora - $posicion->instante > SeguimientoBuses::GPS_VIGENCIA_SEGUNDOS) {
            return null;
        }

        return ["lat" => $posicion->lat, "lng" => $posicion->lng, "instante" => $posicion->instante];
    }
}
