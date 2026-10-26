<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Chat\AvisosDelSistema;
use App\Entity\Agencia;
use App\Entity\AgenciaDeposito;
use App\Entity\Enum\EstadoDepositoAgencia;
use App\Migration\Mapeador;
use App\Venta\Agencia\DepositoRechazado;
use App\Venta\Agencia\DepositosAgencia;
use App\Venta\Agencia\FiltroDepositos;
use App\Venta\Excepcion\VentaRechazada;
use PHPUnit\Framework\TestCase;

/** Depósitos de agencia (ADR-034): estados, filtros y migración del legado. */
final class DepositoAgenciaTest extends TestCase
{
    private static function agencia(?string $porcentaje = "10.00"): Agencia
    {
        return (new Agencia())->setNombre("Agencia Flores")->setMoneda("GTQ")->setPorcentajeBonificacion($porcentaje);
    }

    private static function deposito(int $importe = 50000, bool $bono = true, ?string $porcentaje = "10.00"): AgenciaDeposito
    {
        return new AgenciaDeposito(self::agencia($porcentaje), new \DateTimeImmutable("2026-10-10 14:00"), $importe, "B-123", $bono, null, null, new \DateTimeImmutable("2026-10-12 09:00"));
    }

    private static function codigo(callable $f): string
    {
        try {
            $f();
        } catch (VentaRechazada $e) {
            return $e->codigo;
        }
        self::fail("Se esperaba VentaRechazada");
    }

    public function testNaceRegistradoConLaMonedaDeLaAgenciaYSinHora(): void
    {
        $d = self::deposito();

        $this->assertSame(EstadoDepositoAgencia::REGISTRADO, $d->getEstado());
        $this->assertSame("GTQ", $d->getMoneda());
        $this->assertSame("2026-10-10 00:00", $d->getFecha()->format("Y-m-d H:i"));
        $this->assertSame(0, $d->getBono());
    }

    public function testElImporteDebeSerPositivo(): void
    {
        $this->assertSame("importe_invalido", self::codigo(fn() => self::deposito(0)));
        $this->assertSame("importe_invalido", self::codigo(fn() => self::deposito(-100)));
    }

    public function testAcreditarGuardaElBonoYNoSeRepite(): void
    {
        $d = self::deposito();
        $d->acreditar(5000, null, new \DateTimeImmutable("2026-10-12 10:00"));

        $this->assertSame(EstadoDepositoAgencia::ACREDITADO, $d->getEstado());
        $this->assertSame(5000, $d->getBono());
        $this->assertSame("2026-10-12 10:00", $d->getResueltoEn()?->format("Y-m-d H:i"));
        $this->assertSame("deposito_resuelto", self::codigo(fn() => $d->acreditar(5000, null, new \DateTimeImmutable())));
        $this->assertSame("deposito_resuelto", self::codigo(fn() => $d->rechazar("tarde", null, new \DateTimeImmutable())));
    }

    public function testRechazarExigeMotivoYCierraElDeposito(): void
    {
        $d = self::deposito();
        $this->assertSame("motivo_requerido", self::codigo(fn() => $d->rechazar("   ", null, new \DateTimeImmutable())));
        $this->assertSame(EstadoDepositoAgencia::REGISTRADO, $d->getEstado());

        $d->rechazar("  La boleta no aparece en el banco  ", null, new \DateTimeImmutable());
        $this->assertSame(EstadoDepositoAgencia::RECHAZADO, $d->getEstado());
        $this->assertSame("La boleta no aparece en el banco", $d->getMotivoRechazo());
        $this->assertSame("deposito_resuelto", self::codigo(fn() => $d->acreditar(0, null, new \DateTimeImmutable())));
    }

    public function testBonoPrevistoSegunAplicaBonoYPorcentaje(): void
    {
        $this->assertSame(5000, DepositosAgencia::bonoPrevisto(self::deposito(50000)));
        $this->assertSame(0, DepositosAgencia::bonoPrevisto(self::deposito(50000, false)));
        $this->assertSame(0, DepositosAgencia::bonoPrevisto(self::deposito(50000, true, null)));
        // Redondeo hacia abajo: 10 % de Q 0.05 no regala un centavo.
        $this->assertSame(0, DepositosAgencia::bonoPrevisto(self::deposito(5)));

        $acreditado = self::deposito(50000);
        $acreditado->acreditar(1234, null, new \DateTimeImmutable());
        $this->assertSame(1234, DepositosAgencia::bonoPrevisto($acreditado), "acreditado: el bono que se dio, no el de hoy");
    }

    public function testNumeroDeBoleta(): void
    {
        $this->assertSame("00123", DepositosAgencia::boleta("  00123 "));
        $this->assertSame("boleta_requerida", self::codigo(fn() => DepositosAgencia::boleta("  ")));
        $this->assertSame("boleta_invalida", self::codigo(fn() => DepositosAgencia::boleta(str_repeat("9", AgenciaDeposito::MAX_BOLETA + 1))));
    }

    public function testFiltroPorDefectoUnMesAntesYDespues(): void
    {
        $f = FiltroDepositos::desdeQuery([], new \DateTimeImmutable("2026-10-12 15:30"));

        $this->assertSame("2026-09-12", $f->desde->format("Y-m-d"));
        $this->assertSame("2026-11-12", $f->hasta->format("Y-m-d"));
        $this->assertSame([1, 15], [$f->pagina, $f->porPagina]);
    }

    public function testFiltroLeeLosCampos(): void
    {
        $f = FiltroDepositos::desdeQuery([
            "desde" => "2026-10-01",
            "hasta" => "2026-10-31",
            "id" => "77",
            "agencia" => "12",
            "boleta" => " 4455 ",
            "estado" => "registrado,rechazado,registrado",
            "pagina" => "3",
            "porPagina" => "50",
        ], new \DateTimeImmutable("2026-10-12"));

        $this->assertSame([77, 12, "4455"], [$f->id, $f->agencia, $f->boleta]);
        $this->assertSame([EstadoDepositoAgencia::REGISTRADO, EstadoDepositoAgencia::RECHAZADO], $f->estados);
        $this->assertSame([3, 50], [$f->pagina, $f->porPagina]);
    }

    public function testFiltroRechazaParametrosInvalidos(): void
    {
        $hoy = new \DateTimeImmutable("2026-10-12");
        $this->assertSame("parametro_invalido", self::codigo(fn() => FiltroDepositos::desdeQuery(["estado" => "pagado"], $hoy)));
        $this->assertSame("fecha_invalida", self::codigo(fn() => FiltroDepositos::desdeQuery(["desde" => "12/10/2026"], $hoy)));
        $this->assertSame("rango_invalido", self::codigo(fn() => FiltroDepositos::desdeQuery(["desde" => "2026-10-12", "hasta" => "2026-10-01"], $hoy)));
        $this->assertSame("parametro_invalido", self::codigo(fn() => FiltroDepositos::desdeQuery(["agencia" => "x"], $hoy)));
        $this->assertSame(15, FiltroDepositos::desdeQuery(["porPagina" => "7"], $hoy)->porPagina);
    }

    public function testEstadosDelLegado(): void
    {
        $this->assertSame(EstadoDepositoAgencia::REGISTRADO, EstadoDepositoAgencia::deLegado(1));
        $this->assertSame(EstadoDepositoAgencia::ACREDITADO, EstadoDepositoAgencia::deLegado(2));
        $this->assertSame(EstadoDepositoAgencia::RECHAZADO, EstadoDepositoAgencia::deLegado(3));
    }

    public function testMapeaDepositoDelLegado(): void
    {
        $fila = (new Mapeador())->depositoAgencia([
            "id" => 901,
            "estacion_id" => 55,
            "fecha" => "2026-09-30 00:00:00.000",
            "importe" => "1500.50",
            "moneda_sigla" => "gtq",
            "numero_boleta" => " 778899 ",
            "aplica_bono" => "1",
            "bono" => "150.05",
            "observacion" => null,
            "estado_id" => 2,
            "motivo_rechazo" => null,
            "usuario_creacion_id" => "31",
            "fecha_creacion" => "2026-09-30 11:22:33.000",
        ]);

        $this->assertSame(55, $fila["agencia"]);
        $this->assertSame("2026-09-30", $fila["fecha"]);
        $this->assertSame(150050, $fila["importe"]);
        $this->assertSame("GTQ", $fila["moneda"]);
        $this->assertSame("778899", $fila["numero_boleta"]);
        $this->assertSame("true", $fila["aplica_bono"]);
        $this->assertSame(15005, $fila["bono"]);
        $this->assertSame("acreditado", $fila["estado"]);
        $this->assertSame(31, $fila["usuario"]);
        $this->assertSame("2026-09-30 11:22:33", $fila["creado_en"]);
        $this->assertSame("agencia_deposito-901", $fila["legacy_id"]);
    }

    public function testDepositoPendienteDelLegadoNoTraeBono(): void
    {
        $fila = (new Mapeador())->depositoAgencia([
            "id" => 902, "estacion_id" => 55, "fecha" => "2026-10-01", "importe" => "100", "numero_boleta" => "",
            "aplica_bono" => "0", "bono" => "10", "estado_id" => 1, "usuario_creacion_id" => null, "fecha_creacion" => "2026-10-01 08:00:00",
        ]);

        $this->assertSame(0, $fila["bono"]);
        $this->assertSame("false", $fila["aplica_bono"]);
        $this->assertSame("registrado", $fila["estado"]);
        $this->assertSame("s/n", $fila["numero_boleta"]);
        $this->assertNull($fila["usuario"]);
    }

    public function testFechasConElFormatoDelDriverDelLegado(): void
    {
        $fila = (new Mapeador())->depositoAgencia([
            "id" => 903, "estacion_id" => 55, "fecha" => "Jul 31 2014 12:00:00:AM", "importe" => "100", "numero_boleta" => "1",
            "aplica_bono" => "0", "bono" => "0", "estado_id" => 1, "usuario_creacion_id" => null, "fecha_creacion" => "Jul 31 2014 03:04:05:123PM",
        ]);

        $this->assertSame("2014-07-31", $fila["fecha"]);
        $this->assertSame("2014-07-31 15:04:05", $fila["creado_en"]);
    }

    public function testAvisoDeRechazo(): void
    {
        $this->assertSame(
            "Se rechazó su depósito de Q 1,250.00 (boleta 4455): No aparece en el estado de cuenta",
            AvisosDelSistema::textoRechazo(new DepositoRechazado(7, "4455", 125000, "GTQ", "No aparece en el estado de cuenta")),
        );
    }
}
