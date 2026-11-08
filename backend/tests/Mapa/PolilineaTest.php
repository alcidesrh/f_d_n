<?php

declare(strict_types=1);

namespace App\Tests\Mapa;

use App\Mapa\Polilinea;
use PHPUnit\Framework\TestCase;

final class PolilineaTest extends TestCase
{
    public function testDecodificaElEjemploDeGoogleConCincoDecimales(): void
    {
        $puntos = Polilinea::decodificar('_p~iF~ps|U_ulLnnqC_mqNvxq`@', 5);

        $this->assertEqualsWithDelta([[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]], $puntos, 1e-9);
    }

    public function testDecodificaConSeisDecimalesComoValhalla(): void
    {
        $puntos = Polilinea::decodificar('eyf|ZbgbskDkDjFmz}q@oqjG');

        $this->assertEqualsWithDelta([[14.634915, -90.506882], [14.635001, -90.507], [15.47, -90.37]], $puntos, 1e-9);
    }

    public function testVaciaNoTienePuntosEIncompletaEsUnError(): void
    {
        $this->assertSame([], Polilinea::decodificar(''));
        $this->expectException(\InvalidArgumentException::class);
        Polilinea::decodificar('eyf|ZbgbskDkDjFmz}q');
    }
}
