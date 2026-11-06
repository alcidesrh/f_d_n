<?php

declare(strict_types=1);

namespace App\Tests\Navigation;

use App\Entity\Icon;
use App\Entity\MenuItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class MenuItemIconoTest extends TestCase
{
    /** @return list<string> mensajes de las violaciones sobre `icon` */
    private static function erroresDeIcono(MenuItem $item): array
    {
        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($item);

        $mensajes = [];
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'icon') {
                $mensajes[] = (string) $violation->getMessage();
            }
        }

        return $mensajes;
    }

    public function testUnItemSinIconoEsValido(): void
    {
        $item = (new MenuItem())->setNombre('Reporte');

        $this->assertSame([], self::erroresDeIcono($item));
    }

    public function testUnItemConIconoEsValido(): void
    {
        $item = (new MenuItem())->setNombre('Reporte')->setIcon((new Icon())->setIcon('folder-outline'));

        $this->assertSame([], self::erroresDeIcono($item));
    }
}
