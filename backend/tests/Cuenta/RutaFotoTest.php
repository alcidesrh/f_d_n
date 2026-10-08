<?php

namespace App\Tests\Cuenta;

use App\Controller\MiCuentaController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

/** El usuario `admin` del legado tiene id -1: la URL de su foto debe enrutarse. */
final class RutaFotoTest extends TestCase
{
    public function testLaRutaDeLaFotoAceptaIdsNegativos(): void
    {
        $ruta = (new \ReflectionMethod(MiCuentaController::class, "foto"))->getAttributes(Route::class)[0]->newInstance();

        $this->assertStringContainsString("{id<-?\\d+>}", $ruta->path);
    }
}
