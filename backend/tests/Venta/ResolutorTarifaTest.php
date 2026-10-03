<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\Bus;
use App\Entity\BusClase;
use App\Entity\Empresa;
use App\Entity\Salida;
use App\Venta\ResolutorTarifa;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Salida Guatemala → Santa Elena (trayecto 1) de la empresa 3, bus 7 de
 * clase 2, a las 08:00. Subtrayectos: 10 Guatemala → Río Dulce, 11 Río
 * Dulce → Santa Elena, 12 Guatemala → Morales.
 */
final class ResolutorTarifaTest extends TestCase
{
    private Salida $salida;

    protected function setUp(): void
    {
        $bus = (new Bus())->setClase((new BusClase())->setId(2));
        $bus->setId(7);
        $this->salida = (new Salida())
            ->setFecha(new \DateTime("2026-10-05 08:00"))
            ->setEmpresa((new Empresa())->setId(3))
            ->setBus($bus);
    }

    /** @param array<string, mixed> $campos */
    private static function fila(int $id, ?int $trayecto, string $clase, int $centavos, array $campos = []): array
    {
        return [
            "id" => $id,
            "precio_monto" => $centavos,
            "precio_moneda" => "GTQ",
            "clase" => $clase,
            "vigente_desde" => "2026-01-01 00:00:00",
            "empresa_id" => null,
            "trayecto_id" => $trayecto,
            "hora_desde" => null,
            "hora_hasta" => null,
            "bus_clase_id" => null,
            "bus_id" => null,
            ...$campos,
        ];
    }

    /** @param list<array<string, mixed>> $filas */
    private function resolutor(array $filas, int $consultas = 1): ResolutorTarifa
    {
        $conexion = $this->createMock(Connection::class);
        $conexion->expects($this->exactly($consultas))->method("fetchAllAssociative")->willReturn($filas);

        return new ResolutorTarifa($conexion, new MockClock("2026-10-02 12:00"));
    }

    public function testSoloLosSubtrayectosConTarifaAsignableQuedanTarifados(): void
    {
        $r = $this->resolutor([
            self::fila(1, 1, "A", 25000),
            self::fila(2, 1, "B", 32500),
            // Propia del subtrayecto, sin más condiciones.
            self::fila(3, 10, "A", 15000),
            // Asignable por lo que hereda de la salida (empresa 3, clase de bus 2).
            self::fila(4, 11, "A", 12000, ["empresa_id" => 3, "bus_clase_id" => 2]),
            // Fija otra empresa, pero la relajación la deja aplicar.
            self::fila(5, 11, "B", 14000, ["empresa_id" => 99]),
        ]);

        $t = $r->porTrayectos($this->salida, [1, 10, 11, 12], ["A", "B"]);

        $this->assertSame(["A", "B"], array_keys($t[1]));
        $this->assertSame(["A"], array_keys($t[10]), "Guatemala → Río Dulce solo tiene clase A");
        $this->assertSame("15000", $t[10]["A"]->precio->getAmount());
        $this->assertSame(4, $t[11]["A"]->id);
        $this->assertSame(5, $t[11]["B"]->id);
        $this->assertSame([], $t[12], "Guatemala → Morales no tiene tarifa: no se vende");
    }

    public function testUnaTarifaSinTrayectoTarifaTodosLosSubtrayectos(): void
    {
        $r = $this->resolutor([self::fila(9, null, "A", 5000, ["empresa_id" => 3])]);

        $t = $r->porTrayectos($this->salida, [10, 12], ["A", "B"]);

        $this->assertSame(9, $t[10]["A"]->id);
        $this->assertSame(9, $t[12]["A"]->id);
        $this->assertArrayNotHasKey("B", $t[10]);
    }

    public function testElHorarioSeHeredaDeLaHoraDeLaSalida(): void
    {
        $r = $this->resolutor([
            self::fila(1, 10, "A", 15000, ["hora_desde" => "06:00:00", "hora_hasta" => "07:00:00"]),
            self::fila(2, 10, "A", 18000, ["hora_desde" => "07:30:00", "hora_hasta" => "09:00:00"]),
        ]);

        $this->assertSame(2, $r->porTrayectos($this->salida, [10], ["A"])[10]["A"]->id);
    }

    public function testMemoizaLasTarifasDuranteLaPeticion(): void
    {
        $r = $this->resolutor([self::fila(1, 10, "A", 15000)], consultas: 2);

        $r->porTrayectos($this->salida, [1, 10, 11], ["A"]);
        $this->assertSame(1, $r->porTrayectos($this->salida, [10, 11], ["A"])[10]["A"]->id, "sin otra consulta");

        $r->reset();
        $r->porTrayectos($this->salida, [10], ["A"]);
    }
}
