<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\Tarjeta\TarjetaDesempenoDia;
use PHPUnit\Framework\TestCase;

/** El id de la tarjeta de desempeño lleva el día y la empresa (0 = todas). */
final class TarjetaDesempenoDiaTest extends TestCase
{
    public function testCodigoLlevaDiaYEmpresa(): void
    {
        $dia = new \DateTimeImmutable("2026-10-30 22:00");

        self::assertSame(20261030000007, TarjetaDesempenoDia::codigo($dia, 7));
        self::assertSame(20261030000000, TarjetaDesempenoDia::codigo($dia, null), "0 = todas las empresas");
        self::assertSame(20261030000007 % 1_000_000, 7);
        self::assertSame("2026-10-30", \DateTimeImmutable::createFromFormat("!Ymd", (string) intdiv(TarjetaDesempenoDia::codigo($dia, 7), 1_000_000))->format("Y-m-d"));
    }
}
