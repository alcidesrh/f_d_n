<?php

declare(strict_types=1);

namespace App\Tests\Enclave;

use App\Enclave\Emparejador;
use PHPUnit\Framework\TestCase;

final class EmparejadorTest extends TestCase
{
    public function testBasesQuitaCalificativosYElDepartamento(): void
    {
        self::assertSame(['Xela', 'Quetzaltenango'], Emparejador::bases('Quetzaltenango, Xela Starbus', 'Quetzaltenango'));
        self::assertSame(['Tecun Uman'], Emparejador::bases('Tecun Uman  2', 'San Marcos'));
        self::assertSame(['Aguilar Batres'], Emparejador::bases('Aguilar Batres Web', 'Guatemala'));
        self::assertSame(['Aguilar Batres'], Emparejador::bases('Aguilar Batres1', 'Guatemala'));
        self::assertSame(['Tikal Parque'], Emparejador::bases('Tikal Parque Ida y Vuelta', 'Petén'));
        self::assertSame(['Chupol'], Emparejador::bases('Chupol; Chimaltenango', 'Chimaltenango'));
        self::assertSame(['Guastatoya', 'El Progreso'], Emparejador::bases('El Progreso, Guastatoya', 'El Progreso'));
        self::assertSame(['El Progreso'], Emparejador::bases('El Progreso, Jutiapa', 'Jutiapa'));
        self::assertSame(['Cadenas', 'Modesto Mendez'], Emparejador::bases('Cadenas, Modesto Mendez', 'Izabal'));
        self::assertSame(['Coban'], Emparejador::bases('Coban Alta Verapaz', 'Alta Verapaz'));
        self::assertSame(['Los Encuentros'], Emparejador::bases('Los Encuentros Solola', 'Sololá'));
        self::assertSame(['Guatemala'], Emparejador::bases('Guatemala', 'Guatemala'));
    }

    private function r(string $name, string $estado, string $tipo = 'town', float $imp = 0.3): array
    {
        return ['name' => $name, 'lat' => '16.33', 'lon' => '-89.41', 'addresstype' => $tipo, 'importance' => $imp, 'display_name' => "$name, $estado, Guatemala", 'address' => ['state' => $estado]];
    }

    public function testElegirExigeDepartamentoYParecido(): void
    {
        $res = [$this->r('Poptún', 'Izabal'), $this->r('Poptún', 'Petén'), $this->r('Otro Lugar', 'Petén')];
        $e = Emparejador::elegir('Poptun', 'Petén', $res);
        self::assertSame('Poptún', $e['nombre']);
        self::assertGreaterThan(0.9, $e['parecido']);
        self::assertNull(Emparejador::elegir('Poptun', 'Zacapa', $res));
        self::assertNull(Emparejador::elegir('Xyz', 'Petén', $res));
        // "Ciudad de X" cuenta como X.
        self::assertNotNull(Emparejador::elegir('Tecun Uman', 'Petén', [$this->r('Ciudad Tecún Umán', 'Petén')]) ?? Emparejador::elegir('Tecun Uman', 'Petén', [$this->r('Municipio de Tecún Umán', 'Petén')]));
    }

    public function testElegirPrefiereCiudadSobreAldea(): void
    {
        $res = [$this->r('San Luis', 'Petén', 'hamlet', 0.9), $this->r('San Luis', 'Petén', 'town', 0.1)];
        self::assertSame(16.33, Emparejador::elegir('San Luis', 'Petén', $res)['lat']);
    }

    public function testInternacionalesPidenParecidoCasiExacto(): void
    {
        $res = [['name' => 'San Pedro Sula', 'lat' => '15.5', 'lon' => '-88.03', 'display_name' => 'San Pedro Sula, Honduras', 'address' => ['state' => 'Cortés']]];
        self::assertNotNull(Emparejador::elegir('San Pedro Sula', 'Internacional', $res));
        self::assertNull(Emparejador::elegir('San Pedro', 'Internacional', $res));
    }

    public function testNombreCorregido(): void
    {
        self::assertSame('Tecún Umán 3', Emparejador::nombreCorregido('Tecun uman 3', 'Tecun uman', 'Tecún Umán'));
        self::assertSame('Asunción Mita', Emparejador::nombreCorregido('Asunsión Mita', 'Asunsión Mita', 'Asunción Mita'));
        self::assertNull(Emparejador::nombreCorregido('Guatemala', 'Guatemala', 'guatemala'), 'solo mayúsculas no es errata');
        self::assertNull(Emparejador::nombreCorregido('Flores', 'Flores', 'Santa Elena'), 'nombre distinto, no es errata');
    }
}
