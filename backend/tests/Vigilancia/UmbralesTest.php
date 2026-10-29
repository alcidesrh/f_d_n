<?php

declare(strict_types=1);

namespace App\Tests\Vigilancia;

use App\Acceso\Limite;
use App\Vigilancia\UmbralesDiarios;
use PHPUnit\Framework\TestCase;

/** Avisos por umbral diario (ADR-044): solo la operación que lo alcanza avisa. */
final class UmbralesTest extends TestCase
{
    public function testSoloAvisaLaOperacionQueLoCruza(): void
    {
        self::assertTrue(UmbralesDiarios::cruza(5, 4, 5));
        self::assertTrue(UmbralesDiarios::cruza(5, 3, 8), 'una anulación de varios boletos que salta el umbral');
        self::assertFalse(UmbralesDiarios::cruza(5, 5, 6), 'ya se avisó');
        self::assertFalse(UmbralesDiarios::cruza(5, 2, 4));
        self::assertFalse(UmbralesDiarios::cruza(null, 0, 50), 'sin umbral en el rol');
        self::assertFalse(UmbralesDiarios::cruza(0, 0, 50));
    }

    public function testLosLimitesDeAvisoNoBloquean(): void
    {
        foreach (Limite::cases() as $l) {
            if (str_starts_with($l->value, 'aviso_')) {
                self::assertFalse($l->bloquea(), $l->value);
            }
        }
        self::assertTrue(Limite::ANULACIONES_POR_DIA->bloquea());
    }
}
