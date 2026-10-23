<?php

declare(strict_types=1);

namespace App\Tests\Geografia;

use App\Geografia\Depuracion;
use PHPUnit\Framework\TestCase;

/** Limpieza del dataset de regiones y ciudades de la página anterior (ADR-041). */
final class DepuracionTest extends TestCase
{
    public function testRegionesSinSufijoNiPrefijo(): void
    {
        $this->assertSame(
            [1 => "Petén", 2 => "Cayo", 3 => "Alajuela", 4 => "Cartago", 5 => "Baja California Sur"],
            Depuracion::regiones([1 => "Petén Department", 2 => "Cayo  District", 3 => "Alajuela Province", 4 => "Provincia de Cartago", 5 => "Baja California Sur"]),
        );
    }

    public function testRegionQueChocariaConservaElNombreOriginal(): void
    {
        $this->assertSame(
            [1 => "Lima", 2 => "Lima Province", 3 => "Cusco"],
            Depuracion::regiones([1 => "Lima", 2 => "Lima Province", 3 => "Cusco Department"]),
        );
    }

    public function testMunicipioDeSeDescartaSiYaEstaOSeRenombra(): void
    {
        $ciudades = Depuracion::ciudades(["Municipio de Flores", "Flores", "Municipio de Sayaxché", "Dolores", "flores", "  San  Benito "]);
        sort($ciudades);

        $this->assertSame(["Dolores", "Flores", "San Benito", "Sayaxché"], $ciudades);
    }

    public function testNombreQueSoloEsElPrefijoNoQuedaVacio(): void
    {
        $this->assertSame([1 => "Department"], Depuracion::regiones([1 => "Department"]));
    }
}
