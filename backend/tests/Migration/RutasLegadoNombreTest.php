<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Migration\Salida\RutasLegado;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RutasLegadoNombreTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: string}> */
    public static function casos(): iterable
    {
        yield "palabras en mayúsculas" => ["GUATEMALA - SANTA ELENA", "Guatemala - Santa Elena"];
        yield "guion entre palabras" => ["XELA-SAN JOSE", "Xela-San Jose"];
        yield "acentos" => ["ÁLVARO OBREGÓN", "Álvaro Obregón"];
        yield "letra suelta intacta" => ["RUTA A B", "Ruta A B"];
        yield "mixto intacto" => ["Guatemala McDonald", "Guatemala McDonald"];
        yield "minúsculas intactas" => ["ruta de xela", "ruta de xela"];
        yield "vacío" => ["", ""];
    }

    #[DataProvider("casos")]
    public function testSinMayusculas(string $entrada, string $esperado): void
    {
        self::assertSame($esperado, RutasLegado::sinMayusculas($entrada));
    }
}
