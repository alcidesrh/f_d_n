<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\BoletoTarifa;
use App\GraphQL\Type\Definition\DateType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Lo que manda el formulario por GraphQL (fechas `Y-m-d` por el escalar `Date`)
 * tiene que llegar a la tarifa en la vigencia y en el horario.
 */
final class BoletoTarifaVigenciaTest extends KernelTestCase
{
    public function testLaVigenciaYElHorarioLlegandDesdeElEscalarDate(): void
    {
        self::bootKernel();
        $serializador = self::getContainer()->get(SerializerInterface::class);
        $fecha = new DateType();

        $tarifa = $serializador->denormalize([
            'nombre' => 'Prueba',
            'vigenteDesde' => $fecha->parseValue('2026-10-31'),
            'horaDesde' => $fecha->parseValue('2026-10-31'),
            'horaHasta' => null,
        ], BoletoTarifa::class, 'json');

        $this->assertSame('2026-10-31', $tarifa->getVigenteDesde()->format('Y-m-d'));
        $this->assertSame('2026-10-31', $tarifa->getHoraDesde()?->format('Y-m-d'));
        $this->assertNull($tarifa->getHoraHasta());
    }

    public function testRechazaUnaFechaQueNoEsYMD(): void
    {
        $this->expectException(\GraphQL\Error\Error::class);
        (new DateType())->parseValue('31/10/2026');
    }
}
