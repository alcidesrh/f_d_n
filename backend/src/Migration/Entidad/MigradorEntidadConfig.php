<?php

declare(strict_types=1);

namespace App\Migration\Entidad;

use App\Migration\Job\Progreso;
use App\Services\EntityConfigSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sincroniza la metadatos de entidades hacia EntityConfiguration
 * (mismo recorrido que el paso 4 de `app:migrar:todo`).
 */
final class MigradorEntidadConfig implements MigradorEntidadInterface
{
    private const EXCLUIDAS = [
        "EntityConfiguration",
        "CollectionFieldConfig",
        "FormFieldConfig",
    ];

    public function __construct(
        private readonly EntityConfigSynchronizer $synchronizer,
        private readonly EntityManagerInterface $em,
    ) {}

    public function nombre(): string
    {
        return "config";
    }

    public function etiqueta(): string
    {
        return "Configuraciones de entidad";
    }

    public function dependencias(): array
    {
        return [];
    }

    public function tablaFuente(): ?string
    {
        return null;
    }

    public function tablasDestino(): array
    {
        return ["entity_configuration"];
    }

    public function soportaRangoFechas(): bool
    {
        return false;
    }

    public function soportaCantidad(): bool
    {
        return false;
    }

    public function totalFuente(Especificacion $es): int
    {
        return -1;
    }

    public function migrar(
        Especificacion $es,
        ?OutputInterface $output,
        Progreso $progreso,
    ): array {
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $pendientes = [];
        foreach ($metadata as $m) {
            if ($m->isMappedSuperclass || $m->isEmbeddedClass) {
                continue;
            }
            $short = $m->getReflectionClass()->getShortName();
            if (!in_array($short, self::EXCLUIDAS, true)) {
                $pendientes[] = $short;
            }
        }

        $total = count($pendientes);
        $progreso->informar(
            0,
            $total,
            [],
            "Sincronizando EntityConfiguration…",
        );
        $synced = 0;

        foreach ($pendientes as $short) {
            if ($progreso->debeCancelar()) {
                break;
            }
            $this->synchronizer->syncEntity($short);
            $synced++;
            $progreso->informar(
                $synced,
                $total,
                ["config" => $synced],
                "Sincronizada {$short}",
            );
        }

        if ($output) {
            $output->writeln(
                sprintf("<info>✓ %d entidades sincronizadas</info>", $synced),
            );
        }

        return ["config" => $synced];
    }
}
