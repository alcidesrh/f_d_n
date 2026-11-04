<?php

declare(strict_types=1);

namespace App\Doctrine\Schema;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Quita del esquema que genera Doctrine los índices simples que otro índice
 * de la misma tabla ya cubre por la izquierda: el de `salida_id` sobra si hay
 * uno único `(salida_id, orden)`. Doctrine crea un índice por cada clave
 * foránea salvo que otro tenga exactamente las mismas columnas, y cada índice
 * de más se paga en cada escritura (`lectura_gps`, `salida_parada`, el chat).
 *
 * Solo los btree comunes, sin condición; el que cubre puede ser único o la
 * clave primaria. Los `ex_*` (exclusión, ADR-030) no pasan por aquí.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema, connection: "default")]
final class IndicesCubiertos
{
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        foreach ($args->getSchema()->getTables() as $tabla) {
            foreach (self::cubiertos($tabla) as $nombre) {
                $tabla->dropIndex($nombre);
            }
        }
    }

    /** @return list<string> nombres de los índices que sobran */
    public static function cubiertos(Table $tabla): array
    {
        $prefijos = [];
        if (($primaria = $tabla->getPrimaryKeyConstraint()) !== null) {
            $prefijos[] = array_map(static fn($c) => $c->toString(), $primaria->getColumnNames());
        }
        $candidatos = [];
        foreach ($tabla->getIndexes() as $nombre => $indice) {
            if ($indice->isPrimary() || $indice->getPredicate() !== null) {
                continue;
            }
            $columnas = self::columnas($indice);
            $prefijos[] = $columnas;
            if ($indice->getType() === IndexType::REGULAR) {
                $candidatos[$nombre] = $columnas;
            }
        }

        $sobran = [];
        foreach ($candidatos as $nombre => $columnas) {
            foreach ($prefijos as $otro) {
                if (count($otro) > count($columnas) && array_slice($otro, 0, count($columnas)) === $columnas) {
                    $sobran[] = (string) $nombre;
                    break;
                }
            }
        }

        return $sobran;
    }

    /** @return list<string> */
    private static function columnas(Index $indice): array
    {
        return array_map(static fn(IndexedColumn $c) => strtolower($c->getColumnName()->toString()), $indice->getIndexedColumns());
    }
}
