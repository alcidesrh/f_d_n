<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\Push\AvisosPush;
use App\Entity\Enum\TipoCanalChat;
use PHPUnit\Framework\TestCase;

final class AvisosPushTest extends TestCase
{
    public function testDirectoMuestraQuienEscribeYElFragmento(): void
    {
        $this->assertSame(
            ["titulo" => "Ana López", "cuerpo" => "¿Salió el bus de las 8?", "url" => "/chat/7", "etiqueta" => "chat-7"],
            AvisosPush::contenido(7, TipoCanalChat::Directo, null, "Ana López", "¿Salió el bus de las 8?"),
        );
    }

    public function testGrupoMuestraElGrupoYElAutor(): void
    {
        $c = AvisosPush::contenido(3, TipoCanalChat::Grupo, "Taquillas Petén", "Ana López", "Listo");

        $this->assertSame("Taquillas Petén", $c["titulo"]);
        $this->assertSame("Ana López: Listo", $c["cuerpo"]);
    }

    public function testSistema(): void
    {
        $this->assertSame("Avisos del sistema", AvisosPush::contenido(1, TipoCanalChat::Sistema, null, null, "Saldo acreditado")["titulo"]);
    }

    public function testTruncaElFragmento(): void
    {
        $f = AvisosPush::fragmento(str_repeat("palabra ", 30));

        $this->assertSame(AvisosPush::MAX_FRAGMENTO, mb_strlen($f));
        $this->assertStringEndsWith("…", $f);
        $this->assertSame("una línea", AvisosPush::fragmento("  una\n\n línea "));
    }

    public function testSoloServiciosDePushDeLosNavegadores(): void
    {
        $this->assertTrue(AvisosPush::endpointValido("https://fcm.googleapis.com/fcm/send/abc"));
        $this->assertTrue(AvisosPush::endpointValido("https://updates.push.services.mozilla.com/wpush/v2/abc"));
        $this->assertTrue(AvisosPush::endpointValido("https://web.push.apple.com/abc"));
        $this->assertFalse(AvisosPush::endpointValido("http://fcm.googleapis.com/fcm/send/abc"));
        $this->assertFalse(AvisosPush::endpointValido("https://backend/api/usuarios"));
        $this->assertFalse(AvisosPush::endpointValido("https://evilfcm.googleapis.com.example.com/x"));
        $this->assertFalse(AvisosPush::endpointValido("https://fcm.googleapis.com:8443/x"));
    }
}
