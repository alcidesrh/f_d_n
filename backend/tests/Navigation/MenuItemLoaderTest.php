<?php

declare(strict_types=1);

namespace App\Tests\Navigation;

use App\Entity\Icon;
use App\Entity\MenuItem;
use App\Entity\VueRoute;
use App\Navigation\MenuItemLoader;
use PHPUnit\Framework\TestCase;

final class MenuItemLoaderTest extends TestCase
{
    private static function item(?VueRoute $route): MenuItem
    {
        $icon = (new Icon())->setIcon('folder-outline');

        return (new MenuItem())
            ->setId(7)
            ->setNombre('Reporte')
            ->setIcon($icon)
            ->setRoute($route);
    }

    public function testUnGrupoSerializaSinRuta(): void
    {
        $serialized = MenuItemLoader::serialize(self::item(null));

        $this->assertSame('Reporte', $serialized['label']);
        $this->assertSame('folder-outline', $serialized['icon']);
        $this->assertNull($serialized['route']);
    }

    public function testUnItemConRutaSerializaSuRuta(): void
    {
        $route = (new VueRoute())
            ->setId(3)
            ->setVueRouteName('reporte-ventas')
            ->setPath('/reporte/ventas')
            ->setParams(null);

        $serialized = MenuItemLoader::serialize(self::item($route));

        $this->assertSame('reporte-ventas', $serialized['route']['name']);
        $this->assertSame('/reporte/ventas', $serialized['route']['path']);
        $this->assertSame([], $serialized['route']['params']);
    }
}
