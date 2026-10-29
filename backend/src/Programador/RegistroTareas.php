<?php

declare(strict_types=1);

namespace App\Programador;

use App\Entity\EstadoTarea;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Latido de las tareas (tabla `estado_tarea`, ADR-044). Por DBAL y no por el
 * EntityManager: se escribe también cuando la tarea falló y dejó el
 * EntityManager cerrado, y no se mezcla con lo que la tarea tenga sin flush.
 */
final class RegistroTareas
{
    private const MAX_ERROR = 2000;

    public function __construct(
        private readonly Connection $db,
        private readonly ClockInterface $reloj,
    ) {}

    public function empezo(Tarea $tarea): \DateTimeImmutable
    {
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());
        $this->db->executeStatement(
            'INSERT INTO estado_tarea (codigo, primer_inicio, ultimo_inicio, corridas, fallos_seguidos) VALUES (:codigo, :ahora, :ahora, 0, 0)
             ON CONFLICT (codigo) DO UPDATE SET ultimo_inicio = EXCLUDED.ultimo_inicio,
                 primer_inicio = COALESCE(estado_tarea.primer_inicio, EXCLUDED.primer_inicio)',
            ['codigo' => $tarea->value, 'ahora' => self::fecha($ahora)],
        );

        return $ahora;
    }

    /** @param array<string, mixed>|null $resumen */
    public function termino(Tarea $tarea, \DateTimeImmutable $inicio, ?array $resumen): void
    {
        $fin = $this->reloj->now();
        $this->db->executeStatement(
            'UPDATE estado_tarea SET ultimo_fin = :fin, ultimo_exito = :fin, duracion_ms = :ms, resultado = :ok,
                    error = NULL, resumen = :resumen, corridas = corridas + 1, fallos_seguidos = 0
              WHERE codigo = :codigo',
            [
                'codigo' => $tarea->value,
                'fin' => self::fecha($fin),
                'ms' => self::ms($inicio, $fin),
                'ok' => EstadoTarea::OK,
                'resumen' => $resumen === null ? null : json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function fallo(Tarea $tarea, \DateTimeImmutable $inicio, \Throwable $e): void
    {
        $fin = $this->reloj->now();
        $this->db->executeStatement(
            'UPDATE estado_tarea SET ultimo_fin = :fin, duracion_ms = :ms, resultado = :error, error = :mensaje,
                    corridas = corridas + 1, fallos_seguidos = fallos_seguidos + 1
              WHERE codigo = :codigo',
            [
                'codigo' => $tarea->value,
                'fin' => self::fecha($fin),
                'ms' => self::ms($inicio, $fin),
                'error' => EstadoTarea::ERROR,
                'mensaje' => mb_substr(sprintf('%s: %s', (new \ReflectionClass($e))->getShortName(), $e->getMessage()), 0, self::MAX_ERROR),
            ],
        );
    }

    public function omitida(Tarea $tarea): void
    {
        $this->db->executeStatement(
            'INSERT INTO estado_tarea (codigo, resultado, corridas, fallos_seguidos) VALUES (:codigo, :omitida, 0, 0)
             ON CONFLICT (codigo) DO UPDATE SET resultado = EXCLUDED.resultado',
            ['codigo' => $tarea->value, 'omitida' => EstadoTarea::OMITIDA],
        );
    }

    /**
     * El estado de cada tarea del catálogo (las que nunca corrieron, vacías).
     * Cada una sabe desde cuándo corre el programador (la primera tarea que
     * empezó): así una diaria que aún no llega a su hora no se da por detenida.
     *
     * @return list<EstadoDeTarea>
     */
    public function todas(): array
    {
        $filas = [];
        $desde = null;
        foreach ($this->db->fetchAllAssociative('SELECT * FROM estado_tarea') as $f) {
            $filas[(string) $f['codigo']] = $f;
            if (is_string($f['primer_inicio'] ?? null) && ($desde === null || $f['primer_inicio'] < $desde)) {
                $desde = $f['primer_inicio'];
            }
        }
        $desde = $desde === null ? null : new \DateTimeImmutable($desde);

        return array_map(static fn(Tarea $t) => EstadoDeTarea::deFila($t, $filas[$t->value] ?? null, $desde), Tarea::cases());
    }

    /**
     * Lo que el tablero necesita saber del programador.
     *
     * @return array{detenidas: list<string>, fallidos: int}
     */
    public function salud(\DateTimeImmutable $ahora): array
    {
        return [
            'detenidas' => array_values(array_map(
                static fn(EstadoDeTarea $e) => $e->tarea->nombre(),
                array_filter($this->todas(), static fn(EstadoDeTarea $e) => $e->detenida($ahora)),
            )),
            'fallidos' => $this->fallidos(),
        ];
    }

    /** Mensajes que agotaron sus reintentos (cola `failed`), por clase. */
    public function fallidos(): int
    {
        return (int) $this->db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'");
    }

    private static function fecha(\DateTimeInterface $d): string
    {
        return $d->format('Y-m-d H:i:s');
    }

    private static function ms(\DateTimeInterface $inicio, \DateTimeInterface $fin): int
    {
        return max(0, (int) round(((float) $fin->format('U.u') - (float) $inicio->format('U.u')) * 1000));
    }
}
