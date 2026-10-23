<?php

declare(strict_types=1);

namespace App\Tests\Navigation;

use App\Entity\MenuItem;
use App\Entity\VueRoute;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class MenuItemDestinoTest extends TestCase
{
    /** @return list<string> mensajes de las violaciones sobre `path` */
    private static function erroresDePath(MenuItem $item): array
    {
        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($item);

        $mensajes = [];
        foreach ($violations as $violation) {
            if (in_array($violation->getPropertyPath(), ['path', ''], true)) {
                $mensajes[] = (string) $violation->getMessage();
            }
        }

        return $mensajes;
    }

    public function testUnEnlaceValidoNoTieneErrores(): void
    {
        $this->assertSame([], self::erroresDePath((new MenuItem())->setPath('/salidas/chequeo')));
    }

    public function testUnEnlaceSinBarraInicialNoSeGuarda(): void
    {
        $this->assertNotSame([], self::erroresDePath((new MenuItem())->setPath('salidas')));
    }

    public function testUnEnlaceConEspaciosNoSeGuarda(): void
    {
        $this->assertNotSame([], self::erroresDePath((new MenuItem())->setPath('/salidas chequeo')));
    }

    public function testNoPuedeNavegarARutaYEnlaceALaVez(): void
    {
        $item = (new MenuItem())
            ->setRoute((new VueRoute())->setVueRouteName('venta')->setPath('/venta'))
            ->setPath('/salidas');

        $this->assertNotSame([], self::erroresDePath($item));
    }
}
