<?php

namespace App\Tests\Correo;

use App\Correo\Direcciones;
use App\Correo\Dominios;
use PHPUnit\Framework\TestCase;

/** Leer listas de direcciones, la parte local de cada usuario y qué es "de la casa". */
final class DireccionesTest extends TestCase
{
    public function testLeeUnaListaComoLaDeUnaCabecera(): void
    {
        $this->assertSame([
            ["direccion" => "ana@x.com", "nombre" => "Ana"],
            ["direccion" => "juan@y.com", "nombre" => "Pérez, Juan"],
            ["direccion" => "z@w.com", "nombre" => null],
        ], Direcciones::leer('Ana <Ana@X.com>, "Pérez, Juan" <juan@y.com>; z@w.com, no-es-correo, z@w.com'));
        $this->assertSame([], Direcciones::leer(null));
        $this->assertSame([], Direcciones::leer("  "));
    }

    public function testDeEntradaAceptaObjetosOTextosYDescartaLoInvalido(): void
    {
        $this->assertSame(
            [["direccion" => "a@b.com", "nombre" => "A"], ["direccion" => "c@d.com", "nombre" => null]],
            Direcciones::deEntrada([["direccion" => " A@B.com ", "nombre" => "A"], "c@d.com", ["direccion" => "mala"], "a@b.com"]),
        );
    }

    public function testLaParteLocalSaleDelNombreDeUsuario(): void
    {
        $this->assertSame("jose.perez", Direcciones::localDe("José Pérez"));
        $this->assertSame("ana_lopez", Direcciones::localDe("Ana_Lopez"));
        $this->assertSame("taquilla.1", Direcciones::localDe("..taquilla#1.."));
        $this->assertSame("", Direcciones::localDe("###"));
    }

    public function testFormatear(): void
    {
        $this->assertSame('"Ana" <ana@x.com>', Direcciones::formatear("ana@x.com", 'An"a'));
        $this->assertSame("ana@x.com", Direcciones::formatear("ana@x.com", null));
    }

    public function testDeLaCasaEsElDominioPrincipalOElDeEntrada(): void
    {
        $d = Dominios::fijos("SistemaFDN.com", "correo.sistemafdn.com");
        $this->assertSame("ana", $d->local("Ana@sistemafdn.com"));
        $this->assertSame("ana", $d->local("ana+reportes@correo.sistemafdn.com"));
        $this->assertNull($d->local("ana@gmail.com"));
        $this->assertSame("ana@sistemafdn.com", $d->direccion("ana"));
        $this->assertSame("ana@correo.sistemafdn.com", $d->responderA("ana"));
        $this->assertNull(Dominios::fijos("sistemafdn.com", "")->responderA("ana"));
    }
}
