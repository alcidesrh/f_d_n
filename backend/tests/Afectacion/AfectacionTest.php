<?php

declare(strict_types=1);

namespace App\Tests\Afectacion;

use App\Afectacion\AfectacionRechazada;
use App\Entity\Afectacion;
use App\Entity\Enum\EstadoAfectacion;
use App\Entity\TipoAfectacion;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

/** El ciclo de una afectación: creada → pendiente → resuelta, y las marcas del centinela (ADR-070). */
final class AfectacionTest extends TestCase
{
    private static function nueva(): Afectacion
    {
        return new Afectacion(new TipoAfectacion(), "Sin papel térmico.", null, null, null, null, new \DateTimeImmutable("2026-11-26 08:00"), "  stock:3:7  ");
    }

    public function testNaceCreadaYSinAvisos(): void
    {
        $a = self::nueva();

        self::assertSame(EstadoAfectacion::CREADA, $a->getEstado());
        self::assertSame(0, $a->getAvisos());
        self::assertSame("stock:3:7", $a->getClave());
        self::assertNull($a->getEnviadaEn());
    }

    public function testSinDescripcionNoSeCrea(): void
    {
        $this->expectException(AfectacionRechazada::class);
        new Afectacion(new TipoAfectacion(), "   ", null, null, null, null, new \DateTimeImmutable());
    }

    public function testElPrimerAvisoLaPasaAPendiente(): void
    {
        $a = self::nueva();
        $a->enviada(new \DateTimeImmutable("2026-11-26 08:01"));

        self::assertSame(EstadoAfectacion::PENDIENTE, $a->getEstado());
        self::assertSame("08:01", $a->getEnviadaEn()?->format("H:i"));
        self::assertSame("08:01", $a->getUltimoAvisoEn()?->format("H:i"));
        self::assertNull($a->getEscaladaEn());
        self::assertSame(1, $a->getAvisos());

        $this->expectException(AfectacionRechazada::class);
        $a->enviada(new \DateTimeImmutable("2026-11-26 08:02"));
    }

    public function testElEscalamientoSeMarcaUnaVez(): void
    {
        $a = self::nueva();
        $a->enviada(new \DateTimeImmutable("2026-11-26 08:01"));
        $a->avisada(new \DateTimeImmutable("2026-11-26 10:00"), true);
        $a->avisada(new \DateTimeImmutable("2026-11-26 10:30"), true);

        self::assertSame("10:00", $a->getEscaladaEn()?->format("H:i"));
        self::assertSame("10:30", $a->getUltimoAvisoEn()?->format("H:i"));
        self::assertSame(3, $a->getAvisos());
    }

    public function testUnaRondaSinDestinatariosMarcaElRitmoPeroNoCuentaNiLaEnvia(): void
    {
        $a = self::nueva();
        $a->avisada(new \DateTimeImmutable("2026-11-26 10:00"), true, false);

        self::assertSame(EstadoAfectacion::CREADA, $a->getEstado());
        self::assertSame(0, $a->getAvisos());
        self::assertSame("10:00", $a->getEscaladaEn()?->format("H:i"));
    }

    public function testSeResuelveUnaSolaVez(): void
    {
        $a = self::nueva();
        $quien = (new Usuario())->setUsername("taller");
        $a->resolver($quien, "  ", new \DateTimeImmutable("2026-11-26 09:00"));

        self::assertSame(EstadoAfectacion::RESUELTA, $a->getEstado());
        self::assertSame($quien, $a->getResueltaPor());
        self::assertNull($a->getResolucion());
        // Resuelta: el centinela ya no la marca.
        $a->avisada(new \DateTimeImmutable("2026-11-26 10:00"), true);
        self::assertSame(0, $a->getAvisos());

        $this->expectException(AfectacionRechazada::class);
        $a->resolver($quien, "Otra vez", new \DateTimeImmutable("2026-11-26 09:05"));
    }

    public function testResueltaNoSeEdita(): void
    {
        $a = self::nueva();
        $a->editar("Sin papel térmico ni papel carta.");
        self::assertSame("Sin papel térmico ni papel carta.", $a->getDescripcion());

        $a->resolver(null, null, new \DateTimeImmutable("2026-11-26 09:00"));
        $this->expectException(AfectacionRechazada::class);
        $a->editar("Otra cosa");
    }
}
