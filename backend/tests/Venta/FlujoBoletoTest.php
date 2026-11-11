<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Acceso\Ambito;
use App\Acceso\Autorizacion;
use App\Acceso\Concesion;
use App\Acceso\PermisosUsuario;
use App\Entity\Agencia;
use App\Entity\Asiento;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\Salida;
use App\Entity\Usuario;
use App\Salida\SalidaRechazada;
use App\Venta\Boleto\Flujo\FlujoBoleto;
use App\Venta\Excepcion\VentaRechazada;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * El flujo del boleto (ADR-037) con la máquina de estados de la
 * configuración: sus transiciones son las del enum, los guards aplican las
 * reglas del abordaje y de la venta, y un rechazo es la excepción de siempre.
 * Entidades en memoria: no toca la base.
 */
final class FlujoBoletoTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const AHORA = "2026-10-21 12:00";

    private FlujoBoleto $flujo;

    protected function setUp(): void
    {
        self::bootKernel();
        static::mockTime(self::AHORA);
        $this->flujo = self::getContainer()->get(FlujoBoleto::class);
    }

    public function testLaMaquinaEsLaDelEnum(): void
    {
        /** @var WorkflowInterface $maquina */
        $maquina = self::getContainer()->get("state_machine." . TransicionBoleto::WORKFLOW);
        $definicion = $maquina->getDefinition();

        $this->assertSame(array_column(EstadoBoletoAsiento::cases(), "value"), array_values($definicion->getPlaces()));
        $this->assertSame([EstadoBoletoAsiento::EMITIDO->value], $definicion->getInitialPlaces());

        $arcos = [];
        foreach ($definicion->getTransitions() as $t) {
            $arcos[$t->getName()][] = [$t->getFroms()[0], $t->getTos()[0]];
        }
        $esperados = [];
        foreach (TransicionBoleto::cases() as $t) {
            foreach ($t->desde() as $desde) {
                $esperados[$t->value][] = [$desde->value, $t->hacia()->value];
            }
        }
        $this->assertSame($esperados, $arcos);
    }

    public function testElSetterAceptaLoQueLlegaPorAlgunaTransicion(): void
    {
        foreach (EstadoBoletoAsiento::cases() as $de) {
            foreach (EstadoBoletoAsiento::cases() as $a) {
                $existe = array_filter(TransicionBoleto::cases(), static fn(TransicionBoleto $t) => $t->hacia() === $a && in_array($de, $t->desde(), true)) !== [];
                $this->assertSame($existe, $de->puedeTransicionarA($a), "{$de->value} → {$a->value}");
            }
        }
    }

    public function testChequearSegunLaSalida(): void
    {
        $abordando = self::boleto(EstadoSalida::ABORDANDO, "+1 hour");
        $this->flujo->aplicar($abordando, TransicionBoleto::alChequear(EstadoSalida::ABORDANDO));
        $this->assertSame(EstadoBoletoAsiento::CHEQUEADO, $abordando->getEstado());

        // Sube en una parada intermedia con la salida ya en camino.
        $iniciada = self::boleto(EstadoSalida::INICIADA, "-1 hour");
        $this->assertSame("salida_no_coincide", $this->flujo->rechazo($iniciada, TransicionBoleto::CHEQUEAR)?->codigo);
        $this->flujo->aplicar($iniciada, TransicionBoleto::alChequear(EstadoSalida::INICIADA));
        $this->assertSame(EstadoBoletoAsiento::TRANSITO, $iniciada->getEstado());

        // Programada: todavía no se chequea.
        $programada = self::boleto(EstadoSalida::PROGRAMADA, "+1 hour");
        $this->assertSame("salida_no_abordando", $this->flujo->rechazo($programada, TransicionBoleto::CHEQUEAR)?->codigo);
        // Fuera de la ventana: más de 30 horas después.
        $vieja = self::boleto(EstadoSalida::INICIADA, "-31 hours");
        $this->assertSame("fuera_de_ventana", $this->flujo->rechazo($vieja, TransicionBoleto::CHEQUEAR_EN_RUTA)?->codigo);
    }

    public function testUnaVentaEnProcesoNoSeOpera(): void
    {
        $b = self::boleto(EstadoSalida::ABORDANDO, "+1 hour");
        $b->getBoletoVenta()->setEstado(EstadoBoletoVenta::PENDIENTE);

        foreach ([TransicionBoleto::CHEQUEAR, TransicionBoleto::CANCELAR, TransicionBoleto::ANULAR, TransicionBoleto::REASIGNAR, TransicionBoleto::POSPONER] as $t) {
            $this->assertSame("venta_en_proceso", $this->flujo->rechazo($b, $t)?->codigo, $t->value);
        }
        // Lo que causa la salida no depende de la venta.
        $this->assertNull($this->flujo->rechazo($b, TransicionBoleto::NO_PRESENTARSE));
    }

    public function testElEstadoDelBoletoLoDiceLaMaquina(): void
    {
        $b = self::boleto(EstadoSalida::INICIADA, "-1 hour", EstadoBoletoAsiento::TRANSITO);

        $rechazo = $this->flujo->rechazo($b, TransicionBoleto::CANCELAR);
        $this->assertInstanceOf(SalidaRechazada::class, $rechazo);
        $this->assertSame("boleto_no_cancelable", $rechazo->codigo);
        $this->assertSame(409, $rechazo->estadoHttp);
        $this->assertSame("El boleto 7 (asiento 12) está en tránsito: solo se cancela un boleto emitido o chequeado.", $rechazo->getMessage());

        // Lo de la venta se rechaza como siempre: `VentaRechazada`.
        $anular = $this->flujo->rechazo($b, TransicionBoleto::ANULAR);
        $this->assertInstanceOf(VentaRechazada::class, $anular);
        $this->assertSame("boleto_no_emitido", $anular->codigo);

        $this->expectException(SalidaRechazada::class);
        $this->flujo->aplicar($b, TransicionBoleto::REVERTIR);
    }

    public function testAnularYReasignarAntesDeLaHoraDeSalida(): void
    {
        $antes = self::boleto(EstadoSalida::PROGRAMADA, "+1 hour");
        $this->assertNull($this->flujo->rechazo($antes, TransicionBoleto::ANULAR));
        $this->assertNull($this->flujo->rechazo($antes, TransicionBoleto::REASIGNAR));

        $despues = self::boleto(EstadoSalida::ABORDANDO, "-5 minutes");
        $this->assertSame("salida_iniciada", $this->flujo->rechazo($despues, TransicionBoleto::ANULAR)?->codigo);

        // Un pospuesto no ocupa asiento: se anula o se reasigna aunque su salida haya pasado.
        $pospuesto = self::boleto(EstadoSalida::FINALIZADA, "-2 days", EstadoBoletoAsiento::POSPUESTO);
        $this->assertNull($this->flujo->rechazo($pospuesto, TransicionBoleto::REASIGNAR));
        $this->flujo->aplicar($pospuesto, TransicionBoleto::ANULAR, ["motivo" => "Prueba"]);
        $this->assertSame(EstadoBoletoAsiento::ANULADO, $pospuesto->getEstado());
    }

    public function testRevertirNiCortesiaNiAgencia(): void
    {
        $b = self::boleto(EstadoSalida::PROGRAMADA, "+1 hour", EstadoBoletoAsiento::CANCELADO);
        $this->assertNull($this->flujo->rechazo($b, TransicionBoleto::REVERTIR));

        $b->getBoletoVenta()->setCortesia(true);
        $this->assertSame("cortesia", $this->flujo->rechazo($b, TransicionBoleto::REVERTIR)?->codigo);

        $b->getBoletoVenta()->setCortesia(false)->setAgencia(new Agencia());
        $this->assertSame("agencia", $this->flujo->rechazo($b, TransicionBoleto::REVERTIR)?->codigo);
    }

    public function testLasDeLaSalidaSiguenASuEstado(): void
    {
        $b = self::boleto(EstadoSalida::ABORDANDO, "+1 hour", EstadoBoletoAsiento::CHEQUEADO);
        $this->assertSame("salida_no_iniciada", $this->flujo->rechazo($b, TransicionBoleto::PARTIR)?->codigo);

        $b->getSalida()->setEstado(EstadoSalida::INICIADA);
        $this->flujo->aplicar($b, TransicionBoleto::PARTIR);
        $this->assertSame("salida_no_finalizada", $this->flujo->rechazo($b, TransicionBoleto::LLEGAR)?->codigo);

        $b->getSalida()->setEstado(EstadoSalida::FINALIZADA);
        $this->flujo->aplicar($b, TransicionBoleto::LLEGAR);
        $this->assertSame(EstadoBoletoAsiento::FINALIZADO, $b->getEstado());

        $programada = self::boleto(EstadoSalida::PROGRAMADA, "+1 hour");
        $this->assertSame("salida_no_abordando", $this->flujo->rechazo($programada, TransicionBoleto::NO_PRESENTARSE)?->codigo);
    }

    public function testAccionesDelListado(): void
    {
        $acciones = $this->flujo->acciones(self::boleto(EstadoSalida::PROGRAMADA, "+1 hour"));

        $this->assertNull($acciones["cancelar"]);
        $this->assertNull($acciones["anular"]);
        $this->assertNull($acciones["reasignar"]);
        $this->assertSame("La salida está programada: los boletos se chequean cuando la salida está abordando o iniciada.", $acciones["chequear"]);
        $this->assertSame("El boleto 7 (asiento 12) está emitido: solo se revierte un boleto cancelado.", $acciones["revertir"]);
    }

    public function testElRolDeSistemaAnulaReasignaYCancelaEnCualquierEstadoConValor(): void
    {
        $finalizado = self::boleto(EstadoSalida::FINALIZADA, "-2 days", EstadoBoletoAsiento::FINALIZADO);
        $transito = self::boleto(EstadoSalida::INICIADA, "-1 hour", EstadoBoletoAsiento::TRANSITO);
        $cancelado = self::boleto(EstadoSalida::FINALIZADA, "-2 days", EstadoBoletoAsiento::CANCELADO);
        $anulado = self::boleto(EstadoSalida::PROGRAMADA, "+1 hour", EstadoBoletoAsiento::ANULADO);

        // Sin el rol de sistema, como siempre: el mismo mensaje y código que daba la máquina.
        $this->assertSame("boleto_no_emitido", $this->flujo->rechazo($finalizado, TransicionBoleto::ANULAR)?->codigo);
        $this->assertSame("boleto_no_emitido", $this->flujo->rechazo($cancelado, TransicionBoleto::REASIGNAR)?->codigo);
        $this->assertSame("El boleto 7 (asiento 12) está en tránsito: solo se cancela un boleto emitido o chequeado.", $this->flujo->rechazo($transito, TransicionBoleto::CANCELAR)?->getMessage());

        self::comoSistema();
        foreach ([TransicionBoleto::ANULAR, TransicionBoleto::REASIGNAR, TransicionBoleto::CANCELAR] as $t) {
            $this->assertNull($this->flujo->rechazo($finalizado, $t), $t->value);
            $this->assertNull($this->flujo->rechazo($transito, $t), $t->value);
        }
        $this->assertNull($this->flujo->rechazo($cancelado, TransicionBoleto::ANULAR));
        $this->assertNull($this->flujo->rechazo($cancelado, TransicionBoleto::REASIGNAR));
        // Emitido con la salida ya pasada de hora: sin autorización.
        $this->assertNull($this->flujo->rechazo(self::boleto(EstadoSalida::ABORDANDO, "-5 minutes"), TransicionBoleto::ANULAR));

        // Lo que ya no tiene valor no se opera, ni con el rol de sistema.
        $this->assertSame("boleto_no_emitido", $this->flujo->rechazo($anulado, TransicionBoleto::ANULAR)?->codigo);
        $this->assertNotNull($this->flujo->rechazo(self::boleto(EstadoSalida::PROGRAMADA, "+1 hour", EstadoBoletoAsiento::REASIGNADO), TransicionBoleto::REASIGNAR));
        // Ni una venta que todavía se está procesando.
        $pendiente = self::boleto(EstadoSalida::FINALIZADA, "-2 days", EstadoBoletoAsiento::FINALIZADO);
        $pendiente->getBoletoVenta()->setEstado(EstadoBoletoVenta::PENDIENTE);
        $this->assertSame("venta_en_proceso", $this->flujo->rechazo($pendiente, TransicionBoleto::ANULAR)?->codigo);
        // Lo que causa la salida sigue sus reglas.
        $this->assertSame("salida_no_finalizada", $this->flujo->rechazo($transito, TransicionBoleto::LLEGAR)?->codigo);

        $this->flujo->aplicar($finalizado, TransicionBoleto::ANULAR, ["motivo" => "Prueba"]);
        $this->assertSame(EstadoBoletoAsiento::ANULADO, $finalizado->getEstado());
        $this->flujo->aplicar($transito, TransicionBoleto::CANCELAR, ["motivo" => "Prueba"]);
        $this->assertSame(EstadoBoletoAsiento::CANCELADO, $transito->getEstado());
    }

    /** Sesión de un usuario con el rol de sistema (sin base: sus permisos ya cargados). */
    private static function comoSistema(): void
    {
        $usuario = (new Usuario())->setUsername("sistema");
        $usuario->setId(99);
        self::getContainer()->get("security.token_storage")->setToken(new UsernamePasswordToken($usuario, "api"));
        (new \ReflectionProperty(Autorizacion::class, "cache"))->setValue(
            self::getContainer()->get(Autorizacion::class),
            [99 => new PermisosUsuario([new Concesion(1, 1, "Sistema", true, [], [], Ambito::total())], [])],
        );
    }

    private static function boleto(EstadoSalida $estadoSalida, string $hora, EstadoBoletoAsiento $estado = EstadoBoletoAsiento::EMITIDO): BoletoAsiento
    {
        $salida = (new Salida())->setFecha(new \DateTime(self::AHORA . " " . $hora));
        // Por sus estados en orden, como lo exige `Salida::setEstado`.
        foreach ([EstadoSalida::ABORDANDO, EstadoSalida::INICIADA, EstadoSalida::FINALIZADA] as $paso) {
            if ($salida->getEstado() === $estadoSalida) {
                break;
            }
            $salida->setEstado($paso);
        }

        $b = (new BoletoAsiento())
            ->setSalida($salida)
            ->setBoletoVenta(new BoletoVenta())
            ->setAsiento((new Asiento())->setNumero(12));
        $b->setId(7);
        $estadoInicial = new \ReflectionProperty(BoletoAsiento::class, "estado");
        $estadoInicial->setValue($b, $estado);

        return $b;
    }
}
