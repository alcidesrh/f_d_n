<?php

declare(strict_types=1);

namespace App\Programador;

/**
 * Lo que se sabe de la última corrida de una tarea (puro). `detenida()`: sin
 * una corrida buena dentro de su tolerancia, contada desde la última buena o,
 * si nunca terminó bien, desde que empezó la primera. Si nunca empezó, desde
 * que el programador está en marcha (`programadorDesde`); si el programador
 * nunca corrió nada (una instalación nueva, un entorno de desarrollo), no se
 * da por detenida.
 */
final readonly class EstadoDeTarea
{
    /** @param array<string, mixed>|null $resumen */
    public function __construct(
        public Tarea $tarea,
        public ?\DateTimeImmutable $programadorDesde = null,
        public ?\DateTimeImmutable $primerInicio = null,
        public ?\DateTimeImmutable $ultimoInicio = null,
        public ?\DateTimeImmutable $ultimoFin = null,
        public ?\DateTimeImmutable $ultimoExito = null,
        public ?int $duracionMs = null,
        public ?string $resultado = null,
        public ?string $error = null,
        public ?array $resumen = null,
        public int $corridas = 0,
        public int $fallosSeguidos = 0,
    ) {}

    /** @param array<string, mixed>|null $f */
    public static function deFila(Tarea $tarea, ?array $f, ?\DateTimeImmutable $programadorDesde = null): self
    {
        if ($f === null) {
            return new self($tarea, $programadorDesde);
        }
        $fecha = static fn(mixed $v) => is_string($v) ? new \DateTimeImmutable($v) : null;
        $resumen = is_string($f['resumen'] ?? null) ? json_decode($f['resumen'], true) : null;

        return new self(
            $tarea,
            $programadorDesde,
            $fecha($f['primer_inicio'] ?? null),
            $fecha($f['ultimo_inicio'] ?? null),
            $fecha($f['ultimo_fin'] ?? null),
            $fecha($f['ultimo_exito'] ?? null),
            isset($f['duracion_ms']) ? (int) $f['duracion_ms'] : null,
            isset($f['resultado']) ? (string) $f['resultado'] : null,
            isset($f['error']) ? (string) $f['error'] : null,
            is_array($resumen) ? $resumen : null,
            (int) ($f['corridas'] ?? 0),
            (int) ($f['fallos_seguidos'] ?? 0),
        );
    }

    /** ¿Lleva más de su tolerancia sin terminar bien? */
    public function detenida(\DateTimeImmutable $ahora): bool
    {
        $desde = $this->ultimoExito ?? $this->primerInicio ?? $this->ultimoInicio ?? $this->programadorDesde;

        return $desde !== null && $desde < $ahora->modify('-' . $this->tarea->tolerancia());
    }

    /** @return array<string, mixed> */
    public function toArray(\DateTimeImmutable $ahora): array
    {
        $fecha = static fn(?\DateTimeImmutable $d) => $d?->format(DATE_ATOM);

        return [
            'codigo' => $this->tarea->value,
            'nombre' => $this->tarea->nombre(),
            'frecuencia' => $this->tarea->frecuencia(),
            'ultimoInicio' => $fecha($this->ultimoInicio),
            'ultimoFin' => $fecha($this->ultimoFin),
            'ultimoExito' => $fecha($this->ultimoExito),
            'duracionMs' => $this->duracionMs,
            'resultado' => $this->resultado,
            'error' => $this->error,
            'resumen' => $this->resumen,
            'corridas' => $this->corridas,
            'fallosSeguidos' => $this->fallosSeguidos,
            'detenida' => $this->detenida($ahora),
        ];
    }
}
