<?php

declare(strict_types=1);

namespace App\Bitacora;

use App\Entity\BoletoAsiento;
use App\Entity\Bus;
use App\Entity\Encomienda;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\Enum\OperacionEncomienda;
use App\Entity\Enum\OperacionSalida;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\Salida;
use App\Entity\Usuario;
use App\Venta\Boleto\Flujo\FlujoBoleto;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Bitácora automática de `Salida`, `BoletoAsiento` y `Encomienda`: engancha los eventos de
 * Doctrine, así queda anotado cualquier cambio sin importar por dónde llegue
 * (venta, gestión de salidas, GraphQL, consola).
 *
 * Cada fila se escribe con DBAL dentro de la misma transacción del flush: si
 * la operación se revierte, su bitácora también. Los datos que solo conoce el
 * servicio que opera (el motivo de una anulación) se pasan con `anotar()`
 * antes del flush. Un boleto cambia de estado por una transición del
 * workflow (ADR-037): su nombre y el contexto que trae van al detalle solos.
 *
 * No anota lo que viene de la migración del legado (registro con
 * `legacyId` y sin usuario en sesión): son miles de filas sin autor.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postRemove)]
final class RegistradorBitacora implements ResetInterface
{
    /** @var \WeakMap<object, array<string, mixed>> datos extra por registro (un objeto liberado no deja su nota a otro) */
    private \WeakMap $notas;

    /** @var \WeakMap<object, array{id: int, detalle: array<string, mixed>}> salidas por eliminar */
    private \WeakMap $porEliminar;

    public function __construct(
        private readonly Security $security,
    ) {
        $this->reset();
    }

    /**
     * Datos que se agregan a la próxima entrada de ese registro.
     *
     * @param array<string, mixed> $detalle
     */
    public function anotar(object $entidad, array $detalle): void
    {
        $this->notas[$entidad] = [...($this->notas[$entidad] ?? []), ...$detalle];
    }

    /**
     * La transición del boleto (`transicion`: cancelar desde el listado y no
     * presentarse dejan el mismo estado) y lo que el servicio pasó en el
     * contexto (`FlujoBoleto::BITACORA`).
     */
    #[AsEventListener(event: "workflow." . TransicionBoleto::WORKFLOW . ".transition")]
    public function alTransicionar(TransitionEvent $event): void
    {
        $this->anotar($event->getSubject(), ["transicion" => $event->getTransition()?->getName(), ...($event->getContext()[FlujoBoleto::BITACORA] ?? [])]);
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $e = $args->getObject();
        if ($e instanceof Salida) {
            $this->escribir($args->getObjectManager()->getConnection(), $e, (int) $e->getId(), OperacionSalida::CREADA, [
                "fecha" => $e->getFecha()?->format(DATE_ATOM),
                "bus" => $e->getBus()?->getCodigo(),
                "ruta" => $e->getRuta()?->getNombre(),
            ]);
        } elseif ($e instanceof BoletoAsiento) {
            $this->escribir($args->getObjectManager()->getConnection(), $e, (int) $e->getId(), OperacionBoleto::CREADO, [
                "venta" => $e->getBoletoVenta()?->getId(),
                "canal" => $e->getBoletoVenta()?->getCanal()->value,
                "salida" => $e->getSalida()?->getId(),
                "asiento" => $e->getAsiento()?->getNumero(),
                "trayecto" => self::viaje($e->getTrayecto()),
                "precio" => $e->getPrecio()?->getAmount(),
                "reasignadoDe" => $e->getReasignadoDe()?->getId(),
            ]);
        } elseif ($e instanceof Encomienda) {
            $this->escribir($args->getObjectManager()->getConnection(), $e, (int) $e->getId(), OperacionEncomienda::deEstado($e->getEstado()), [
                "modalidad" => $e->getModalidad()->value,
                "origen" => $e->getOrigen()?->getNombre(),
                "destino" => $e->getDestino()?->getNombre(),
                "bultos" => $e->getBultos(),
                "total" => $e->getTotal()->getAmount(),
                "venta" => $e->getBoletoVenta()?->getId(),
                "salida" => $e->getSalida()?->getId(),
            ]);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $e = $args->getObject();
        $conexion = $args->getObjectManager()->getConnection();
        if ($e instanceof Salida) {
            if ($args->hasChangedField("estado")) {
                $nuevo = self::estado(EstadoSalida::class, $args->getNewValue("estado"));
                $operacion = OperacionSalida::deEstado($nuevo);
                if ($operacion !== null) {
                    $this->escribir($conexion, $e, (int) $e->getId(), $operacion, [
                        "de" => self::estado(EstadoSalida::class, $args->getOldValue("estado"))->value,
                        "a" => $nuevo->value,
                    ]);
                }
            }
            if ($args->hasChangedField("bus")) {
                $this->escribir($conexion, $e, (int) $e->getId(), OperacionSalida::CAMBIO_BUS, [
                    "de" => $args->getOldValue("bus") instanceof Bus ? $args->getOldValue("bus")->getCodigo() : null,
                    "a" => $args->getNewValue("bus") instanceof Bus ? $args->getNewValue("bus")->getCodigo() : null,
                ]);
            }
        } elseif ($e instanceof Encomienda && $args->hasChangedField("estado")) {
            $nuevo = self::estado(EstadoEncomienda::class, $args->getNewValue("estado"));
            $this->escribir($conexion, $e, (int) $e->getId(), OperacionEncomienda::deEstado($nuevo), [
                "de" => self::estado(EstadoEncomienda::class, $args->getOldValue("estado"))->value,
                "a" => $nuevo->value,
                "salida" => $nuevo->aBordo() || $nuevo === EstadoEncomienda::DESEMBARCADA ? self::salida($e->getSalida()) : null,
            ]);
        } elseif ($e instanceof Encomienda && $args->hasChangedField("ruta")) {
            $this->escribir($conexion, $e, (int) $e->getId(), OperacionEncomienda::RUTA_CAMBIADA, [
                "ruta" => $e->getRuta()?->getNombre(),
            ]);
        } elseif ($e instanceof BoletoAsiento && $args->hasChangedField("estado")) {
            $operacion = match (self::estado(EstadoBoletoAsiento::class, $args->getNewValue("estado"))) {
                EstadoBoletoAsiento::ANULADO => OperacionBoleto::ANULADO,
                EstadoBoletoAsiento::REASIGNADO => OperacionBoleto::REASIGNADO,
                EstadoBoletoAsiento::CHEQUEADO => OperacionBoleto::CHEQUEADO,
                EstadoBoletoAsiento::TRANSITO => OperacionBoleto::TRANSITO,
                EstadoBoletoAsiento::CANCELADO => OperacionBoleto::CANCELADO,
                EstadoBoletoAsiento::FINALIZADO => OperacionBoleto::FINALIZADO,
                EstadoBoletoAsiento::POSPUESTO => OperacionBoleto::POSPUESTO,
                // Solo se vuelve a emitido revirtiendo un cancelado (`TransicionBoleto::REVERTIR`).
                EstadoBoletoAsiento::EMITIDO => OperacionBoleto::REVERTIDO,
                default => null,
            };
            if ($operacion !== null) {
                $this->escribir($conexion, $e, (int) $e->getId(), $operacion, [
                    "venta" => $e->getBoletoVenta()?->getId(),
                    "salida" => $e->getSalida()?->getId(),
                    "asiento" => $e->getAsiento()?->getNumero(),
                ]);
            }
        }
    }

    /** El id de una entidad borrada ya no está en `postRemove`: se toma antes. */
    public function onFlush(OnFlushEventArgs $args): void
    {
        foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityDeletions() as $e) {
            if ($e instanceof Salida) {
                $this->porEliminar[$e] = ["id" => (int) $e->getId(), "detalle" => [
                    "fecha" => $e->getFecha()?->format(DATE_ATOM),
                    "bus" => $e->getBus()?->getCodigo(),
                    "ruta" => $e->getRuta()?->getNombre(),
                ]];
            }
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $e = $args->getObject();
        $pendiente = $this->porEliminar[$e] ?? null;
        if ($pendiente === null) {
            return;
        }
        unset($this->porEliminar[$e]);
        $this->escribir($args->getObjectManager()->getConnection(), $e, $pendiente["id"], OperacionSalida::ELIMINADA, $pendiente["detalle"]);
    }

    public function reset(): void
    {
        $this->notas = new \WeakMap();
        $this->porEliminar = new \WeakMap();
    }

    /**
     * @param array<string, mixed> $detalle
     */
    private function escribir(Connection $conexion, object $entidad, int $id, Operacion $operacion, array $detalle): void
    {
        $tipo = TipoRegistro::deObjeto($entidad);
        $notas = $this->notas[$entidad] ?? [];
        unset($this->notas[$entidad]);
        $usuario = $this->security->getUser();
        $usuario = $usuario instanceof Usuario ? $usuario : null;

        // Migrado del legado: sin usuario en sesión y con identificador del sistema anterior.
        if ($tipo === null || ($usuario === null && $entidad->getLegacyId() !== null)) {
            return;
        }

        $detalle = array_filter([...$detalle, ...$notas], static fn(mixed $v) => $v !== null);
        $conexion->insert(
            "bitacora",
            [
                "entidad" => $tipo->value,
                "registro_id" => $id,
                "operacion" => $operacion->value,
                "usuario_id" => $usuario?->getId(),
                "usuario_nombre" => $usuario === null ? null : mb_substr((string) ($usuario->getFullName() ?: $usuario->getUsername()), 0, 100),
                "fecha" => new \DateTimeImmutable(),
                "detalle" => $detalle === [] ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ],
            ["fecha" => Types::DATETIME_IMMUTABLE],
        );
    }

    /**
     * El conjunto de cambios de Doctrine guarda los enums por su valor.
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    private static function estado(string $enum, \BackedEnum|string $valor): \BackedEnum
    {
        return $valor instanceof \BackedEnum ? $valor : $enum::from($valor);
    }

    /** «1234 · 18/10/2026 14:30 · Guatemala → Flores (bus 101)». */
    private static function salida(?Salida $s): ?string
    {
        return $s === null ? null : sprintf(
            "%d · %s · %s → %s%s",
            $s->getId(),
            $s->getFecha()?->format("d/m/Y H:i"),
            $s->getRuta()?->getOrigen()?->getNombre(),
            $s->getRuta()?->getDestino()?->getNombre(),
            $s->getBus() !== null ? sprintf(" (bus %s)", $s->getBus()->getCodigo()) : "",
        );
    }

    private static function viaje(?\App\Entity\Trayecto $t): ?string
    {
        return $t === null ? null : sprintf("%s → %s", $t->getOrigen()?->getNombre(), $t->getDestino()?->getNombre());
    }
}
