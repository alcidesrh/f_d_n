<?php

declare(strict_types=1);

namespace App\Tests\Correo;

use App\Correo\Configuracion\AjustesCorreo;
use App\Correo\Configuracion\GestionCorreo;
use App\Correo\Envio\TransporteCorreo;
use App\Entity\ConfiguracionCorreo;
use App\Venta\CifradoCredenciales;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

final class TransporteCorreoTest extends TestCase
{
    private CifradoCredenciales $cifrado;

    protected function setUp(): void
    {
        $this->cifrado = new CifradoCredenciales("secreto-de-prueba");
    }

    public function testApagadoSiguePorElTransportePorDefecto(): void
    {
        $porDefecto = new NullTransport();
        $t = new TransporteCorreo($porDefecto, AjustesCorreo::fijos(new ConfiguracionCorreo()), $this->cifrado);

        $enviado = $t->send((new Email())->from("ana@sistemafdn.com")->to("x@ejemplo.com")->text("hola"));

        $this->assertNotNull($enviado);
    }

    public function testUsaElTokenDelDominioDelRemitente(): void
    {
        $config = (new ConfiguracionCorreo())->setTokens([
            "sistemafdn.com" => $this->cifrado->cifrar(TransporteCorreo::CONTEXTO, "token-sistema"),
            "transportesfuentedelnorte.com" => $this->cifrado->cifrar(TransporteCorreo::CONTEXTO, "token-pagina"),
        ]);
        $t = new TransporteCorreo(new NullTransport(), AjustesCorreo::fijos($config), $this->cifrado);

        $smtp = $t->mailpace($config, "Boletos@TransportesFuenteDelNorte.com");

        $this->assertInstanceOf(EsmtpTransport::class, $smtp);
        $this->assertSame("token-pagina", $smtp->getUsername());
        $this->assertSame($smtp, $t->mailpace($config, "otro@transportesfuentedelnorte.com"), "la conexión se reutiliza");
    }

    public function testSinTokenParaElDominioNoSale(): void
    {
        $config = (new ConfiguracionCorreo())->setTokens(["sistemafdn.com" => null]);
        $t = new TransporteCorreo(new NullTransport(), AjustesCorreo::fijos($config), $this->cifrado);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage("sistemafdn.com");
        $t->mailpace($config, "ana@sistemafdn.com");
    }

    public function testTokenCifradoConOtroSecretoEsIlegible(): void
    {
        $config = (new ConfiguracionCorreo())->setTokens([
            "sistemafdn.com" => (new CifradoCredenciales("otro"))->cifrar(TransporteCorreo::CONTEXTO, "x"),
        ]);
        $t = new TransporteCorreo(new NullTransport(), AjustesCorreo::fijos($config), $this->cifrado);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage("APP_SECRET");
        $t->mailpace($config, "ana@sistemafdn.com");
    }

    public function testDominiosValidos(): void
    {
        $this->assertTrue(GestionCorreo::esDominio("sistemafdn.com"));
        $this->assertTrue(GestionCorreo::esDominio("correo.sistemafdn.com"));
        $this->assertTrue(GestionCorreo::esDominio("smtp.mailpace.com"));
        $this->assertFalse(GestionCorreo::esDominio("sistemafdn"));
        $this->assertFalse(GestionCorreo::esDominio("ana@sistemafdn.com"));
        $this->assertFalse(GestionCorreo::esDominio("-mal.com"));
        $this->assertFalse(GestionCorreo::esDominio(""));
    }
}
