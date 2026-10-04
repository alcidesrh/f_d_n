<?php

declare(strict_types=1);

namespace App\Salida\Manifiesto;

/**
 * Lo que dicen los manifiestos de una salida (puro): encabezado y boletos
 * vivos (no anulados ni reasignados), por asiento.
 */
final readonly class Manifiesto
{
    /** @var list<Pasajero> */
    public array $pasajeros;

    /**
     * @param list<Pasajero> $pasajeros
     * @param list<string>   $pilotos    piloto y copiloto del bus (`N/D` si falta)
     */
    public function __construct(
        public int $salidaId,
        public \DateTimeImmutable $fecha,
        public string $estacion,
        public string $ruta,
        public string $empresa,
        public ?string $bus,
        public array $pilotos,
        array $pasajeros,
    ) {
        usort($pasajeros, static fn(Pasajero $a, Pasajero $b) => [$a->asiento, $a->boletoId] <=> [$b->asiento, $b->boletoId]);
        $this->pasajeros = $pasajeros;
    }

    /**
     * Pasajeros por lugar de emisión, en orden alfabético y con `—` al final.
     *
     * @return array<string, list<Pasajero>>
     */
    public function porEmision(): array
    {
        $grupos = [];
        foreach ($this->pasajeros as $p) {
            $grupos[$p->emitidoEn][] = $p;
        }
        uksort($grupos, static fn(string $a, string $b) => [$a === "—", $a] <=> [$b === "—", $b]);

        return $grupos;
    }

    /** @param list<Pasajero> $pasajeros */
    public static function cobrado(array $pasajeros): int
    {
        return array_sum(array_map(static fn(Pasajero $p) => $p->cobrado(), $pasajeros));
    }

    public function moneda(): string
    {
        return $this->pasajeros[0]->moneda ?? "GTQ";
    }

    public static function importe(int $centavos, string $moneda = "GTQ"): string
    {
        return sprintf("%s %s", $moneda === "GTQ" ? "Q" : $moneda, number_format($centavos / 100, 2));
    }
}
