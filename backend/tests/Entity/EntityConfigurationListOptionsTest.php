<?php

namespace App\Tests\Entity;

use App\Entity\CollectionFieldConfig;
use App\Entity\EntityConfiguration;
use PHPUnit\Framework\TestCase;

final class EntityConfigurationListOptionsTest extends TestCase
{
    public function testGuardaSoloLasOpcionesValidas(): void
    {
        $config = new EntityConfiguration('Bus');
        $config->setListOptions([
            'pageSize' => 25,
            'pageSizes' => [10, 25, 50],
            'density' => 'compact',
            'filterMode' => 'xor',
            'selectable' => 'si',
            'inlineEdit' => false,
            'otra' => 1,
        ]);

        self::assertSame(
            ['pageSize' => 25, 'pageSizes' => [10, 25, 50], 'density' => 'compact', 'inlineEdit' => false],
            $config->getListOptions(),
        );
    }

    public function testSinOpcionesValidasQuedaNull(): void
    {
        $config = new EntityConfiguration('Bus');
        $config->setListOptions(['pageSize' => 0, 'pageSizes' => [10, -1]]);
        self::assertNull($config->getListOptions());

        $config->setListOptions(null);
        self::assertNull($config->getListOptions());
    }

    public function testSincronizarNoPisaLaConfiguracionDeLaColumna(): void
    {
        $columna = new CollectionFieldConfig(['nombre', 'text']);
        self::assertNull($columna->isSortable());
        self::assertNull($columna->isFilterable());

        $columna->setSortable(false)->setFilterable(true)->setLabel('Nombre')->setVisible(false)->setWidth(' 12rem ');
        $columna->setData(['nombre', 'datetime']);

        self::assertFalse($columna->isSortable());
        self::assertTrue($columna->isFilterable());
        self::assertSame('Nombre', $columna->getLabel());
        self::assertFalse($columna->isVisible());
        self::assertSame('12rem', $columna->getWidth());
        self::assertSame('date', $columna->getKind());
    }
}
