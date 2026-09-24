<?php

namespace App\Tests\Entity;

use App\Entity\Icon;
use App\Entity\Status;
use PHPUnit\Framework\TestCase;

final class BaseLabelTest extends TestCase
{
    public function testLabelSaleDeNameYSinNombreDelId(): void
    {
        $icon = (new Icon())->setIcon('bus')->setName('Autobús');
        $this->assertSame('Autobús', $icon->getLabel());

        $sinNombre = (new Icon())->setIcon('bus');
        $sinNombre->setId(7);
        $this->assertSame('7', (string) $sinNombre->getLabel());
    }

    public function testLabelNoEsEscribible(): void
    {
        $this->assertFalse(property_exists(Status::class, 'label'), 'label es derivado: no debe existir como propiedad');
    }
}
