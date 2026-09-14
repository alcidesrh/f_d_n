<?php

declare(strict_types=1);

namespace App\Migration\Entidad;

use App\Migration\Job\Progreso;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Ejecuta una entidad resolviendo primero sus dependencias (orden topológico
 * DFS). El dedupe por legacy_id hace el grafo idempotente: re-ejecutar una
 * entidad nunca duplica datos.
 */
final class EjecutorEntidad
{
    public function __construct(
        private readonly RegistroMigradores $registro,
    ) {}

    /**
     * Migra la entidad pedida y (antes) sus dependencias.
     *
     * @return array<string, int>
     */
    public function ejecutar(
        string $entidad,
        Especificacion $es,
        ?OutputInterface $output,
        Progreso $progreso,
    ): array {
        if (!$this->registro->tiene($entidad)) {
            throw new \InvalidArgumentException(
                "Migrador de entidad desconocido: {$entidad}",
            );
        }

        $contadores = [];
        foreach ($this->orden($entidad) as $nombre) {
            $migrador = $this->registro->obtener($nombre);
            $resultado = $migrador->migrar(
                $es->para($nombre),
                $output,
                $progreso,
            );
            $contadores = array_merge($contadores, $resultado);
            if ($progreso->debeCancelar()) {
                break;
            }
        }

        return $contadores;
    }

    /**
     * Orden topológico (dependencias primero) para una entidad.
     *
     * @return string[]
     */
    public function orden(string $entidad): array
    {
        $orden = [];
        $visitando = [];
        $this->dfs($entidad, $orden, $visitando);

        return $orden;
    }

    /**
     * @param string[]       $orden
     * @param array<string, true> $visitando
     */
    private function dfs(string $nombre, array &$orden, array &$visitando): void
    {
        if (in_array($nombre, $orden, true)) {
            return;
        }
        if (isset($visitando[$nombre])) {
            throw new \RuntimeException(
                "Dependencia cíclica en el grafo de migración: {$nombre}",
            );
        }

        $visitando[$nombre] = true;
        foreach ($this->registro->obtener($nombre)->dependencias() as $dep) {
            $this->dfs($dep, $orden, $visitando);
        }
        unset($visitando[$nombre]);
        $orden[] = $nombre;
    }
}
