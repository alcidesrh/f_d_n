<?php

declare(strict_types=1);

namespace App\Tests\Inventario;

use App\Entity\Enum\TipoMovimiento;
use App\Entity\Movimiento;
use App\Inventario\InventarioRechazado;
use PHPUnit\Framework\TestCase;

/** Lo que hace una existencia al entrar, salir, contarse y ponerle niveles, y su movimiento (ADR-071). */
final class ExistenciaTest extends TestCase
{
    public function testEntrarRehaceElCostoPromedio(): void
    {
        $e = ReglasInventarioTest::existencia();
        $e->entrar(1000, 20000);
        $e->entrar(3000, 40000);

        self::assertSame("40.00", $e->getCantidad());
        self::assertSame("3.5000", $e->getCostoPromedio());
    }

    public function testSacarNoCambiaElCostoNiDejaNegativos(): void
    {
        $e = ReglasInventarioTest::existencia();
        $e->entrar(1000, 20000);
        $e->sacar(400);

        self::assertSame("6.00", $e->getCantidad());
        self::assertSame("2.0000", $e->getCostoPromedio());
        try {
            $e->sacar(601);
            self::assertTrue(false);
        } catch (InventarioRechazado $r) {
            self::assertSame("existencia_insuficiente", $r->codigo);
            self::assertSame(409, $r->estadoHttp);
            self::assertSame("Solo hay 6.00 rollo de «Papel térmico» en Central.", $r->getMessage());
        }
        self::assertSame("6.00", $e->getCantidad());
    }

    public function testNoEntraNiSaleCeroONegativo(): void
    {
        $e = ReglasInventarioTest::existencia();
        $this->expectException(InventarioRechazado::class);
        $e->entrar(0, 100);
    }

    public function testContarDejaLoContadoYDevuelveLaDiferencia(): void
    {
        $e = ReglasInventarioTest::existencia();
        $e->entrar(1000, 20000);

        self::assertSame(-250, $e->contar(750));
        self::assertSame("7.50", $e->getCantidad());
        self::assertSame(250, $e->contar(1000));
        self::assertSame(0, $e->contar(1000));
        self::assertSame("2.0000", $e->getCostoPromedio());
    }

    public function testLosNivelesVanEnOrden(): void
    {
        $e = ReglasInventarioTest::existencia();
        $e->niveles(1000, 1500, 5000);
        self::assertSame("10.00", $e->getMinimo());
        self::assertSame("15.00", $e->getPuntoReorden());
        self::assertSame("50.00", $e->getMaximo());
        $e->niveles(0, null, null);
        self::assertNull($e->getPuntoReorden());

        foreach ([[1000, 500, null], [1000, null, 500], [1000, 1500, 1200], [-1, null, null]] as [$min, $reorden, $max]) {
            try {
                $e->niveles($min, $reorden, $max);
                self::assertTrue(false);
            } catch (InventarioRechazado $r) {
                self::assertSame("nivel_invalido", $r->codigo);
            }
        }
    }

    public function testElMovimientoGuardaElSaldoQueDejo(): void
    {
        $e = ReglasInventarioTest::existencia();
        $e->entrar(1000, 20000);
        $m = new Movimiento(TipoMovimiento::ENTRADA, $e, "10.00", "2.0000", null, new \DateTimeImmutable("2026-11-26 10:00"), "  FAC-1 ", "");

        self::assertSame("10.00", $m->getSaldo());
        self::assertSame("FAC-1", $m->getDocumento());
        self::assertNull($m->getMotivo());
        self::assertSame($e->getAlmacen(), $m->getAlmacen());
    }

    public function testUnEnvioSeRecibeUnaSolaVez(): void
    {
        $e = ReglasInventarioTest::existencia();
        $envio = new Movimiento(TipoMovimiento::ENVIO, $e, "-1.00", "2.0000", null, new \DateTimeImmutable("2026-11-26 10:00"));
        $envio->recibido(new \DateTimeImmutable("2026-11-27 09:00"));
        self::assertSame("2026-11-27 09:00", $envio->getRecibidoEn()?->format("Y-m-d H:i"));

        try {
            $envio->recibido(new \DateTimeImmutable("2026-11-27 10:00"));
            self::assertTrue(false);
        } catch (InventarioRechazado $r) {
            self::assertSame("envio_recibido", $r->codigo);
        }
        $salida = new Movimiento(TipoMovimiento::SALIDA, $e, "-1.00", "2.0000", null, new \DateTimeImmutable("2026-11-26 10:00"));
        $this->expectException(InventarioRechazado::class);
        $salida->recibido(new \DateTimeImmutable("2026-11-27 10:00"));
    }

    public function testElMotivoTieneUnLimite(): void
    {
        $this->expectException(InventarioRechazado::class);
        new Movimiento(TipoMovimiento::AJUSTE, ReglasInventarioTest::existencia(), "0.00", "0.0000", null, new \DateTimeImmutable(), null, str_repeat("x", Movimiento::MAX_MOTIVO + 1));
    }
}
