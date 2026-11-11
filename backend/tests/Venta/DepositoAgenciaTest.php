<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Excepcion\Rechazo;
use App\Chat\AvisosDelSistema;
use App\Entity\Agencia;
use App\Entity\AgenciaDeposito;
use App\Entity\Enum\EstadoDepositoAgencia;
use App\Venta\Agencia\DepositoRechazado;
use App\Venta\Agencia\DepositosAgencia;
use App\Venta\Agencia\FiltroDepositos;
use PHPUnit\Framework\TestCase;

/** Depósitos de agencia (ADR-034): estados y filtros. */
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
        } catch (Rechazo $e) {
            return $e->codigo;
        }
        self::fail("Se esperaba un rechazo");
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

    public function testAvisoDeRechazo(): void
    {
        $this->assertSame(
            "Se rechazó su depósito de Q 1,250.00 (boleta 4455): No aparece en el estado de cuenta",
            AvisosDelSistema::textoRechazo(new DepositoRechazado(7, "4455", 125000, "GTQ", "No aparece en el estado de cuenta")),
        );
    }
}
