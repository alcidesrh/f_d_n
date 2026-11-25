<?php

declare(strict_types=1);

namespace App\Tests\Tablero;

use App\Excepcion\ParametroInvalido;
use App\Tablero\DistribucionVentas;
use App\Tablero\FiltroDistribucion;
use PHPUnit\Framework\TestCase;

/** Reparto de lo vendido de la portada: tipos de boleto, puntos de venta y usuarios. */
final class DistribucionVentasTest extends TestCase
{
    /** @return array{canal: string, estacionId: ?int, sigla: ?string, estacion: ?string, usuarioId: ?int, usuario: ?string, nombre: ?string, estado: string, cortesia: bool, voucher: bool, boletos: int, monto: int} */
    private static function fila(
        int $monto,
        int $boletos = 1,
        string $canal = "estacion",
        ?int $estacion = 1,
        ?int $usuario = 1,
        string $estado = "emitido",
        bool $cortesia = false,
        bool $voucher = false,
    ): array {
        $siglas = [1 => "GUA", 2 => "SEP", 3 => null];

        return [
            "canal" => $canal,
            "estacionId" => $canal === "estacion" ? $estacion : null,
            "sigla" => $canal === "estacion" && $estacion !== null ? $siglas[$estacion] : null,
            "estacion" => $canal === "estacion" && $estacion !== null ? "Estación {$estacion}" : null,
            "usuarioId" => $usuario,
            "usuario" => $usuario !== null ? "u{$usuario}" : null,
            "nombre" => $usuario !== null ? "Usuario {$usuario}" : null,
            "estado" => $estado,
            "cortesia" => $cortesia,
            "voucher" => $voucher,
            "boletos" => $boletos,
            "monto" => $monto,
        ];
    }

    /** @param list<array<string, mixed>> $filas */
    private static function distribucion(array $filas): array
    {
        $dia = new \DateTimeImmutable("2026-11-24");

        return (new DistribucionVentas(new FiltroDistribucion($dia, $dia), $filas))->toArray();
    }

    public function testCadaBoletoCuentaEnUnSoloTipo(): void
    {
        self::assertSame("anulado", DistribucionVentas::tipo("anulado", true, false, "estacion"));
        self::assertSame("reasignado", DistribucionVentas::tipo("reasignado", false, true, "estacion"));
        self::assertSame("cortesia", DistribucionVentas::tipo("cancelado", true, false, "estacion"), "una cortesía no presentada sigue siendo cortesía");
        self::assertSame("voucher", DistribucionVentas::tipo("transito", false, true, "estacion"));
        self::assertSame("cobrado", DistribucionVentas::tipo("transito", false, true, "agencia"), "lo de agencia siempre se cobra");
        self::assertSame("cancelado", DistribucionVentas::tipo("cancelado", false, false, "web"));
        self::assertSame("cobrado", DistribucionVentas::tipo("pospuesto", false, false, "web"));
        self::assertSame("cobrado", DistribucionVentas::tipo("finalizado", false, false, "estacion"));
    }

    public function testLoVendidoSonLosCobradosYLosNoPresentados(): void
    {
        $d = self::distribucion([
            self::fila(10_000, 2),
            self::fila(5_000, 1, estado: "cancelado"),
            self::fila(7_000, 1, estado: "anulado"),
            self::fila(3_000, 1, estado: "reasignado"),
            self::fila(4_000, 1, cortesia: true),
            self::fila(6_000, 1, voucher: true),
        ]);

        self::assertSame(["monto" => 15_000, "boletos" => 3], $d["total"]);
        self::assertSame(
            ["cobrado" => 2, "anulado" => 1, "reasignado" => 1, "cortesia" => 1, "cancelado" => 1, "voucher" => 1],
            array_column($d["tipos"], "boletos", "tipo"),
        );
        self::assertSame(7_000, array_column($d["tipos"], "monto", "tipo")["anulado"]);
    }

    public function testPorCanalEnOrdenFijo(): void
    {
        $d = self::distribucion([
            self::fila(1_000, canal: "web", usuario: 9),
            self::fila(8_000, canal: "agencia", usuario: 5),
            self::fila(2_000),
        ]);

        self::assertSame(["estacion", "agencia", "web"], array_column($d["canales"], "canal"));
        self::assertSame([2_000, 8_000, 1_000], array_column($d["canales"], "monto"));
    }

    public function testLaPaginaYLasAgenciasSonDosPuntosMas(): void
    {
        $d = self::distribucion([
            self::fila(2_000, estacion: 1),
            self::fila(9_000, estacion: 2),
            self::fila(1_000, estacion: 3),
            self::fila(4_000, canal: "agencia", usuario: 5),
            self::fila(3_000, canal: "agencia", usuario: 6),
            self::fila(500, canal: "web", usuario: 9),
            self::fila(800, estacion: 1, estado: "anulado"),
        ]);

        self::assertSame(["SEP", "Agencias", "GUA", "Estación 3", "Página"], array_column($d["estaciones"], "sigla"));
        self::assertSame([9_000, 7_000, 2_000, 1_000, 500], array_column($d["estaciones"], "monto"));
        self::assertSame(["estacion-2", "agencia", "estacion-1", "estacion-3", "web"], array_column($d["estaciones"], "clave"));
        self::assertSame("Estación 2", $d["estaciones"][0]["nombre"]);
    }

    public function testPorUsuarioSoloQuienVendio(): void
    {
        $d = self::distribucion([
            self::fila(2_000, usuario: 1),
            self::fila(3_000, usuario: 1, estacion: 2),
            self::fila(4_000, usuario: 2),
            self::fila(1_000, usuario: 3, cortesia: true),
            self::fila(700, canal: "web", usuario: null),
        ]);

        self::assertSame(["u1", "u2", "—"], array_column($d["usuarios"], "usuario"));
        self::assertSame([5_000, 4_000, 700], array_column($d["usuarios"], "monto"));
        self::assertSame("Sin usuario", $d["usuarios"][2]["nombre"]);
    }

    public function testElRangoTieneQueSerValido(): void
    {
        $hoy = new \DateTimeImmutable("2026-11-24 10:30");
        $f = FiltroDistribucion::desdeQuery([], $hoy);
        self::assertSame(["2026-11-24", "2026-11-24", "2026-11-25"], [$f->desde->format("Y-m-d"), $f->hasta->format("Y-m-d"), $f->fin()->format("Y-m-d")]);
        self::assertSame("2026-11-30", FiltroDistribucion::desdeQuery(["desde" => "2026-11-01", "hasta" => "2026-11-30"], $hoy)->hasta->format("Y-m-d"));

        $this->expectException(ParametroInvalido::class);
        FiltroDistribucion::desdeQuery(["desde" => "2026-11-24", "hasta" => "2026-11-01"], $hoy);
    }

    public function testVariasEmpresasSinRepetirYEnOrden(): void
    {
        $hoy = new \DateTimeImmutable("2026-11-24");
        self::assertSame([], FiltroDistribucion::desdeQuery([], $hoy)->empresas, "sin empresas: todas las del alcance");
        self::assertSame([1, 3], FiltroDistribucion::desdeQuery(["empresas" => "3,1,3, "], $hoy)->empresas);

        $this->expectException(ParametroInvalido::class);
        FiltroDistribucion::desdeQuery(["empresas" => "1,x"], $hoy);
    }

    public function testNoMasDeUnAno(): void
    {
        $hoy = new \DateTimeImmutable("2026-11-24");
        self::assertSame("2026-01-01", FiltroDistribucion::desdeQuery(["desde" => "2025-01-01", "hasta" => "2026-01-01"], $hoy)->hasta->format("Y-m-d"));

        $this->expectException(ParametroInvalido::class);
        FiltroDistribucion::desdeQuery(["desde" => "2025-01-01", "hasta" => "2026-01-02"], $hoy);
    }
}
