<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\IdentidadCliente;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentidadClienteTest extends TestCase
{
    public function testLaClaveIgnoraAcentosSignosYMayusculas(): void
    {
        $this->assertSame("jose nandu garcia", IdentidadCliente::clave("  José  Ñandú, GARCÍA.- "));
    }

    /** @return iterable<array{?string, ?string}> */
    public static function nombres(): iterable
    {
        yield "espacios de más" => ["Luvi  Valdez ", "Luvi Valdez"];
        yield "cola de signos" => ["GUILLERMO GARCIA,,.,.,", "GUILLERMO GARCIA"];
        yield "guion al final" => ["PEREZ.-", "PEREZ"];
        yield "un punto solo se queda" => ["Mario Pérez Jr.", "Mario Pérez Jr."];
        yield "solo signos" => [" .- ", null];
        yield "vacío" => [null, null];
    }

    #[DataProvider("nombres")]
    public function testElNombreSeGuardaLimpio(?string $escrito, ?string $guardado): void
    {
        $this->assertSame($guardado, IdentidadCliente::nombre($escrito));
    }

    public function testElTelefonoSeGuardaConSoloDigitos(): void
    {
        $this->assertSame("47893304", IdentidadCliente::telefono("tel.-4789-3304"));
        $this->assertSame("+50258087471", IdentidadCliente::telefono("+502 5808 7471"));
        $this->assertNull(IdentidadCliente::telefono("tel.-"));
    }

    public function testElTelefonoSeComparaSinElCodigoDelPais(): void
    {
        $this->assertSame("58087471", IdentidadCliente::telefonoComparable("+502 5808-7471"));
        $this->assertSame("58087471", IdentidadCliente::telefonoComparable("5808 7471"));
        $this->assertNull(IdentidadCliente::telefonoComparable("5808"));
    }

    public function testElDocumentoSeGuardaSinSeparadores(): void
    {
        $this->assertSame("1234567890101", IdentidadCliente::documento("1234 56789 0101"));
        $this->assertSame("A1234567", IdentidadCliente::documento("a-123.4567"));
        $this->assertNull(IdentidadCliente::documento(" - "));
    }

    public function testCuiConSuDigitoVerificador(): void
    {
        // 1·2 + 2·3 + … + 8·9 = 240 y 240 mod 11 = 9: el noveno dígito.
        $this->assertTrue(IdentidadCliente::cuiValido("1234567890101"));
        $this->assertFalse(IdentidadCliente::cuiValido("1234567800101"), "verificador equivocado");
        $this->assertFalse(IdentidadCliente::cuiValido("123456789010"), "12 dígitos");
        $this->assertFalse(IdentidadCliente::cuiValido("123456789010A"));
    }
}
