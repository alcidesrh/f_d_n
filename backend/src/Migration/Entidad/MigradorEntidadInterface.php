<?php

declare(strict_types=1);

namespace App\Migration\Entidad;

use App\Migration\Job\Progreso;
use Symfony\Component\Console\Output\OutputInterface;

interface MigradorEntidadInterface
{
    public function nombre(): string;

    public function etiqueta(): string;

    /** @return string[] Nombres de entidades que deben migrarse antes que esta. */
    public function dependencias(): array;

    public function tablaFuente(): ?string;

    /** @return string[] Tablas del backend nuevo afectadas. */
    public function tablasDestino(): array;

    public function soportaRangoFechas(): bool;

    public function soportaCantidad(): bool;

    /**
     * Total aproximado de registros fuente (legado) para el rango/opciones dadas.
     * Devuelve -1 cuando no aplica.
     */
    public function totalFuente(Especificacion $es): int;

    /**
     * Migra la(s) entidad(es) hacia la nueva BD.
     *
     * Devuelve contadores ["nombre_entidad" => n, ...].
     * Debe respetar Progreso::debeCancelar() y devolver lo antes posible al cancelar.
     */
    public function migrar(
        Especificacion $es,
        ?OutputInterface $output,
        Progreso $progreso,
    ): array;
}
