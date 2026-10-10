<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\Presencia;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class PresenciaTest extends TestCase
{
    public function testSoloLosQueLatieronEstanEnLinea(): void
    {
        $presencia = new Presencia(new ArrayAdapter());
        $presencia->latir(1, "a");
        $presencia->latir(3, "a");

        $this->assertEqualsCanonicalizing([1, 3], $presencia->enLinea([1, 2, 3]));
        $this->assertSame([], $presencia->enLinea([]));
    }

    public function testElPrimerLatidoCambiaDeEstadoYLosDemasNo(): void
    {
        $presencia = new Presencia(new ArrayAdapter());

        $this->assertTrue($presencia->latir(1, "a"));
        $this->assertFalse($presencia->latir(1, "a"));
        $this->assertFalse($presencia->latir(1, "b"));
    }

    public function testDesconectaSoloAlCerrarLaUltimaConexion(): void
    {
        $presencia = new Presencia(new ArrayAdapter());
        $presencia->latir(1, "a");
        $presencia->latir(1, "b");

        $this->assertFalse($presencia->salir(1, "a"));
        $this->assertSame([1], $presencia->enLinea([1]));
        $this->assertTrue($presencia->salir(1, "b"));
        $this->assertSame([], $presencia->enLinea([1]));
    }

    public function testSalirSinEstarEnLineaNoCambiaNada(): void
    {
        $this->assertFalse((new Presencia(new ArrayAdapter()))->salir(1, "a"));
    }
}
