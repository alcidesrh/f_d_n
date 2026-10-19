<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Bitacora\RegistradorBitacora;
use App\Entity\BoletoVenta;
use App\Entity\Cliente;
use App\Entity\Encomienda;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Estacion;
use App\Entity\Moneda;
use App\Entity\Salida;
use App\Entity\TipoPago;
use App\Entity\Usuario;
use App\Venta\Excepcion\FacturacionFallida;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\Facturador;
use App\Venta\ReglasVenta;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Embarcar, desembarcar, entregar y anular encomiendas (ADR-035). Las reglas
 * puras están en `ReglasEncomienda`; la bitácora la escribe el listener al
 * cambiar el estado (con la estación y la salida que se anotan aquí).
 */
final class MovimientosEncomienda
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly RegistradorBitacora $bitacora,
        private readonly Facturador $facturador,
        private readonly ReglasVenta $reglasVenta,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Sube las encomiendas a la salida. Las que no se pueden quedan en
     * `fallidas` con el motivo; las demás se embarcan igual.
     *
     * @param list<int> $ids
     * @param callable(Encomienda): bool $puedeVer
     *
     * @return array{embarcadas: list<int>, fallidas: list<array{id: int, codigo: string, error: string}>}
     */
    public function embarcar(array $ids, int $salidaId, Usuario $usuario, callable $puedeVer): array
    {
        return $this->transaccion->ejecutar(function () use ($ids, $salidaId, $usuario, $puedeVer) {
            $salida = $this->em->find(Salida::class, $salidaId) ?? throw new EncomiendaRechazada("La salida no existe.", "no_encontrado", 404);
            $paradas = RutasEncomienda::idsDeSalida($salida);
            $estacion = $usuario->getEstacion()?->getId();

            return $this->porCada($ids, $puedeVer, "embarcadas", function (Encomienda $e) use ($salida, $paradas, $estacion) {
                $nuevo = ReglasEncomienda::alEmbarcar(
                    $e->getEstado(),
                    $e->getUbicacion()?->getId(),
                    $estacion,
                    $salida->getEstado(),
                    $paradas,
                    $e->getEmpresa()?->getId(),
                    $salida->getEmpresa()?->getId(),
                );
                $this->bitacora->anotar($e, ["estacion" => $e->getUbicacion()?->getNombre()]);
                $e->setEstado($nuevo)->setSalida($salida)->setUbicacion(null);
            });
        });
    }

    /**
     * Baja las encomiendas en una parada de su salida: la estación del
     * usuario o, si es de oficina, la indicada.
     *
     * @param list<int> $ids
     * @param callable(Encomienda): bool $puedeVer
     *
     * @return array{desembarcadas: list<int>, fallidas: list<array{id: int, codigo: string, error: string}>}
     */
    public function desembarcar(array $ids, ?int $estacionId, Usuario $usuario, callable $puedeVer): array
    {
        return $this->transaccion->ejecutar(function () use ($ids, $estacionId, $usuario, $puedeVer) {
            $estacion = $usuario->getEstacion()
                ?? ($estacionId !== null ? $this->em->find(Estacion::class, $estacionId) : null)
                ?? throw new EncomiendaRechazada("Indique la estación donde se desembarcan.");

            return $this->porCada($ids, $puedeVer, "desembarcadas", function (Encomienda $e) use ($estacion) {
                $salida = $e->getSalida() ?? throw new EncomiendaRechazada("La encomienda no va en ninguna salida.", "estado");
                ReglasEncomienda::exigirDesembarcable($e->getEstado(), (int) $estacion->getId(), RutasEncomienda::idsDeSalida($salida));
                $this->bitacora->anotar($e, ["estacion" => $estacion->getNombre()]);
                $e->setEstado(EstadoEncomienda::DESEMBARCADA)->setUbicacion($estacion);
            });
        });
    }

    /**
     * Entrega la encomienda a quien la retira. Si es por cobrar, se cobra y se
     * certifica la factura al cliente que paga antes de registrar la entrega:
     * si el certificador falla no cambia nada (o, con permiso, contingencia).
     *
     * @throws EncomiendaRechazada|FacturacionFallida
     */
    public function entregar(int $id, EntregaEncomienda $s, Usuario $usuario, bool $permiteSinFactura): Encomienda
    {
        $encomienda = $this->em->find(Encomienda::class, $id) ?? throw new EncomiendaRechazada("La encomienda no existe.", "no_encontrado", 404);
        ReglasEncomienda::exigirEntregable(
            $encomienda->getEstado(),
            $encomienda->getUbicacion()?->getId(),
            (int) $encomienda->getDestino()?->getId(),
            $usuario->getEstacion()?->getId(),
            $encomienda->getSalida() !== null ? RutasEncomienda::idsDeSalida($encomienda->getSalida()) : [],
        );
        $receptor = $s->receptorId !== null
            ? ($this->em->find(Cliente::class, $s->receptorId) ?? throw new EncomiendaRechazada("El cliente que retira no existe."))
            : $encomienda->getDestinatario();
        $cobra = $encomienda->getModalidad() === ModalidadEncomienda::POR_COBRAR && !$encomienda->getTotal()->isZero();
        $ahora = $this->reloj->now();

        if ($cobra) {
            if ($s->sinFacturaElectronica && !$permiteSinFactura) {
                throw new EncomiendaRechazada("No tiene permiso para cobrar sin factura electrónica.", "permiso", 403);
            }
            $paga = $s->clienteFacturaId !== null
                ? ($this->em->find(Cliente::class, $s->clienteFacturaId) ?? throw new EncomiendaRechazada("El cliente a facturar no existe."))
                : $receptor;
            $this->reglasVenta->exigirReceptorFacturable($paga?->getNit(), $encomienda->getTotal());
            $encomienda
                ->setClienteFactura($paga)
                ->setTipoPago($s->tipoPagoId ? $this->em->find(TipoPago::class, $s->tipoPagoId) : null)
                ->setMoneda($s->monedaId ? $this->em->find(Moneda::class, $s->monedaId) : null)
                ->setEntregadaEn($ahora)
                ->setEstadoFacturacion(EstadoFacturacion::PENDIENTE);
            if ($s->sinFacturaElectronica) {
                $encomienda
                    ->setNumeroAcceso(Facturador::nuevoNumeroAcceso())
                    ->setErrorFacturacion("Encomienda en contingencia: sin factura electrónica.");
            } else {
                try {
                    $this->facturador->certificarEncomienda($encomienda);
                } catch (CertificacionFallida $e) {
                    $this->em->refresh($encomienda);
                    throw FacturacionFallida::por($e->getMessage(), $e->recuperable, $permiteSinFactura && $e->recuperable);
                } catch (\Throwable $e) {
                    $this->em->refresh($encomienda);
                    throw FacturacionFallida::por(CertificacionFallida::sinRespuesta($e)->getMessage(), true, $permiteSinFactura);
                }
            }
        }

        $this->transaccion->ejecutar(function () use ($encomienda, $receptor, $ahora) {
            $this->em->lock($encomienda, LockMode::PESSIMISTIC_WRITE);
            if ($encomienda->getEstado()->aBordo()) {
                // Llegó en tránsito: primero se desembarca en su destino (queda en la bitácora).
                $this->bitacora->anotar($encomienda, ["estacion" => $encomienda->getDestino()?->getNombre()]);
                $encomienda->setEstado(EstadoEncomienda::DESEMBARCADA)->setUbicacion($encomienda->getDestino());
                $this->em->flush();
            }
            $this->bitacora->anotar($encomienda, ["estacion" => $encomienda->getUbicacion()?->getNombre(), "retira" => $receptor?->getNombreCompleto()]);
            $encomienda
                ->setEstado(EstadoEncomienda::ENTREGADA)
                ->setReceptor($receptor)
                ->setEntregadaEn($ahora)
                ->setUbicacion(null);
        });

        return $encomienda;
    }

    /**
     * Entrega varias a la misma persona (la «entrega múltiple» del legado).
     * Cada una por su cuenta: la por cobrar con su propia factura al mismo
     * cliente; las que no se pueden quedan en `fallidas` con el motivo (y,
     * si falló el certificador, `recuperable` para reintentarlas).
     *
     * @param list<int> $ids
     * @param callable(Encomienda): bool $puedeVer
     *
     * @return array{entregadas: list<int>, fallidas: list<array<string, mixed>>}
     */
    public function entregarVarias(array $ids, EntregaEncomienda $s, Usuario $usuario, bool $permiteSinFactura, callable $puedeVer): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn($id) => is_int($id) && $id > 0)));
        if ($ids === []) {
            throw new EncomiendaRechazada("Elija al menos una encomienda.");
        }
        if (count($ids) > 100) {
            throw new EncomiendaRechazada("Demasiadas encomiendas en una sola entrega.");
        }
        $hechas = [];
        $fallidas = [];
        foreach ($ids as $id) {
            $e = $this->em->find(Encomienda::class, $id);
            if ($e === null || !$puedeVer($e)) {
                $fallidas[] = ["id" => $id, "codigo" => sprintf("%08d", $id), "error" => "No existe o no es de su empresa."];
                continue;
            }
            try {
                $this->entregar($id, $s, $usuario, $permiteSinFactura);
                $hechas[] = $id;
            } catch (FacturacionFallida $x) {
                $fallidas[] = ["id" => $id, "codigo" => $e->getCodigo(), "error" => $x->getMessage(), "facturacion" => true, "recuperable" => (bool) ($x->detalle["recuperable"] ?? false)];
            } catch (EncomiendaRechazada $x) {
                $fallidas[] = ["id" => $id, "codigo" => $e->getCodigo(), "error" => $x->getMessage()];
            }
        }

        return ["entregadas" => $hechas, "fallidas" => $fallidas];
    }

    /**
     * Cambia la ruta por la que viajará una encomienda que espera en bodega
     * (pendientes de envío): una que pase por donde está y luego por su
     * destino. No cambia la empresa (ni su factura).
     *
     * @throws EncomiendaRechazada
     */
    public function cambiarRuta(int $id, int $rutaId, Usuario $usuario, RutasEncomienda $rutas): Encomienda
    {
        return $this->transaccion->ejecutar(function () use ($id, $rutaId, $usuario, $rutas) {
            $e = $this->em->find(Encomienda::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw new EncomiendaRechazada("La encomienda no existe.", "no_encontrado", 404);
            $aqui = $e->getUbicacion()?->getId();
            if (!$e->getEstado()->enBodega() || $aqui === null) {
                throw new EncomiendaRechazada("Solo se cambia la ruta de una encomienda en bodega.", "estado");
            }
            $estacion = $usuario->getEstacion()?->getId();
            if ($estacion !== null && $estacion !== $aqui) {
                throw new EncomiendaRechazada("La encomienda está en la bodega de otra estación.", "otra_estacion", 403);
            }
            $ruta = null;
            foreach ($rutas->entre($aqui, (int) $e->getDestino()?->getId()) as $r) {
                if ($r->getId() === $rutaId) {
                    $ruta = $r;
                }
            }
            if ($ruta === null) {
                throw new EncomiendaRechazada("Esa ruta no pasa por donde está la encomienda y luego por su destino.", "fuera_de_ruta");
            }
            if ($ruta->getId() !== $e->getRuta()?->getId()) {
                $this->bitacora->anotar($e, ["estacion" => $e->getUbicacion()?->getNombre(), "antes" => $e->getRuta()?->getNombre()]);
                $e->setRuta($ruta);
            }

            return $e;
        });
    }

    /**
     * Anula una encomienda que no ha viajado. La pagada anula antes su factura
     * en el certificador (si falla, no cambia nada).
     *
     * @throws EncomiendaRechazada
     */
    public function anular(int $id, string $motivo, Usuario $usuario): Encomienda
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new EncomiendaRechazada("Indique el motivo de la anulación.");
        }
        $encomienda = $this->em->find(Encomienda::class, $id) ?? throw new EncomiendaRechazada("La encomienda no existe.", "no_encontrado", 404);
        ReglasEncomienda::exigirAnulable(
            $encomienda->getEstado(),
            $encomienda->getModalidad(),
            $encomienda->getUbicacion()?->getId(),
            (int) $encomienda->getOrigen()?->getId(),
            $usuario->getEstacion()?->getId(),
        );

        if ($encomienda->getFactura() !== null && !$encomienda->getFactura()->isAnulada()) {
            try {
                $this->facturador->anularFactura($encomienda->getFactura(), $motivo);
            } catch (CertificacionFallida $e) {
                throw new EncomiendaRechazada("El certificador no anuló la factura: " . $e->getMessage(), "facturacion", 502);
            }
            // Ya anulada en el certificador: se guarda enseguida, así un reintento no vuelve a llamarlo.
            $this->em->flush();
        }

        $this->transaccion->ejecutar(function () use ($encomienda, $motivo) {
            $this->em->lock($encomienda, LockMode::PESSIMISTIC_WRITE);
            $this->anularEnTransaccion($encomienda, $motivo);
        });

        return $encomienda;
    }

    /**
     * Inició la salida (abordaje): sus encomiendas embarcadas pasan a tránsito.
     * Dentro de la transacción de quien inicia.
     */
    public function alIniciarSalida(Salida $salida): int
    {
        /** @var list<Encomienda> $embarcadas */
        $embarcadas = $this->em->getRepository(Encomienda::class)->findBy(["salida" => $salida, "estado" => EstadoEncomienda::EMBARCADA->value]);
        foreach ($embarcadas as $e) {
            $e->setEstado(EstadoEncomienda::TRANSITO);
        }

        return count($embarcadas);
    }

    /**
     * Se anularon todos los boletos de la venta: sus encomiendas (que van con
     * ellos) también. La factura de la venta ya se anuló completa. Dentro de
     * la transacción de la anulación.
     */
    public function alAnularVenta(BoletoVenta $venta, string $motivo): void
    {
        foreach ($venta->getEncomiendas() as $e) {
            if ($e->getEstado()->puedeTransicionarA(EstadoEncomienda::ANULADA)) {
                $this->anularEnTransaccion($e, $motivo);
            }
        }
    }

    private function anularEnTransaccion(Encomienda $e, string $motivo): void
    {
        $this->bitacora->anotar($e, ["motivo" => $motivo]);
        $e->setEstado(EstadoEncomienda::ANULADA)
            ->setUbicacion(null)
            ->setMotivoAnulacion($motivo);
        if ($e->getEstadoFacturacion() !== EstadoFacturacion::NO_APLICA) {
            // También la pendiente (contingencia): así el cron no la certifica.
            $e->setEstadoFacturacion(EstadoFacturacion::ANULADA);
        }
    }

    /**
     * @param list<int> $ids
     * @param callable(Encomienda): bool $puedeVer
     * @param callable(Encomienda): void $operacion
     *
     * @return array<string, list<mixed>>
     */
    private function porCada(array $ids, callable $puedeVer, string $clave, callable $operacion): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn($id) => is_int($id) && $id > 0)));
        if ($ids === []) {
            throw new EncomiendaRechazada("Elija al menos una encomienda.");
        }
        if (count($ids) > 300) {
            throw new EncomiendaRechazada("Demasiadas encomiendas en una sola operación.");
        }
        $hechas = [];
        $fallidas = [];
        foreach ($ids as $id) {
            $e = $this->em->find(Encomienda::class, $id, LockMode::PESSIMISTIC_WRITE);
            if ($e === null || !$puedeVer($e)) {
                $fallidas[] = ["id" => $id, "codigo" => sprintf("%08d", $id), "error" => "No existe o no es de su empresa."];
                continue;
            }
            try {
                $operacion($e);
                $hechas[] = $id;
            } catch (EncomiendaRechazada $x) {
                $fallidas[] = ["id" => $id, "codigo" => $e->getCodigo(), "error" => $x->getMessage()];
            }
        }

        return [$clave => $hechas, "fallidas" => $fallidas];
    }
}
