<?php

declare(strict_types=1);

namespace App\Migration\Entidad;

use App\Migration\Job\Progreso;
use App\Migration\MigradorEstaticos;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Migrador genérico para una entidad estática (empresa, estacion, cliente, ...).
 * Delega en MigradorEstaticos (dedupe por id/legacy_id).
 */
final class MigradorEntidadEstatica implements MigradorEntidadInterface
{
    public function __construct(
        private readonly MigradorEstaticos $estaticos,
        private readonly string $nombre,
        private readonly string $etiqueta,
        private readonly string $tablaFuente,
        private readonly array $tablasDestino,
        private readonly array $dependencias = [],
        private readonly bool $soportaCantidad = false,
        private readonly ?string $sqlConteoFuente = null,
        #[Target("oldPdo")] private readonly ?\PDO $oldPdo = null,
    ) {}

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function etiqueta(): string
    {
        return $this->etiqueta;
    }

    public function dependencias(): array
    {
        return $this->dependencias;
    }

    public function tablaFuente(): ?string
    {
        return $this->tablaFuente;
    }

    public function tablasDestino(): array
    {
        return $this->tablasDestino;
    }

    public function soportaRangoFechas(): bool
    {
        return false;
    }

    public function soportaCantidad(): bool
    {
        return $this->soportaCantidad;
    }

    public function totalFuente(Especificacion $es): int
    {
        if (null === $this->oldPdo) {
            return -1;
        }

        try {
            $sql =
                $this->sqlConteoFuente ??
                "SELECT COUNT(*) FROM {$this->tablaFuente}";
            $stmt = $this->oldPdo->prepare($sql);
            $stmt->execute();
            $total = (int) $stmt->fetchColumn();

            if ($this->soportaCantidad && null !== $es->cantidad) {
                return min($total, $es->cantidad);
            }

            return $total;
        } catch (\Throwable) {
            return -1;
        }
    }

    public function migrar(
        Especificacion $es,
        ?OutputInterface $output,
        Progreso $progreso,
    ): array {
        $progreso->informar(0, 1, [], "Migrando {$this->etiqueta}…");
        $limite = $this->soportaCantidad ? $es->cantidad : null;
        $contadores = $this->estaticos->migrarEntidad(
            $this->nombre,
            $output,
            $progreso,
            $limite,
        );
        $progreso->informar(1, 1, $contadores, "{$this->etiqueta} completada");

        return $contadores;
    }
}
