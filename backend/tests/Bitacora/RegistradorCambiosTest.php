<?php

declare(strict_types=1);

namespace App\Tests\Bitacora;

use App\Bitacora\RegistradorCambios;
use App\Entity\Bus;
use App\Entity\Enum\OperacionCambio;
use PHPUnit\Framework\TestCase;

final class RegistradorCambiosTest extends TestCase
{
    public function testLosCambiosTapanSecretosEIgnoranLoQueCambiaSolo(): void
    {
        $this->assertSame(
            [
                "nombre" => ["de" => "Viejo", "a" => "Nuevo"],
                "password" => ["de" => "***", "a" => "***"],
                "claveCifrada" => ["de" => "***", "a" => "***"],
                "activo" => ["de" => true, "a" => false],
                "fecha" => ["de" => "2026-11-01 08:00", "a" => null],
            ],
            RegistradorCambios::cambios([
                "nombre" => ["Viejo", "Nuevo"],
                "password" => ["h1", "h2"],
                "claveCifrada" => ["a", "b"],
                "activo" => [true, false],
                "fecha" => [new \DateTimeImmutable("2026-11-01 08:00"), null],
                "updatedAt" => [new \DateTimeImmutable(), new \DateTimeImmutable()],
            ]),
        );
    }

    public function testValorLegible(): void
    {
        $this->assertSame(str_repeat("x", 200), RegistradorCambios::valor(str_repeat("x", 300)));
        $this->assertSame('{"a":1}', RegistradorCambios::valor(["a" => 1]));
        $this->assertNull(RegistradorCambios::valor(new \stdClass()));
    }

    public function testClaseCorta(): void
    {
        $this->assertSame("Bus", RegistradorCambios::clase(new Bus()));
    }

    public function testLosValoresGuardadosSonEstables(): void
    {
        $this->assertSame(["creado", "modificado", "eliminado"], array_column(OperacionCambio::cases(), "value"));
    }
}
