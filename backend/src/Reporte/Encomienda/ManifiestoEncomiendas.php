<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

/**
 * Manifiesto de carga de una salida (puro): sus encomiendas por destino, con
 * el importe y espacio para la firma de quien la recibe.
 */
final readonly class ManifiestoEncomiendas
{
    /** @var list<array{destino: string, alias: ?string, filas: list<LineaEncomienda>, totales: array<string, int>}> */
    public array $grupos;
    /** @var array{cantidad: int, bultos: int, total: int, pagado: int, porCobrar: int} */
    public array $totales;

    /**
     * @param list<string> $pilotos
     * @param list<LineaEncomienda> $lineas
     */
    public function __construct(
        public int $salidaId,
        public \DateTimeImmutable $fecha,
        public string $ruta,
        public string $empresa,
        public ?string $bus,
        public array $pilotos,
        public ?string $destino,
        array $lineas,
    ) {
        $grupos = [];
        foreach (Totales::agrupar($lineas, static fn(LineaEncomienda $l) => $l->destino) as $destino => $filas) {
            $grupos[] = ["destino" => (string) $destino, "alias" => $filas[0]->destinoAlias, "filas" => $filas, "totales" => Totales::de($filas)];
        }
        usort($grupos, static fn(array $a, array $b) => strcmp($a["destino"], $b["destino"]));
        $this->grupos = $grupos;
        $this->totales = Totales::de($lineas);
    }

    public function titulo(): string
    {
        return $this->destino !== null && count($this->grupos) === 1 && $this->grupos[0]["alias"] !== null
            ? sprintf("Manifiesto de carga · %s (%s)", $this->destino, $this->grupos[0]["alias"])
            : ($this->destino !== null ? "Manifiesto de carga · {$this->destino}" : "Manifiesto de carga");
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        return [...$this->totales, "destinos" => count($this->grupos)];
    }
}
