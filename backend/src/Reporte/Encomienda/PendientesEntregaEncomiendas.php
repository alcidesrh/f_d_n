<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

/**
 * Encomiendas pendientes de entregar (puro): las que no se han entregado ni
 * anulado, por día de registro, con cuántos días llevan.
 */
final readonly class PendientesEntregaEncomiendas
{
    /** @var list<array{dia: \DateTimeImmutable, filas: list<LineaEncomienda>, totales: array<string, int>}> */
    public array $grupos;
    /** @var array{cantidad: int, bultos: int, total: int, pagado: int, porCobrar: int} */
    public array $totales;
    public int $masAntigua;

    /** @param list<LineaEncomienda> $lineas */
    public function __construct(
        public \DateTimeImmutable $hoy,
        public ?string $estacion,
        public ?string $empresa,
        array $lineas,
    ) {
        $grupos = [];
        foreach (Totales::agrupar($lineas, static fn(LineaEncomienda $l) => $l->diaCreada()) as $dia => $filas) {
            $grupos[] = ["dia" => new \DateTimeImmutable((string) $dia), "filas" => $filas, "totales" => Totales::de($filas)];
        }
        $this->grupos = $grupos;
        $this->totales = Totales::de($lineas);
        $this->masAntigua = $lineas === [] ? 0 : $this->dias($lineas[0]);
    }

    /** Días desde que se registró. */
    public function dias(LineaEncomienda $l): int
    {
        return (int) $l->creada->setTime(0, 0)->diff($this->hoy->setTime(0, 0))->days;
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        $porEstado = [];
        foreach ($this->grupos as $g) {
            foreach ($g["filas"] as $l) {
                $porEstado[$l->estado] = ($porEstado[$l->estado] ?? 0) + 1;
            }
        }

        return [...$this->totales, "masAntigua" => $this->masAntigua, "porEstado" => $porEstado];
    }
}
