<?php

namespace App\Tests\Correo;

use App\Correo\Entrante\Mailpace;
use PHPUnit\Framework\TestCase;

/** El webhook de correo entrante de MailPace: firma Ed25519 del cuerpo y lectura del JSON. */
final class MailpaceTest extends TestCase
{
    public function testLaFirmaEsDelCuerpoTalCual(): void
    {
        $par = sodium_crypto_sign_keypair();
        $publica = base64_encode(sodium_crypto_sign_publickey($par));
        $cuerpo = '{"from":"a@b.com"}';
        $firma = base64_encode(sodium_crypto_sign_detached($cuerpo, sodium_crypto_sign_secretkey($par)));

        $this->assertTrue(Mailpace::firmaValida($cuerpo, $firma, $publica));
        $this->assertFalse(Mailpace::firmaValida($cuerpo . " ", $firma, $publica));
        $this->assertFalse(Mailpace::firmaValida($cuerpo, $firma, base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()))));
        $this->assertFalse(Mailpace::firmaValida($cuerpo, null, $publica));
        $this->assertFalse(Mailpace::firmaValida($cuerpo, $firma, ""), "Sin clave configurada no se acepta nada");
        $this->assertFalse(Mailpace::firmaValida($cuerpo, "no-es-base64!", $publica));
    }

    public function testLeeElCorreo(): void
    {
        $c = Mailpace::leer([
            "from" => "Juan Pérez <Juan@Gmail.com>",
            "to" => "ana@sistemafdn.com, Beto <beto@correo.sistemafdn.com>",
            "cc" => "otro@x.com",
            "subject" => " Reporte de ventas ",
            "text" => "Hola",
            "html" => "<p>Hola <img src=\"cid:logo1\"></p>",
            "messageId" => "<abc@gmail.com>",
            "inReplyTo" => "<prev@sistemafdn.com>",
            "replyTo" => "respuestas@gmail.com",
            "headers" => ["Subject: Reporte", "References: <a@x> <prev@sistemafdn.com>"],
            "attachments" => [
                ["filename" => "reporte.pdf", "contentType" => "application/pdf", "content" => base64_encode("%PDF-1.4"), "contentDisposition" => "attachment"],
                ["contentType" => "image/png", "content" => base64_encode("png"), "cid" => "logo1", "related" => true, "contentDisposition" => "inline"],
                ["filename" => "vacio.txt", "content" => ""],
            ],
        ]);

        $this->assertSame(["direccion" => "juan@gmail.com", "nombre" => "Juan Pérez"], $c->de);
        $this->assertSame(["ana@sistemafdn.com", "beto@correo.sistemafdn.com", "otro@x.com"], $c->destinatarios());
        $this->assertSame("Reporte de ventas", $c->asunto);
        $this->assertSame("abc@gmail.com", $c->messageId);
        $this->assertSame("prev@sistemafdn.com", $c->enRespuestaA);
        $this->assertSame("a@x prev@sistemafdn.com", $c->referencias);
        $this->assertSame("respuestas@gmail.com", $c->responderA);
        $this->assertCount(2, $c->adjuntos);
        $this->assertSame(["reporte.pdf", "application/pdf", "%PDF-1.4", null, false], [$c->adjuntos[0]["nombre"], $c->adjuntos[0]["tipo"], $c->adjuntos[0]["contenido"], $c->adjuntos[0]["contentId"], $c->adjuntos[0]["enLinea"]]);
        $this->assertSame(["logo1", true], [$c->adjuntos[1]["contentId"], $c->adjuntos[1]["enLinea"]]);
    }

    public function testSinRemitenteNoSeLee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Mailpace::leer(["to" => "ana@sistemafdn.com"]);
    }
}
