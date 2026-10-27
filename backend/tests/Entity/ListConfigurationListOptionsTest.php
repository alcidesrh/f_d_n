<?php

namespace App\Tests\Entity;

use App\Entity\CollectionFieldConfig;
use App\Entity\EntityConfiguration;
use App\Entity\ListConfiguration;
use PHPUnit\Framework\TestCase;

final class ListConfigurationListOptionsTest extends TestCase
{
    public function testGuardaSoloLasOpcionesValidas(): void
    {
        $config = new ListConfiguration(new EntityConfiguration('Bus'), 'Por defecto');
        $config->setListOptions([
            'pageSize' => 25,
            'pageSizes' => [10, 25, 50],
            'density' => 'compact',
            'filterMode' => 'xor',
            'selectable' => 'si',
            'inlineEdit' => false,
            'create' => false,
            'edit' => 'no',
            'delete' => true,
            'otra' => 1,
        ]);

        self::assertSame(
            ['pageSize' => 25, 'pageSizes' => [10, 25, 50], 'density' => 'compact', 'inlineEdit' => false, 'create' => false, 'delete' => true],
            $config->getListOptions(),
        );
    }

    public function testSinOpcionesValidasQuedaNull(): void
    {
        $config = new ListConfiguration(new EntityConfiguration('Bus'), 'Por defecto');
        $config->setListOptions(['pageSize' => 0, 'pageSizes' => [10, -1]]);
        self::assertNull($config->getListOptions());

        $config->setListOptions(null);
        self::assertNull($config->getListOptions());
    }

    public function testSincronizarNoPisaLaConfiguracionDeLaColumna(): void
    {
        $columna = new CollectionFieldConfig(['nombre', 'text']);
        self::assertFalse($columna->isSortable());
        self::assertFalse($columna->isFilterable());

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
