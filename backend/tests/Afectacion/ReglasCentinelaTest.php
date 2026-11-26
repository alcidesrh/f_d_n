<?php

declare(strict_types=1);

namespace App\Tests\Afectacion;

use App\Afectacion\ReglasCentinela as C;
use App\Entity\Enum\EstadoAfectacion as E;
use PHPUnit\Framework\TestCase;

/**
 * El protocolo del centinela (ADR-070): primer aviso a quien atiende,
 * escalamiento al pasar el tiempo sin resolver y repetición en el intervalo.
 * Tipo de ejemplo: insiste cada 30 minutos y escala a las 2 horas.
 */
final class ReglasCentinelaTest extends TestCase
{
    private const INTERVALO = 30;
    private const ESCALAR = 120;

    private static function t(string $hora): \DateTimeImmutable
    {
        return new \DateTimeImmutable("2026-11-26 $hora");
    }

    private static function decidir(E $estado, ?string $escalada, ?string $ultimo, string $ahora, string $creada = "08:00"): string
    {
        return C::decidir($estado, self::t($creada), $escalada ? self::t($escalada) : null, $ultimo ? self::t($ultimo) : null, self::INTERVALO, self::ESCALAR, self::t($ahora));
    }

    public function testRecienCreadaRecibeElAvisoInicial(): void
    {
        self::assertSame(C::INICIAL, self::decidir(E::CREADA, null, null, "08:00"));
        self::assertSame(C::INICIAL, self::decidir(E::CREADA, null, null, "09:59"));
    }

    public function testPendienteNoInsisteAntesDelTiempoDeEscalar(): void
    {
        self::assertSame(C::NADA, self::decidir(E::PENDIENTE, null, "08:00", "08:30"));
        self::assertSame(C::NADA, self::decidir(E::PENDIENTE, null, "08:00", "09:59"));
    }

    public function testAlPasarElTiempoSinResolverEscala(): void
    {
        self::assertSame(C::ESCALAMIENTO, self::decidir(E::PENDIENTE, null, "08:00", "10:00"));
        self::assertSame(C::ESCALAMIENTO, self::decidir(E::PENDIENTE, null, "08:00", "13:00"));
    }

    public function testEscaladaRepiteCadaIntervaloDesdeElUltimoAviso(): void
    {
        self::assertSame(C::NADA, self::decidir(E::PENDIENTE, "10:00", "10:00", "10:29"));
        self::assertSame(C::ESCALAMIENTO, self::decidir(E::PENDIENTE, "10:00", "10:00", "10:30"));
        // El ritmo cuenta desde el último aviso, no desde el escalamiento.
        self::assertSame(C::NADA, self::decidir(E::PENDIENTE, "10:00", "10:31", "11:00"));
        self::assertSame(C::ESCALAMIENTO, self::decidir(E::PENDIENTE, "10:00", "10:31", "11:01"));
    }

    public function testCreadaQueYaPasoElTiempoDeEscalarRecibeElEscalamiento(): void
    {
        // El aviso inicial no encontró a nadie, o el centinela estuvo detenido.
        self::assertSame(C::ESCALAMIENTO, self::decidir(E::CREADA, null, null, "10:05"));
        // Una ronda de escalamiento sin destinatarios marca el ritmo: no reintenta cada minuto.
        self::assertSame(C::NADA, self::decidir(E::CREADA, "10:05", "10:05", "10:06"));
        self::assertSame(C::ESCALAMIENTO, self::decidir(E::CREADA, "10:05", "10:05", "10:35"));
    }

    public function testResueltaNoSeVigila(): void
    {
        self::assertSame(C::NADA, self::decidir(E::RESUELTA, null, null, "08:00"));
        self::assertSame(C::NADA, self::decidir(E::RESUELTA, "10:00", "10:00", "23:00"));
        self::assertNull(C::proximoAviso(E::RESUELTA, self::t("08:00"), null, null, self::INTERVALO, self::ESCALAR, self::t("09:00")));
    }

    public function testEscalarEnCeroAvisaATodosDesdeElPrincipio(): void
    {
        self::assertSame(C::ESCALAMIENTO, C::decidir(E::CREADA, self::t("08:00"), null, null, 30, 0, self::t("08:00")));
    }

    public function testProximoAviso(): void
    {
        $proximo = static fn(E $e, ?string $escalada, ?string $ultimo, string $ahora) => C::proximoAviso($e, self::t("08:00"), $escalada ? self::t($escalada) : null, $ultimo ? self::t($ultimo) : null, self::INTERVALO, self::ESCALAR, self::t($ahora))?->format("H:i");

        self::assertSame("08:01", $proximo(E::CREADA, null, null, "08:01"), "Creada: se avisa ya.");
        self::assertSame("10:00", $proximo(E::PENDIENTE, null, "08:00", "08:30"), "Pendiente: cuando escala.");
        self::assertSame("11:00", $proximo(E::PENDIENTE, "10:00", "10:30", "10:40"), "Escalada: el último más el intervalo.");
        self::assertSame("10:50", $proximo(E::PENDIENTE, "10:00", "10:00", "10:50"), "Atrasado: ahora.");
    }
}
