<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\Push\AvisosPush;
use App\Chat\Push\Fcm;
use PHPUnit\Framework\TestCase;

final class FcmTest extends TestCase
{
    private const CONTENIDO = ["titulo" => "Ana López", "cuerpo" => "¿Salió el bus de las 8?", "url" => "/chat/7", "etiqueta" => "chat-7"];

    public function testElMensajeLlevaLaNotificacionYLoQueAbreAlTocarla(): void
    {
        $m = Fcm::mensaje("token-del-telefono", self::CONTENIDO)["message"];

        $this->assertSame("token-del-telefono", $m["token"]);
        $this->assertSame(["title" => "Ana López", "body" => "¿Salió el bus de las 8?"], $m["notification"]);
        $this->assertSame(["url" => "/chat/7", "etiqueta" => "chat-7"], $m["data"]);
        // La de la misma conversación reemplaza a la anterior, en el canal que crea la app.
        $this->assertSame(["tag" => "chat-7", "channel_id" => Fcm::CANAL], $m["android"]["notification"]);
        $this->assertSame("HIGH", $m["android"]["priority"]);
    }

    public function testElJwtVaFirmadoConLaClaveDeLaCuenta(): void
    {
        $clave = openssl_pkey_new(["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($clave);
        openssl_pkey_export($clave, $privada);
        $publica = openssl_pkey_get_details($clave)["key"];

        $jwt = Fcm::jwt(["client_email" => "fdn@proyecto.iam.gserviceaccount.com", "private_key" => $privada], 1_800_000_000);
        [$cabecera, $cuerpo, $firma] = explode(".", $jwt);
        $leer = static fn (string $parte): string => (string) base64_decode(strtr($parte, "-_", "+/"), true);

        $this->assertSame(["alg" => "RS256", "typ" => "JWT"], json_decode($leer($cabecera), true));
        $this->assertSame(
            [
                "iss" => "fdn@proyecto.iam.gserviceaccount.com",
                "scope" => "https://www.googleapis.com/auth/firebase.messaging",
                "aud" => "https://oauth2.googleapis.com/token",
                "iat" => 1_800_000_000,
                "exp" => 1_800_003_600,
            ],
            json_decode($leer($cuerpo), true),
        );
        $this->assertSame(1, openssl_verify($cabecera . "." . $cuerpo, $leer($firma), $publica, OPENSSL_ALGO_SHA256));
    }

    public function testVencidoSoloCuandoFirebaseYaNoConoceElToken(): void
    {
        $this->assertTrue(Fcm::vencido(404, ["status" => "NOT_FOUND", "details" => [["errorCode" => "UNREGISTERED"]]]));
        $this->assertTrue(Fcm::vencido(404, []));
        $this->assertTrue(Fcm::vencido(400, ["message" => "The registration token is not a valid FCM registration token", "details" => [["errorCode" => "INVALID_ARGUMENT"]]]));
        // Un mensaje mal armado no es culpa del teléfono: no se borra.
        $this->assertFalse(Fcm::vencido(400, ["message" => "Invalid JSON payload received.", "details" => [["errorCode" => "INVALID_ARGUMENT"]]]));
        $this->assertFalse(Fcm::vencido(429, ["details" => [["errorCode" => "QUOTA_EXCEEDED"]]]));
        $this->assertFalse(Fcm::vencido(503, []));
    }

    public function testTokenDeRegistro(): void
    {
        $this->assertTrue(AvisosPush::tokenValido("dX3k_9aB-cD:APA91b" . str_repeat("Zq0", 40)));
        $this->assertFalse(AvisosPush::tokenValido("corto"));
        $this->assertFalse(AvisosPush::tokenValido(str_repeat("a", 501)));
        $this->assertFalse(AvisosPush::tokenValido("https://backend/api/usuarios?x=" . str_repeat("a", 30)));
    }
}
