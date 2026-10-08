<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\Transaccion;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TransaccionTest extends TestCase
{
    private function transaccion(): Transaccion
    {
        $conexion = $this->createStub(Connection::class);
        $conexion->method("isTransactionActive")->willReturn(true);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method("getConnection")->willReturn($conexion);

        return new Transaccion($em);
    }

    public function testLosEfectosEsperanAlCommit(): void
    {
        $t = $this->transaccion();
        $orden = [];
        $t->ejecutar(function () use ($t, &$orden) {
            $t->despuesDeConfirmar(function () use (&$orden) {
                $orden[] = "aviso";
            });
            $orden[] = "operación";
        });

        $this->assertSame(["operación", "aviso"], $orden);
    }

    public function testSiSeRevierteNoSaleNingunAviso(): void
    {
        $t = $this->transaccion();
        $avisos = 0;
        try {
            $t->ejecutar(function () use ($t, &$avisos) {
                $t->despuesDeConfirmar(function () use (&$avisos) {
                    ++$avisos;
                });
                throw new \RuntimeException("falla");
            });
        } catch (\RuntimeException) {
        }
        // Una transacción posterior no arrastra el aviso descartado.
        $t->ejecutar(static fn() => null);

        $this->assertSame(0, $avisos);
    }

    public function testFueraDeTransaccionElEfectoEsInmediatoYLaAnidadaUsaLaExterna(): void
    {
        $t = $this->transaccion();
        $avisos = [];
        $t->despuesDeConfirmar(function () use (&$avisos) {
            $avisos[] = "suelto";
        });
        $t->ejecutar(function () use ($t, &$avisos) {
            $t->ejecutar(function () use ($t, &$avisos) {
                $t->despuesDeConfirmar(function () use (&$avisos) {
                    $avisos[] = "anidado";
                });
            });
            $this->assertSame(["suelto"], $avisos, "aún no hubo commit");
        });

        $this->assertSame(["suelto", "anidado"], $avisos);
    }
}
