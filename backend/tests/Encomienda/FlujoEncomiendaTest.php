<?php

declare(strict_types=1);

namespace App\Tests\Encomienda;

use App\Acceso\Ambito;
use App\Acceso\Autorizacion;
use App\Acceso\Concesion;
use App\Acceso\PermisosUsuario;
use App\Autorizacion\DispensasEnCurso;
use App\Encomienda\EncomiendaRechazada;
use App\Encomienda\FlujoEncomienda;
use App\Encomienda\ReglasEncomienda;
use App\Entity\Encomienda;
use App\Entity\Enclave;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Enum\TransicionEncomienda;
use App\Entity\Parada;
use App\Entity\Ruta;
use App\Entity\Salida;
use App\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * El flujo de la encomienda (ADR-038) con la máquina de estados de la
 * configuración: sus transiciones son las del enum, los guards aplican lo que
 * se sabe de la encomienda (dónde está, su modalidad, su salida) y un rechazo
 * es la `EncomiendaRechazada` de siempre. Entidades en memoria: no toca la base.
 */
final class FlujoEncomiendaTest extends KernelTestCase
{
    private const GUATE = 1;
    private const RIO_DULCE = 2;
    private const FLORES = 3;

    private FlujoEncomienda $flujo;

    /** @var array<int, Enclave> */
    private array $enclaves = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->flujo = self::getContainer()->get(FlujoEncomienda::class);
        foreach ([self::GUATE => "Guatemala", self::RIO_DULCE => "Río Dulce", self::FLORES => "Flores"] as $id => $nombre) {
            $this->enclaves[$id] = (new Enclave())->setNombre($nombre)->setId($id);
        }
    }

    public function testLaMaquinaEsLaDelEnum(): void
    {
        /** @var WorkflowInterface $maquina */
        $maquina = self::getContainer()->get("state_machine." . TransicionEncomienda::WORKFLOW);
        $definicion = $maquina->getDefinition();

        $this->assertSame(array_column(EstadoEncomienda::cases(), "value"), array_values($definicion->getPlaces()));
        $this->assertSame([EstadoEncomienda::RECIBIDA->value], $definicion->getInitialPlaces());

        $arcos = [];
        foreach ($definicion->getTransitions() as $t) {
            $arcos[$t->getName()][] = [$t->getFroms()[0], $t->getTos()[0]];
        }
        $esperados = [];
        foreach (TransicionEncomienda::cases() as $t) {
            foreach ($t->desde() as $desde) {
                $esperados[$t->value][] = [$desde->value, $t->hacia()->value];
            }
        }
        $this->assertSame($esperados, $arcos);
    }

    public function testElSetterAceptaLoQueLlegaPorAlgunaTransicion(): void
    {
        foreach (EstadoEncomienda::cases() as $de) {
            foreach (EstadoEncomienda::cases() as $a) {
                $existe = array_filter(TransicionEncomienda::cases(), static fn(TransicionEncomienda $t) => $t->hacia() === $a && in_array($de, $t->desde(), true)) !== [];
                $this->assertSame($existe, $de->puedeTransicionarA($a), "{$de->value} → {$a->value}");
            }
        }
    }

    public function testSeEmbarcaDesdeLaBodegaSinEstarEnSuDestino(): void
    {
        $recibida = $this->encomienda();
        $this->flujo->aplicar($recibida, TransicionEncomienda::alEmbarcar(EstadoSalida::PROGRAMADA));
        $this->assertSame(EstadoEncomienda::EMBARCADA, $recibida->getEstado());

        // Transbordo con la salida ya en camino: directo a tránsito.
        $transbordo = $this->encomienda(EstadoEncomienda::DESEMBARCADA, self::RIO_DULCE);
        $this->flujo->aplicar($transbordo, TransicionEncomienda::alEmbarcar(EstadoSalida::INICIADA));
        $this->assertSame(EstadoEncomienda::TRANSITO, $transbordo->getEstado());

        $this->assertSame("en_destino", $this->flujo->rechazo($this->encomienda(EstadoEncomienda::DESEMBARCADA, self::FLORES), TransicionEncomienda::EMBARCAR)?->codigo);
        $this->assertSame(
            "La encomienda está entregada: solo se embarca una encomienda en bodega (recibida o desembarcada).",
            $this->flujo->rechazo($this->encomienda(EstadoEncomienda::ENTREGADA, null), TransicionEncomienda::EMBARCAR)?->getMessage(),
        );
    }

    public function testParteConSuSalidaIniciada(): void
    {
        $e = $this->encomienda(EstadoEncomienda::EMBARCADA, null, $this->salida(EstadoSalida::ABORDANDO));
        $this->assertSame("salida_no_iniciada", $this->flujo->rechazo($e, TransicionEncomienda::PARTIR)?->codigo);

        $e->getSalida()?->setEstado(EstadoSalida::INICIADA);
        $this->flujo->aplicar($e, TransicionEncomienda::PARTIR);
        $this->assertSame(EstadoEncomienda::TRANSITO, $e->getEstado());
    }

    public function testSeEntregaEnSuDestinoOEnTransitoHaciaEl(): void
    {
        $this->assertNull($this->flujo->rechazoEntrega($this->encomienda(EstadoEncomienda::DESEMBARCADA, self::FLORES)));
        // El bus llegó y nadie la desembarcó: se entrega igual.
        $this->assertNull($this->flujo->rechazoEntrega($this->encomienda(EstadoEncomienda::TRANSITO, null, $this->salida(EstadoSalida::INICIADA))));

        // En un transbordo todavía no; tampoco si su salida no pasa por el destino o no ha salido.
        $this->assertSame("estado", $this->flujo->rechazoEntrega($this->encomienda(EstadoEncomienda::DESEMBARCADA, self::RIO_DULCE))?->codigo);
        $this->assertSame("estado", $this->flujo->rechazoEntrega($this->encomienda(EstadoEncomienda::TRANSITO, null, $this->salida(EstadoSalida::INICIADA, [self::GUATE, self::RIO_DULCE])))?->codigo);
        $this->assertSame("estado", $this->flujo->rechazoEntrega($this->encomienda(EstadoEncomienda::EMBARCADA, null, $this->salida(EstadoSalida::ABORDANDO)))?->codigo);
    }

    public function testSeAnulaSiNoHaViajadoYLaDelBoletoConSuVenta(): void
    {
        $this->flujo->exigir($this->encomienda(), TransicionEncomienda::ANULAR);
        // Se bajó en el origen antes de salir.
        $this->flujo->exigir($this->encomienda(EstadoEncomienda::DESEMBARCADA, self::GUATE), TransicionEncomienda::ANULAR);

        $this->assertSame("estado", $this->flujo->rechazo($this->encomienda(EstadoEncomienda::DESEMBARCADA, self::RIO_DULCE), TransicionEncomienda::ANULAR)?->codigo);
        $this->assertSame("estado", $this->flujo->rechazo($this->encomienda(EstadoEncomienda::TRANSITO, null), TransicionEncomienda::ANULAR)?->codigo);
        $this->assertSame("con_boleto", $this->flujo->rechazo($this->encomienda(modalidad: ModalidadEncomienda::CON_BOLETO), TransicionEncomienda::ANULAR)?->codigo);

        $conBoleto = $this->encomienda(EstadoEncomienda::EMBARCADA, null, $this->salida(EstadoSalida::PROGRAMADA), ModalidadEncomienda::CON_BOLETO);
        $this->assertTrue($this->flujo->puede($conBoleto, TransicionEncomienda::ANULAR_CON_BOLETO));
        $this->assertFalse($this->flujo->puede($this->encomienda(EstadoEncomienda::TRANSITO, null, null, ModalidadEncomienda::CON_BOLETO), TransicionEncomienda::ANULAR_CON_BOLETO));
        $this->assertFalse($this->flujo->puede($this->encomienda(), TransicionEncomienda::ANULAR_CON_BOLETO));

        try {
            $this->flujo->aplicar($this->encomienda(EstadoEncomienda::ENTREGADA, null), TransicionEncomienda::ANULAR);
            $this->fail("Una entregada no se anula.");
        } catch (EncomiendaRechazada $x) {
            $this->assertSame("estado", $x->codigo);
            $this->assertSame(422, $x->estadoHttp);
        }
    }

    public function testLasAccionesDelListado(): void
    {
        $acciones = $this->flujo->acciones($this->encomienda());

        $this->assertNull($acciones["embarcar"]);
        $this->assertNull($acciones["anular"]);
        $this->assertSame("La encomienda está recibida: solo se desembarca una encomienda embarcada o en tránsito.", $acciones["desembarcar"]);
        $this->assertSame("La encomienda está recibida: solo se entrega una encomienda desembarcada en su destino o en tránsito hacia él.", $acciones["entregar"]);
    }

    public function testElRolDeSistemaLaAnulaEnCualquierEstado(): void
    {
        $entregada = $this->encomienda(EstadoEncomienda::ENTREGADA, null);
        $transito = $this->encomienda(EstadoEncomienda::TRANSITO, null, $this->salida(EstadoSalida::INICIADA));
        $transbordo = $this->encomienda(EstadoEncomienda::DESEMBARCADA, self::RIO_DULCE);
        $conBoleto = $this->encomienda(EstadoEncomienda::ENTREGADA, null, null, ModalidadEncomienda::CON_BOLETO);

        // Sin el rol de sistema, como siempre.
        $this->assertSame(
            "La encomienda está entregada: solo se anula una encomienda que no ha viajado (en la bodega de su origen).",
            $this->flujo->rechazo($entregada, TransicionEncomienda::ANULAR)?->getMessage(),
        );
        $this->assertSame("estado", $this->flujo->rechazo($transbordo, TransicionEncomienda::ANULAR)?->codigo);
        $this->assertFalse($this->flujo->puede($conBoleto, TransicionEncomienda::ANULAR_CON_BOLETO));

        $usuario = (new Usuario())->setUsername("sistema");
        $usuario->setId(99);
        self::getContainer()->get("security.token_storage")->setToken(new UsernamePasswordToken($usuario, "api"));
        (new \ReflectionProperty(Autorizacion::class, "cache"))->setValue(
            self::getContainer()->get(Autorizacion::class),
            [99 => new PermisosUsuario([new Concesion(1, 1, "Sistema", true, [], [], Ambito::total())], [])],
        );

        $this->assertNull($this->flujo->rechazo($transito, TransicionEncomienda::ANULAR));
        $this->assertNull($this->flujo->rechazo($transbordo, TransicionEncomienda::ANULAR));
        $this->assertNull($this->flujo->acciones($entregada)["anular"]);
        // La que va con un boleto, solo con su venta (que ahora la anula aunque ya se haya entregado).
        $this->assertSame("con_boleto", $this->flujo->rechazo($conBoleto, TransicionEncomienda::ANULAR)?->codigo);
        $this->assertTrue($this->flujo->puede($conBoleto, TransicionEncomienda::ANULAR_CON_BOLETO));
        // Lo demás sigue sus reglas.
        $this->assertSame("estado", $this->flujo->rechazo($entregada, TransicionEncomienda::EMBARCAR)?->codigo);

        $this->flujo->aplicar($entregada, TransicionEncomienda::ANULAR, ["motivo" => "Prueba"]);
        $this->assertSame(EstadoEncomienda::ANULADA, $entregada->getEstado());
        $this->assertSame("estado", $this->flujo->rechazo($entregada, TransicionEncomienda::ANULAR)?->codigo);
    }

    public function testConAutorizacionFueraDeEstadoSeAnulaComoConElRolDeSistema(): void
    {
        $entregada = $this->encomienda(EstadoEncomienda::ENTREGADA, null);
        $transito = $this->encomienda(EstadoEncomienda::TRANSITO, null, $this->salida(EstadoSalida::INICIADA));
        $transbordo = $this->encomienda(EstadoEncomienda::DESEMBARCADA, self::RIO_DULCE);
        $conBoleto = $this->encomienda(EstadoEncomienda::ENTREGADA, null, null, ModalidadEncomienda::CON_BOLETO);
        $anulada = $this->encomienda(EstadoEncomienda::ANULADA, null);

        // Solo su estado la detiene: se pide una autorización fuera de estado (ADR-058).
        $this->assertTrue(ReglasEncomienda::soloPorEstado($entregada));
        $this->assertTrue(ReglasEncomienda::soloPorEstado($transito));
        $this->assertTrue(ReglasEncomienda::soloPorEstado($transbordo));
        // En la bodega de su origen no hace falta; la del boleto se anula con su venta; la anulada, nunca.
        $this->assertFalse(ReglasEncomienda::soloPorEstado($this->encomienda()));
        $this->assertFalse(ReglasEncomienda::soloPorEstado($conBoleto));
        $this->assertFalse(ReglasEncomienda::soloPorEstado($anulada));

        // Todas las de prueba son la 123: la dispensa las cubre a todas.
        self::getContainer()->get(DispensasEnCurso::class)->permitirEncomiendaFueraDeEstado(123);
        $this->assertNull($this->flujo->rechazo($entregada, TransicionEncomienda::ANULAR));
        $this->assertNull($this->flujo->rechazo($transito, TransicionEncomienda::ANULAR));
        $this->assertNull($this->flujo->rechazo($transbordo, TransicionEncomienda::ANULAR));
        // La del boleto, solo con su venta (también ya entregada, si se anula con la autorización de sus boletos).
        $this->assertSame("con_boleto", $this->flujo->rechazo($conBoleto, TransicionEncomienda::ANULAR)?->codigo);
        $this->assertTrue($this->flujo->puede($conBoleto, TransicionEncomienda::ANULAR_CON_BOLETO));
        $this->assertSame("estado", $this->flujo->rechazo($anulada, TransicionEncomienda::ANULAR)?->codigo);
        // Lo demás sigue sus reglas.
        $this->assertSame("estado", $this->flujo->rechazo($entregada, TransicionEncomienda::EMBARCAR)?->codigo);
    }

    /** @param list<int> $paradas */
    private function salida(EstadoSalida $estado, array $paradas = [self::GUATE, self::RIO_DULCE, self::FLORES]): Salida
    {
        $ruta = new Ruta();
        foreach ($paradas as $orden => $id) {
            $ruta->getParadas()->add(new Parada($ruta, $this->enclaves[$id], $orden));
        }
        $salida = (new Salida())->setRuta($ruta);
        // Por sus estados en orden, como lo exige `Salida::setEstado`.
        foreach ([EstadoSalida::ABORDANDO, EstadoSalida::INICIADA, EstadoSalida::FINALIZADA] as $paso) {
            if ($salida->getEstado() === $estado) {
                break;
            }
            $salida->setEstado($paso);
        }

        return $salida;
    }

    /** Guatemala → Flores, en `$ubicacion` (null = a bordo o ya fuera de bodega). */
    private function encomienda(
        EstadoEncomienda $estado = EstadoEncomienda::RECIBIDA,
        ?int $ubicacion = self::GUATE,
        ?Salida $salida = null,
        ModalidadEncomienda $modalidad = ModalidadEncomienda::PAGADA,
    ): Encomienda {
        $e = (new Encomienda())
            ->setOrigen($this->enclaves[self::GUATE])
            ->setDestino($this->enclaves[self::FLORES])
            ->setModalidad($modalidad)
            ->setUbicacion($ubicacion === null ? null : $this->enclaves[$ubicacion])
            ->setSalida($salida);
        (new \ReflectionProperty(Encomienda::class, "id"))->setValue($e, 123);
        (new \ReflectionProperty(Encomienda::class, "estado"))->setValue($e, $estado);

        return $e;
    }
}
