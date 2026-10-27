<?php

declare(strict_types=1);

namespace App\Tests\ConfiguracionListado;

use App\ConfiguracionListado\Reglas;
use App\Entity\EntityConfiguration;
use App\Entity\ListConfiguration;
use App\Entity\Role;
use PHPUnit\Framework\TestCase;

final class ReglasTest extends TestCase
{
    private EntityConfiguration $agencia;
    private ListConfiguration $porDefecto;
    private ListConfiguration $taquilla;
    private ListConfiguration $supervision;
    private Role $vendedor;
    private Role $cajero;
    private Role $supervisor;
    private Role $contador;

    protected function setUp(): void
    {
        $this->vendedor = self::rol(1, "Vendedor");
        $this->cajero = self::rol(2, "Cajero");
        $this->supervisor = self::rol(3, "Supervisor");
        $this->contador = self::rol(4, "Contador");
        $this->agencia = new EntityConfiguration("Agencia");
        $this->porDefecto = self::con(new ListConfiguration($this->agencia, "Por defecto", 0), 10);
        $this->taquilla = self::con((new ListConfiguration($this->agencia, "Taquilla", 1))->setRoles([$this->vendedor, $this->cajero]), 11);
        $this->supervision = self::con((new ListConfiguration($this->agencia, "Supervisión", 2))->setRoles([$this->supervisor]), 12);
    }

    public function testAlUsuarioLeTocaLaDeSuRol(): void
    {
        $todas = $this->agencia->getListConfigurations();
        $this->assertSame($this->taquilla, Reglas::para($todas, [2]));
        $this->assertSame($this->supervision, Reglas::para($todas, [3, 99]));
    }

    public function testSinRolAsociadoLeTocaLaPorDefecto(): void
    {
        $todas = $this->agencia->getListConfigurations();
        $this->assertSame($this->porDefecto, Reglas::para($todas, []));
        $this->assertSame($this->porDefecto, Reglas::para($todas, [4]));
    }

    public function testConRolesEnVariasGanaLaDeMenorPosicion(): void
    {
        $this->supervision->setPosition(-1);
        $this->assertSame($this->supervision, Reglas::para($this->agencia->getListConfigurations(), [1, 3]));
    }

    public function testUnRolSoloEstaEnUnaConfiguracionPorEntidad(): void
    {
        $todas = $this->agencia->getListConfigurations();
        $this->assertStringContainsString("«Cajero» ya está en la configuración «Taquilla»", (string) Reglas::rechazoRoles($todas, $this->supervision, [$this->supervisor, $this->cajero]));
        $this->assertStringContainsString("«Taquilla»", (string) Reglas::rechazoRoles($todas, null, [$this->vendedor]));
        $this->assertNull(Reglas::rechazoRoles($todas, $this->taquilla, [$this->vendedor, $this->contador]), "sus propios roles no chocan");
        $this->assertNull(Reglas::rechazoRoles($todas, null, [$this->contador]));
    }

    public function testHayUnaSolaPorDefecto(): void
    {
        $todas = $this->agencia->getListConfigurations();
        $this->assertStringContainsString("La configuración por defecto es «Por defecto»", (string) Reglas::rechazoRoles($todas, null, []));
        $this->assertStringContainsString("al menos un rol", (string) Reglas::rechazoRoles($todas, $this->taquilla, []));
        $this->assertStringContainsString("no puede tener roles", (string) Reglas::rechazoRoles($todas, $this->porDefecto, [$this->contador]));
        $this->assertNull(Reglas::rechazoRoles($todas, $this->porDefecto, []));
    }

    public function testElRolBaseNoSeAsocia(): void
    {
        $base = self::rol(5, Role::user);
        $this->assertStringContainsString("lo tienen todos", (string) Reglas::rechazoRoles($this->agencia->getListConfigurations(), $this->taquilla, [$base]));
    }

    public function testLaPorDefectoNoSeElimina(): void
    {
        $this->assertNotNull(Reglas::rechazoEliminar($this->porDefecto));
        $this->assertNull(Reglas::rechazoEliminar($this->taquilla));
    }

    private static function rol(int $id, string $nombre): Role
    {
        return (new Role())->setId($id)->setNombre($nombre);
    }

    private static function con(ListConfiguration $config, int $id): ListConfiguration
    {
        (new \ReflectionProperty(ListConfiguration::class, "id"))->setValue($config, $id);

        return $config;
    }
}
