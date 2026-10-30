<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Bitacora\TipoRegistro;
use App\Chat\Predeterminado\Despacho;
use App\Chat\Predeterminado\Difusion;
use App\Chat\Predeterminado\OcupacionAlIniciar;
use App\Chat\Conversaciones;
use App\Entity\BoletoTarifa;
use App\Entity\Enum\OperacionTarifa;
use App\Entity\Enum\TipoMensajePredeterminado;
use App\Entity\MensajePredeterminado;
use PHPUnit\Framework\TestCase;

/** Mensajes predeterminados del chat (ADR-045). */
final class MensajesPredeterminadosTest extends TestCase
{
    /** @return array{asiento: int, estado: string, canal: string, usuario: string, voucher: bool, cortesia: bool, clase: string, sube: int, baja: int, reasignado: bool} */
    private static function boleto(int $asiento, string $usuario, array $extra = []): array
    {
        return [
            "asiento" => $asiento,
            "estado" => "transito",
            "canal" => "estacion",
            "usuario" => $usuario,
            "voucher" => false,
            "cortesia" => false,
            "clase" => "A",
            "sube" => 0,
            "baja" => 3,
            "reasignado" => false,
            ...$extra,
        ];
    }

    public function testLaOcupacionAlIniciarCuentaAsientosYVendedores(): void
    {
        $o = OcupacionAlIniciar::de([
            self::boleto(1, "Ana"),
            self::boleto(2, "Ana", ["clase" => "B", "cortesia" => true]),
            // El asiento 3 en dos tramos intermedios: un solo asiento ocupado.
            self::boleto(3, "Luis", ["sube" => 0, "baja" => 1, "canal" => "agencia"]),
            self::boleto(3, "Luis", ["sube" => 1, "baja" => 3, "canal" => "web", "reasignado" => true]),
            // Anulado: libera el asiento, pero Beto aparece (vendió en la salida).
            self::boleto(4, "Beto", ["estado" => "anulado"]),
            // Reasignado a otra salida o no presentado: tampoco ocupa.
            self::boleto(5, "Ana", ["estado" => "reasignado"]),
            self::boleto(6, "Ana", ["estado" => "cancelado", "voucher" => true]),
        ], 40, 3);

        self::assertSame(3, $o->ocupados);
        self::assertSame(1, $o->conIntermedias);
        self::assertSame(37, $o->libres());
        self::assertSame(["estacion" => 2, "agencia" => 1, "web" => 1], $o->porCanal);

        self::assertSame(["Ana", "Luis", "Beto"], array_column($o->filas, "usuario"));
        self::assertSame(
            ["reasignados" => 0, "anulados" => 0, "vouchers" => 0, "cortesias" => 1, "intermedias" => 0, "claseA" => 1, "claseB" => 1, "total" => 2],
            $o->filas[0]["cuenta"],
        );
        self::assertSame(1, $o->filas[1]["cuenta"]["reasignados"]);
        self::assertSame(2, $o->filas[1]["cuenta"]["intermedias"]);
        self::assertSame(["anulados" => 1, "total" => 0], array_intersect_key($o->filas[2]["cuenta"], ["anulados" => 0, "total" => 0]));
        self::assertSame(4, $o->totales["total"]);
        self::assertSame(1, $o->totales["anulados"]);
    }

    public function testCuandoLeTocaAUnMensajeConHora(): void
    {
        $m = new MensajePredeterminado("Buenos días", TipoMensajePredeterminado::LIBRE, null);
        $m->setHora("07:30");
        $m->setDias([1, 4]); // lunes y jueves
        $this->creadoEn($m, "2026-10-20 10:00");

        // Jueves 29 de octubre de 2026.
        self::assertFalse(Despacho::tocaHoy($m, new \DateTimeImmutable("2026-10-29 07:29")));
        self::assertTrue(Despacho::tocaHoy($m, new \DateTimeImmutable("2026-10-29 07:30")));
        self::assertTrue(Despacho::tocaHoy($m, new \DateTimeImmutable("2026-10-29 23:00")));
        // Viernes: no es uno de sus días.
        self::assertFalse(Despacho::tocaHoy($m, new \DateTimeImmutable("2026-10-30 08:00")));

        // Creado después de la hora de hoy: hasta su próximo día.
        $this->creadoEn($m, "2026-10-29 09:00");
        self::assertFalse(Despacho::tocaHoy($m, new \DateTimeImmutable("2026-10-29 10:00")));

        // Sin días: todos.
        $m->setDias([]);
        self::assertTrue(Despacho::tocaHoy($m, new \DateTimeImmutable("2026-10-30 08:00")));
    }

    public function testLosReportesDelDiaSalenAlCierrePorDefecto(): void
    {
        $m = new MensajePredeterminado("Ventas", TipoMensajePredeterminado::VENTAS_DIA, null);
        $this->creadoEn($m, "2026-10-01 10:00");

        self::assertFalse(Despacho::tocaHoy($m, new \DateTimeImmutable("2026-10-29 21:59")));
        self::assertTrue(Despacho::tocaHoy($m, new \DateTimeImmutable("2026-10-29 22:00")));

        // Las que no salen a una hora nunca "tocan" por hora.
        $s = new MensajePredeterminado("Salida", TipoMensajePredeterminado::SALIDA_INICIADA, null);
        $this->creadoEn($s, "2026-10-01 10:00");
        $s->setHora("08:00");
        self::assertFalse(Despacho::tocaHoy($s, new \DateTimeImmutable("2026-10-29 12:00")));
    }

    public function testElTextoJuntaElPropioYElPredeterminado(): void
    {
        self::assertSame("Atentos.\n\nSalida iniciada: A → B", Difusion::texto("  Atentos. ", "Salida iniciada: A → B"));
        self::assertSame("Solo el propio", Difusion::texto("Solo el propio", ""));
        self::assertSame("Solo el predeterminado", Difusion::texto(null, "Solo el predeterminado"));
        self::assertSame(Conversaciones::MAX_TEXTO, mb_strlen(Difusion::texto(str_repeat("x", 3000), str_repeat("y", 3000))));
    }

    public function testElCatalogoDeTipos(): void
    {
        // Se guardan tal cual en `mensaje_predeterminado.tipo`.
        self::assertSame(
            ["libre", "salida_iniciada", "anomalia_reasignacion", "anomalia_tarifa", "ventas_dia", "desempeno_dia"],
            array_column(TipoMensajePredeterminado::cases(), "value"),
        );
        foreach (TipoMensajePredeterminado::cases() as $t) {
            self::assertNotSame("", $t->nombre());
            self::assertNotSame("", $t->descripcion());
            // Salen a una hora o por lo que vigilan, no de las dos formas.
            self::assertFalse($t->conHora() && ($t->esAnomalia() || $t->exigeRuta()), $t->value);
        }
        self::assertTrue(TipoMensajePredeterminado::SALIDA_INICIADA->exigeRuta());
        self::assertTrue(TipoMensajePredeterminado::SALIDA_INICIADA->exigeEmpresa());
        self::assertTrue(TipoMensajePredeterminado::VENTAS_DIA->exigeEmpresa());
        self::assertFalse(TipoMensajePredeterminado::ANOMALIA_TARIFA->exigeEmpresa());
        self::assertTrue(TipoMensajePredeterminado::LIBRE->exigeContenido());
    }

    public function testLaBitacoraDeTarifas(): void
    {
        self::assertSame(["creada", "modificada", "eliminada"], array_column(OperacionTarifa::cases(), "value"));
        self::assertSame(TipoRegistro::TARIFA, TipoRegistro::deObjeto(new BoletoTarifa()));
        self::assertSame(OperacionTarifa::class, TipoRegistro::from("tarifa")->operaciones());
    }

    private function creadoEn(MensajePredeterminado $m, string $fecha): void
    {
        (new \ReflectionProperty($m, "creadoEn"))->setValue($m, new \DateTimeImmutable($fecha));
    }
}
