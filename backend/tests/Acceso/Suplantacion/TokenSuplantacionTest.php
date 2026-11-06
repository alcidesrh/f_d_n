<?php

declare(strict_types=1);

namespace App\Tests\Acceso\Suplantacion;

use App\Acceso\Suplantacion\SuplantacionActual;
use App\Entity\ApiToken;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

final class TokenSuplantacionTest extends TestCase
{
    public function testElLoginDelUsuarioNoRecibeElTokenDeSuplantacion(): void
    {
        $admin = new Usuario();
        $usuario = new Usuario();
        $suplantacion = (new ApiToken())->setUsuario($usuario)->suplantacion($admin, "Revisar")->setActivo(true);
        $usuario->addApiToken($suplantacion);

        $this->assertNull($usuario->getToken());
        $this->assertNotSame($suplantacion->getToken(), $usuario->getValidTokenStrings());

        $propio = (new ApiToken())->setUsuario($usuario)->setActivo(true);
        $usuario->addApiToken($propio);
        $this->assertSame($propio, $usuario->getToken());
        $this->assertSame($propio->getToken(), $usuario->getValidTokenStrings());
    }

    public function testSoloRecuerdaLosTokensDeSuplantacion(): void
    {
        $admin = new Usuario();
        $actual = new SuplantacionActual();

        $actual->fijar((new ApiToken())->setActivo(true));
        $this->assertFalse($actual->activa());
        $this->assertNull($actual->actor());

        $actual->fijar((new ApiToken())->suplantacion($admin, null));
        $this->assertTrue($actual->activa());
        $this->assertSame($admin, $actual->actor());

        $actual->reset();
        $this->assertFalse($actual->activa());
    }
}
