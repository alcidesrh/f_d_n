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

    public function testNombreSaleDeNombreNoDeDescripcion(): void
    {
        $ruta = [
            "codigo" => "RUT101",
            "nombre" => "SANTA ELENA-GUATE,ZONA1",
            "descripcion" => "Ruta de Santa Elena Petén a Guatemala Zona 1, solo aplica a Mayas de Oro",
        ];

        self::assertSame("Santa Elena-Guate,Zona1", RutasLegado::nombre($ruta));
    }

    public function testSinNombreUsaDescripcionYSinAmbosElCodigo(): void
    {
        self::assertSame("Ruta de xela", RutasLegado::nombre(["codigo" => "R1", "nombre" => " ", "descripcion" => "Ruta de xela"]));
        self::assertSame("R1", RutasLegado::nombre(["codigo" => "R1", "nombre" => null, "descripcion" => null]));
    }
}
