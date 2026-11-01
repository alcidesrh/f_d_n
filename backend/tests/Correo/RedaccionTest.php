<?php

namespace App\Tests\Correo;

use App\Correo\Filtro;
use App\Correo\Redaccion;
use App\Entity\Enum\CarpetaCorreo;
use PHPUnit\Framework\TestCase;

/** Asunto, cita y destinatarios de respuestas y reenvíos; HTML y extracto; filtros del buzón. */
final class RedaccionTest extends TestCase
{
    private const YO = ["yo@sistemafdn.com", "yo@correo.sistemafdn.com"];

    public function testAsunto(): void
    {
        $this->assertSame("Re: Ventas", Redaccion::asunto("Ventas", Redaccion::RESPONDER));
        $this->assertSame("RE: Ventas", Redaccion::asunto("RE: Ventas", Redaccion::RESPONDER_TODOS));
        $this->assertSame("Rv: Ventas", Redaccion::asunto("Ventas", Redaccion::REENVIAR));
        $this->assertSame("Fwd: Ventas", Redaccion::asunto("Fwd: Ventas", Redaccion::REENVIAR));
        $this->assertSame("Re:", Redaccion::asunto("", Redaccion::RESPONDER));
    }

    public function testResponderVaAlRemitenteOASuReplyTo(): void
    {
        $de = ["direccion" => "juan@gmail.com", "nombre" => "Juan"];
        $para = [["direccion" => "yo@sistemafdn.com", "nombre" => "Yo"], ["direccion" => "ana@sistemafdn.com", "nombre" => "Ana"]];
        $cc = [["direccion" => "beto@x.com", "nombre" => null]];

        $r = Redaccion::destinatarios(Redaccion::RESPONDER, $de, null, $para, $cc, self::YO, false);
        $this->assertSame([[$de], []], [$r["para"], $r["cc"]]);

        $r = Redaccion::destinatarios(Redaccion::RESPONDER, $de, "lista@gmail.com", $para, $cc, self::YO, false);
        $this->assertSame([["direccion" => "lista@gmail.com", "nombre" => null]], $r["para"]);

        $r = Redaccion::destinatarios(Redaccion::RESPONDER_TODOS, $de, null, $para, $cc, self::YO, false);
        $this->assertSame([$de], $r["para"]);
        $this->assertSame(["ana@sistemafdn.com", "beto@x.com"], array_column($r["cc"], "direccion"), "Sin uno mismo");
    }

    public function testResponderUnEnviadoEsEscribirALosMismos(): void
    {
        $yo = ["direccion" => "yo@sistemafdn.com", "nombre" => "Yo"];
        $para = [["direccion" => "ana@sistemafdn.com", "nombre" => "Ana"]];
        $r = Redaccion::destinatarios(Redaccion::RESPONDER_TODOS, $yo, "yo@correo.sistemafdn.com", $para, [["direccion" => "c@x.com", "nombre" => null]], self::YO, true);
        $this->assertSame($para, $r["para"]);
        $this->assertSame(["c@x.com"], array_column($r["cc"], "direccion"));
    }

    public function testReenviarNoLlevaDestinatarios(): void
    {
        $r = Redaccion::destinatarios(Redaccion::REENVIAR, ["direccion" => "a@b.com", "nombre" => null], null, [], [], self::YO, false);
        $this->assertSame(["para" => [], "cc" => []], $r);
    }

    public function testCitaYExtracto(): void
    {
        $fecha = new \DateTimeImmutable("2026-11-02 15:30:00", new \DateTimeZone("America/Guatemala"));
        $cita = Redaccion::cita(Redaccion::RESPONDER, "Juan <juan@x.com>", $fecha, "Ventas", "", "Hola\n\nAdiós");
        $this->assertSame("\n\nEl lun, 2 nov 2026 a las 15:30, Juan <juan@x.com> escribió:\n> Hola\n>\n> Adiós", $cita);
        $this->assertSame("Gracias", Redaccion::extracto("Gracias" . $cita));
        $this->assertStringContainsString("Mensaje reenviado", Redaccion::cita(Redaccion::REENVIAR, "a", $fecha, "s", "p", "t"));
        $this->assertSame(str_repeat("a", 9) . "…", Redaccion::extracto(str_repeat("a", 20), 10));
    }

    public function testHtmlEscapaYEnlaza(): void
    {
        $html = Redaccion::html("<b>Hola</b>\nVer https://fdn.gt/x?a=1\n> citado");
        $this->assertStringContainsString("&lt;b&gt;Hola&lt;/b&gt;<br>", $html);
        $this->assertStringContainsString('<a href="https://fdn.gt/x?a=1">', $html);
        $this->assertStringContainsString('<span style="color:#64748b">citado</span>', $html);
        $this->assertSame("Hola\nmundo", Redaccion::textoDeHtml("<style>p{}</style><p>Hola</p><div>mundo</div>"));
    }

    public function testFiltroDeLaQuery(): void
    {
        $f = Filtro::deQuery(["carpeta" => "destacados", "noLeidos" => "1", "adjuntos" => "true", "origen" => "externos", "q" => "  ventas   Junio ", "antes" => "12"]);
        $this->assertTrue($f->esDestacados());
        $this->assertTrue($f->noLeidos && $f->conAdjuntos);
        $this->assertSame([Filtro::EXTERNOS, 12, ["ventas", "junio"]], [$f->origen, $f->antes, $f->palabras()]);

        $f = Filtro::deQuery(["carpeta" => "otra", "origen" => "x"]);
        $this->assertSame(CarpetaCorreo::Entrada, $f->carpeta);
        $this->assertNull($f->origen);
        $this->assertFalse($f->noLeidos);
    }
}
