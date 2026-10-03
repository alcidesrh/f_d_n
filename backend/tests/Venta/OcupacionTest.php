<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\Enum\CanalVenta;
use App\Venta\Itinerario;
use App\Venta\Ocupacion;
use App\Venta\Ocupante;
use App\Venta\Tramo;
use PHPUnit\Framework\TestCase;

final class OcupacionTest extends TestCase
{
    private Itinerario $it;

    protected function setUp(): void
    {
        // A(1) → B(2) → C(3); trayecto 1 = A→C, 10 = A→B, 11 = B→C.
        $this->it = Itinerario::construir(1, 1, 3, [
            ["trayecto" => 10, "origen" => 1, "destino" => 2],
            ["trayecto" => 11, "origen" => 2, "destino" => 3],
        ]);
    }

    public function testUnAsientoVendidoParaUnTramoQuedaLibreEnElOtro(): void
    {
        $ocupantes = [new Ocupante(5, 10, Ocupante::VENDIDO, CanalVenta::ESTACION)];

        $this->assertSame([], Ocupacion::estados($this->it, $this->it->tramo(11), $ocupantes));
        $this->assertSame(
            [5 => ["estado" => Ocupacion::VENDIDO, "canal" => "estacion", "sinCobro" => null]],
            Ocupacion::estados($this->it, $this->it->tramo(1), $ocupantes),
        );
    }

    public function testCortesiasYVouchersSeDistinguenDeLoCobrado(): void
    {
        $ocupantes = [
            new Ocupante(1, 1, Ocupante::VENDIDO, CanalVenta::ESTACION, sinCobro: Ocupacion::CORTESIA),
            new Ocupante(2, 1, Ocupante::VENDIDO, CanalVenta::AGENCIA, sinCobro: Ocupacion::VOUCHER),
            new Ocupante(3, 1, Ocupante::VENDIDO, CanalVenta::WEB),
        ];

        $estados = Ocupacion::estados($this->it, $this->it->tramo(1), $ocupantes);

        $this->assertSame(Ocupacion::CORTESIA, $estados[1]["sinCobro"]);
        $this->assertSame(["estado" => Ocupacion::VENDIDO, "canal" => "agencia", "sinCobro" => Ocupacion::VOUCHER], $estados[2]);
        $this->assertNull($estados[3]["sinCobro"]);
    }

    public function testLasReservasPropiasSeDistinguenDeLasAjenas(): void
    {
        $ocupantes = [
            new Ocupante(1, 1, Ocupante::RESERVADO, token: "yo"),
            new Ocupante(2, 1, Ocupante::RESERVADO, token: "otro"),
        ];
        $estados = Ocupacion::estados($this->it, $this->it->tramo(1), $ocupantes, "yo");

        $this->assertSame(Ocupacion::PROPIO, $estados[1]["estado"]);
        $this->assertSame(Ocupacion::RESERVADO, $estados[2]["estado"]);
        $this->assertSame([2], Ocupacion::noDisponibles([1, 2, 3], $estados));
    }

    public function testVendidoPrevaleceSobreReservado(): void
    {
        $ocupantes = [
            new Ocupante(3, 10, Ocupante::RESERVADO, token: "yo"),
            new Ocupante(3, 1, Ocupante::VENDIDO, CanalVenta::WEB),
        ];

        $this->assertSame(Ocupacion::VENDIDO, Ocupacion::estados($this->it, new Tramo(0, 1), $ocupantes, "yo")[3]["estado"]);
    }

    public function testUnTrayectoAjenoAlItinerarioOcupaTodoElSalida(): void
    {
        $ocupantes = [new Ocupante(8, 999, Ocupante::VENDIDO, CanalVenta::AGENCIA)];

        $this->assertArrayHasKey(8, Ocupacion::estados($this->it, $this->it->tramo(11), $ocupantes));
    }

    public function testUnAsientoSeRevendeEnLosSubtrayectosQueNoSeSolapan(): void
    {
        // Guatemala(1) → Morales(2) → Río Dulce(3) → Santa Elena(4), todos los pares.
        $it = Itinerario::construir(1, 1, 4, [
            ["trayecto" => 12, "origen" => 1, "destino" => 2],
            ["trayecto" => 10, "origen" => 1, "destino" => 3],
            ["trayecto" => 23, "origen" => 2, "destino" => 3],
            ["trayecto" => 24, "origen" => 2, "destino" => 4],
            ["trayecto" => 11, "origen" => 3, "destino" => 4],
        ]);
        // Asiento 5 vendido Guatemala → Morales por la agencia; asiento 6 Río Dulce → Santa Elena en la web.
        $ocupantes = [
            new Ocupante(5, 12, Ocupante::VENDIDO, CanalVenta::AGENCIA),
            new Ocupante(6, 11, Ocupante::VENDIDO, CanalVenta::WEB),
        ];
        $ocupados = static fn(int $trayecto) => array_keys(Ocupacion::estados($it, $it->tramo($trayecto), $ocupantes));

        $this->assertSame([], $ocupados(23), "Morales → Río Dulce: ambos libres");
        $this->assertSame([6], $ocupados(24), "Morales → Santa Elena: el 5 ya bajó");
        $this->assertSame([5], $ocupados(10), "Guatemala → Río Dulce: el 6 aún no sube");
        $this->assertSame([5, 6], $ocupados(1), "el trayecto completo choca con los dos");
    }
}
