<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Cronograma de una salida: dónde debería estar el bus en cada instante según
 * la distancia, una velocidad media y las esperas en estaciones. Es
 * determinista: la misma salida da el mismo plan en cada consulta, así el
 * frontend puede animar el bus con las paradas sin consultar al servidor.
 */
final class PlanDeViaje
{
    /** @param list<Parada> $paradas al menos dos: origen y destino */
    private function __construct(
        public readonly array $paradas,
        public readonly float $velocidadKmh,
    ) {}

    /**
     * @param list<array{nombre: string, lat: float, lng: float, km: float, origen?: string}> $puntos origen → destino, con km acumulados
     * @param int $semilla estabiliza la variación de velocidad (p. ej. el id de la salida)
     */
    public static function construir(array $puntos, \DateTimeInterface $partida, int $semilla, ParametrosSimulacion $p = new ParametrosSimulacion()): self
    {
        if (count($puntos) < 2) {
            throw new \InvalidArgumentException('Un plan necesita al menos origen y destino.');
        }

        $velocidad = $p->velocidadKmh * (1 + $p->variacionVelocidad * self::ruido($semilla));
        $total = $puntos[count($puntos) - 1]['km'];
        $descanso = $total >= $p->descansoDesdeKm ? self::indiceDeDescanso($puntos) : null;

        $t = $partida->getTimestamp();
        $paradas = [];
        foreach ($puntos as $i => $punto) {
            if ($i > 0) {
                $t += (int) round(($punto['km'] - $puntos[$i - 1]['km']) / $velocidad * 3600);
            }
            $llegada = $t;
            if ($i > 0 && $i < count($puntos) - 1) {
                $t += $p->paradaMinutos * 60 + ($i === $descanso ? $p->descansoMinutos * 60 : 0);
            }
            $paradas[] = new Parada($punto['nombre'], $punto['lat'], $punto['lng'], $punto['km'], $llegada, $t, $punto['origen'] ?? 'gps');
        }

        return new self($paradas, $velocidad);
    }

    public function partida(): int
    {
        return $this->paradas[0]->salida;
    }

    public function llegada(): int
    {
        return $this->paradas[count($this->paradas) - 1]->llegada;
    }

    public function posicionEn(int $instante): Posicion
    {
        $n = count($this->paradas);
        $origen = $this->paradas[0];
        $destino = $this->paradas[$n - 1];

        if ($instante <= $origen->salida) {
            return new Posicion($origen->lat, $origen->lng, $this->rumboDe(0), 0.0, 0.0, Posicion::POR_SALIR, $instante);
        }
        if ($instante >= $destino->llegada) {
            return new Posicion($destino->lat, $destino->lng, $this->rumboDe($n - 2), 0.0, $destino->km, Posicion::LLEGO, $instante);
        }

        for ($i = 1; $i < $n; $i++) {
            $anterior = $this->paradas[$i - 1];
            $siguiente = $this->paradas[$i];

            if ($instante < $siguiente->llegada) {
                $duracion = max(1, $siguiente->llegada - $anterior->salida);
                $f = ($instante - $anterior->salida) / $duracion;
                [$lat, $lng] = Geo::interpolar($anterior->lat, $anterior->lng, $siguiente->lat, $siguiente->lng, $f);
                $km = $anterior->km + ($siguiente->km - $anterior->km) * $f;

                return new Posicion($lat, $lng, $this->rumboDe($i - 1), $this->velocidadKmh, $km, Posicion::EN_RUTA, $instante);
            }
            if ($instante < $siguiente->salida) {
                return new Posicion($siguiente->lat, $siguiente->lng, $this->rumboDe($i), 0.0, $siguiente->km, Posicion::DETENIDO, $instante);
            }
        }

        return new Posicion($destino->lat, $destino->lng, 0.0, 0.0, $destino->km, Posicion::LLEGO, $instante);
    }

    /** Estación hacia la que va (o en la que está) el bus; null si ya llegó o no salió. */
    public function proximaParada(int $instante): ?Parada
    {
        foreach ($this->paradas as $i => $parada) {
            if ($i > 0 && $instante < $parada->llegada) {
                return $parada;
            }
        }

        return null;
    }

    private function rumboDe(int $tramo): float
    {
        $tramo = max(0, min(count($this->paradas) - 2, $tramo));
        $a = $this->paradas[$tramo];
        $b = $this->paradas[$tramo + 1];

        return Geo::rumbo($a->lat, $a->lng, $b->lat, $b->lng);
    }

    /** Pseudoaleatorio estable en [-1, 1] a partir de la semilla. */
    private static function ruido(int $semilla): float
    {
        $h = crc32((string) $semilla);

        return ($h % 20001) / 10000.0 - 1.0;
    }

    /** @param list<array{km: float}> $puntos */
    private static function indiceDeDescanso(array $puntos): ?int
    {
        $n = count($puntos);
        if ($n < 3) {
            return null;
        }
        $mitad = $puntos[$n - 1]['km'] / 2;
        $mejor = 1;
        for ($i = 1; $i < $n - 1; $i++) {
            if (abs($puntos[$i]['km'] - $mitad) < abs($puntos[$mejor]['km'] - $mitad)) {
                $mejor = $i;
            }
        }

        return $mejor;
    }
}
