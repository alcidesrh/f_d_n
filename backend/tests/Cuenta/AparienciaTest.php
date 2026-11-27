<?php

namespace App\Tests\Cuenta;

use App\Cuenta\Apariencia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AparienciaTest extends TestCase
{
    public function testGuardaLosAjustesConLaFormaEsperada(): void
    {
        $entrada = ['modo' => 'dark', 'primario' => 'blue', 'esquinas' => 1.5, 'texto' => 16, 'algo' => true];

        self::assertSame($entrada, Apariencia::limpiar($entrada));
    }

    public function testDescartaClavesYValoresQueNoSonAjustes(): void
    {
        $limpia = Apariencia::limpiar([
            'modo' => 'dark',
            'Mayuscula' => 'x',
            'con espacio' => 'x',
            'html' => '<script>',
            'largo' => str_repeat('a', 41),
            'enorme' => 1e9,
            'lista' => ['a'],
            'nulo' => null,
            7 => 'numerica',
        ]);

        self::assertSame(['modo' => 'dark'], $limpia);
    }

    public function testAcotaCuantosAjustesGuarda(): void
    {
        $entrada = [];
        for ($i = 0; $i < 60; $i++) {
            $entrada["a$i"] = $i;
        }

        self::assertCount(Apariencia::MAX_AJUSTES, Apariencia::limpiar($entrada));
    }

    public function testUnObjetoVacioEsValido(): void
    {
        self::assertSame([], Apariencia::limpiar([]));
    }

    #[DataProvider('noObjetos')]
    public function testRechazaLoQueNoEsUnObjeto(mixed $entrada): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Apariencia::limpiar($entrada);
    }

    /** @return iterable<string, array{mixed}> */
    public static function noObjetos(): iterable
    {
        yield 'texto' => ['dark'];
        yield 'número' => [3];
        yield 'nulo' => [null];
        yield 'lista' => [['dark', 'blue']];
    }
}
