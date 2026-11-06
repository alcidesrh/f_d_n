<?php

declare(strict_types=1);

namespace App\Tests\Acceso\Suplantacion;

use App\Acceso\Suplantacion\Bloqueo;
use App\Acceso\Suplantacion\ModoChat;
use App\Acceso\Suplantacion\ReglasSuplantacion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReglasSuplantacionTest extends TestCase
{
    /** @param array<string, mixed> $cambios */
    private static function rechazo(array $cambios = []): ?string
    {
        $c = [
            "activa" => true,
            "actorEsSistema" => true,
            "actorYaSuplanta" => false,
            "mismoUsuario" => false,
            "objetivoActivo" => true,
            "objetivoEsSistema" => false,
            "exigeMotivo" => true,
            "motivo" => "Revisar su caja",
            ...$cambios,
        ];

        return ReglasSuplantacion::rechazo(...$c)?->codigo;
    }

    public function testElAdministradorDelSistemaEntraComoUnUsuarioActivo(): void
    {
        $this->assertNull(self::rechazo());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function rechazos(): iterable
    {
        yield "desactivada" => [["activa" => false], "suplantacion_desactivada"];
        yield "no es sistema" => [["actorEsSistema" => false], "solo_sistema"];
        yield "encadenar" => [["actorYaSuplanta" => true], "ya_suplanta"];
        yield "a sí mismo" => [["mismoUsuario" => true], "mismo_usuario"];
        yield "inactivo" => [["objetivoActivo" => false], "usuario_inactivo"];
        yield "otro sistema" => [["objetivoEsSistema" => true], "objetivo_sistema"];
        yield "sin motivo" => [["motivo" => "  "], "motivo_requerido"];
        yield "motivo corto" => [["motivo" => "ok"], "motivo_requerido"];
    }

    /** @param array<string, mixed> $cambios */
    #[DataProvider("rechazos")]
    public function testRechaza(array $cambios, string $codigo): void
    {
        $this->assertSame($codigo, self::rechazo($cambios));
    }

    public function testSinExigirloElMotivoEsOpcional(): void
    {
        $this->assertNull(self::rechazo(["exigeMotivo" => false, "motivo" => null]));
    }

    public function testLimpiaYAcotaElMotivo(): void
    {
        $this->assertSame("Revisar su caja", ReglasSuplantacion::motivo("  Revisar \n su   caja "));
        $this->assertNull(ReglasSuplantacion::motivo("   "));
        $this->assertSame(ReglasSuplantacion::MOTIVO_MAX, mb_strlen((string) ReglasSuplantacion::motivo(str_repeat("á", 400))));
    }

    /** @return iterable<string, array{string, string, ModoChat, Bloqueo}> */
    public static function peticiones(): iterable
    {
        yield "venta" => ["POST", "/api/venta/vender", ModoChat::LECTURA, Bloqueo::NINGUNO];
        yield "graphql" => ["POST", "/api/graphql", ModoChat::BLOQUEAR, Bloqueo::NINGUNO];
        yield "su token" => ["POST", "/auth", ModoChat::PERMITIR, Bloqueo::PROHIBIDO];
        yield "su contraseña" => ["POST", "/api/me/password", ModoChat::PERMITIR, Bloqueo::PROHIBIDO];
        yield "su cuenta" => ["PUT", "/api/me/cuenta", ModoChat::PERMITIR, Bloqueo::PROHIBIDO];
        yield "ver su cuenta" => ["GET", "/api/me/cuenta", ModoChat::PERMITIR, Bloqueo::NINGUNO];
        yield "su foto" => ["DELETE", "/api/me/foto", ModoChat::PERMITIR, Bloqueo::PROHIBIDO];
        yield "contraseña de otro" => ["POST", "/api/usuarios/9/password", ModoChat::PERMITIR, Bloqueo::PROHIBIDO];
        yield "encadenar" => ["POST", "/api/usuarios/9/suplantar", ModoChat::PERMITIR, Bloqueo::PROHIBIDO];
        yield "otorgar accesos" => ["POST", "/api/acceso/usuarios/9/asignaciones", ModoChat::PERMITIR, Bloqueo::PROHIBIDO];
        yield "ver accesos" => ["GET", "/api/acceso/roles", ModoChat::PERMITIR, Bloqueo::NINGUNO];
        yield "salir" => ["POST", "/api/suplantacion/salir", ModoChat::BLOQUEAR, Bloqueo::NINGUNO];
        yield "chat permitido" => ["POST", "/api/chat/canales/3/mensajes", ModoChat::PERMITIR, Bloqueo::NINGUNO];
        yield "chat lectura: leer" => ["GET", "/api/chat/canales/3/mensajes", ModoChat::LECTURA, Bloqueo::NINGUNO];
        yield "chat lectura: escribir" => ["POST", "/api/chat/canales/3/mensajes", ModoChat::LECTURA, Bloqueo::PROHIBIDO];
        yield "chat lectura: leído" => ["POST", "/api/chat/canales/3/leido", ModoChat::LECTURA, Bloqueo::IGNORAR];
        yield "chat lectura: presencia" => ["POST", "/api/chat/presencia", ModoChat::LECTURA, Bloqueo::IGNORAR];
        yield "chat permitido: leído" => ["POST", "/api/chat/canales/3/leido", ModoChat::PERMITIR, Bloqueo::NINGUNO];
        yield "push nunca" => ["POST", "/api/chat/push", ModoChat::PERMITIR, Bloqueo::IGNORAR];
        yield "chat bloqueado" => ["GET", "/api/chat/canales", ModoChat::BLOQUEAR, Bloqueo::PROHIBIDO];
        yield "correo lectura: enviar" => ["POST", "/api/correo/borradores/4/enviar", ModoChat::LECTURA, Bloqueo::PROHIBIDO];
        yield "correo bloqueado" => ["GET", "/api/correo/buzon", ModoChat::BLOQUEAR, Bloqueo::PROHIBIDO];
    }

    #[DataProvider("peticiones")]
    public function testBloqueo(string $metodo, string $ruta, ModoChat $chat, Bloqueo $esperado): void
    {
        $this->assertSame($esperado, ReglasSuplantacion::bloqueo($metodo, $ruta, $chat));
    }
}
