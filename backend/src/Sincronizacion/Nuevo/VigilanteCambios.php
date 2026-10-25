<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Cliente;
use App\Entity\Encomienda;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Sincronizacion\Interruptor;
use App\Venta\Excepcion\AsientosNoDisponibles;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Transaccion;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Lleva al legado lo que cambia aquí mientras la sincronización está
 * encendida (ADR-042). Engancha el flush de Doctrine: no importa por dónde
 * llegue el cambio (venta, reasignación, abordaje, página web, GraphQL).
 *
 * - Lo que ocupa un asiento (un boleto nuevo, revertir una cancelación) se
 *   escribe allá primero, dentro de la transacción de aquí: si el legado ya
 *   vendió el asiento, la operación se rechaza y aquí no queda nada.
 * - Lo que libera un asiento o solo cambia el estado de un boleto, después
 *   del commit (si aquí se revierte, allá no pasó). Si el legado no
 *   responde, queda en `sync_cambio` y lo reintenta el ciclo.
 * - Encomiendas, clientes y facturas van a `sync_cambio`: los lleva el ciclo.
 *
 * Solo ve lo que pasa por Doctrine: lo que trae la importación del legado
 * (DBAL) no vuelve allá.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
class VigilanteCambios implements ResetInterface
{
    /** @var \WeakMap<BoletoAsiento, int> boleto nuevo → su id en el legado (se guarda al insertarlo aquí) */
    private \WeakMap $escritos;

    /** @var list<\Closure(): void> lo que se lleva al legado tras el commit */
    private array $despues = [];

    /** Hubo cambios que esperan en `sync_cambio`: el final de la petición los lleva. */
    private bool $pendientes = false;

    public function __construct(
        private readonly Interruptor $interruptor,
        private readonly EscritorLegado $escritor,
        private readonly Transaccion $transaccion,
        private readonly LoggerInterface $logger,
    ) {
        $this->reset();
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $db = $em->getConnection();
        if (!$this->interruptor->activo()) {
            return;
        }

        /** @var list<string> boletos del legado que este flush libera: no estorban a los que ocupa */
        $liberados = [];
        foreach ($uow->getScheduledEntityUpdates() as $e) {
            if ($e instanceof BoletoAsiento) {
                $cambios = $uow->getEntityChangeSet($e);
                if (isset($cambios["estado"])) {
                    $this->estadoDeBoleto($db, $e, self::estado($cambios["estado"][0]), $liberados);
                } elseif (array_intersect(array_keys($cambios), ["cliente", "asiento"]) !== []) {
                    $this->encolar($db, "boleto", (int) $e->getId());
                }
            } elseif ($e instanceof BoletoVenta && array_intersect(array_keys($uow->getEntityChangeSet($e)), ["factura", "estado", "estadoFacturacion"]) !== []) {
                $this->encolar($db, "venta", (int) $e->getId());
            } elseif ($e instanceof Encomienda) {
                $this->encolar($db, "encomienda", (int) $e->getId());
            } elseif ($e instanceof Cliente) {
                $this->encolar($db, "cliente", (int) $e->getId());
            }
        }

        $nuevos = [];
        foreach ($uow->getScheduledEntityInsertions() as $e) {
            if (!$e instanceof BoletoAsiento || in_array($e->getEstado()->value, EstadoBoletoAsiento::liberanAsiento(), true)) {
                continue;
            }
            $de = $e->getReasignadoDe();
            $dto = BoletoParaLegado::de($e, Uuid::v4()->toRfc4122(), $de === null ? null : self::legacyId($db, $de));
            if ($dto === null) {
                // La salida se creó aquí: el legado no la tiene ni puede venderla.
                continue;
            }
            $nuevos[] = [$e, $dto];
        }
        if ($nuevos !== []) {
            $ids = $this->ocupar(array_column($nuevos, 1), $liberados);
            foreach ($nuevos as [$boleto, $dto]) {
                $this->escritos[$boleto] = $ids[$dto->clave];
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $e) {
            if ($e instanceof BoletoAsiento && ($legado = self::legacyId($db, $e)) !== null) {
                $this->despues[] = fn() => $this->anularOEncolar($db, $legado);
            }
        }
    }

    /** El id del legado del boleto recién escrito allá, en la misma transacción. */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $e = $args->getObject();
        $db = $args->getObjectManager()->getConnection();
        if ($e instanceof BoletoAsiento && isset($this->escritos[$e])) {
            $db->executeStatement("UPDATE boleto_asiento SET legacy_id = :l WHERE id = :id", ["l" => (string) $this->escritos[$e], "id" => $e->getId()]);
            unset($this->escritos[$e]);
        } elseif ($e instanceof Encomienda && $this->interruptor->activo()) {
            $this->encolar($db, "encomienda", (int) $e->getId());
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        [$despues, $this->despues] = [$this->despues, []];
        foreach ($despues as $efecto) {
            // Dentro de una `Transaccion`, tras su commit; si no, ya (el flush ya confirmó).
            $this->transaccion->despuesDeConfirmar($efecto);
        }
    }

    /** ¿Quedaron cambios en `sync_cambio` en esta petición? Los lleva `AlTerminar`. */
    public function hayPendientes(): bool
    {
        return $this->pendientes;
    }

    public function reset(): void
    {
        $this->escritos = new \WeakMap();
        $this->despues = [];
        $this->pendientes = false;
    }

    /**
     * Un cambio de estado de un boleto que ya está en el legado. Volver a
     * ocupar el asiento se comprueba allá ahora; lo demás, tras el commit.
     *
     * @param list<string> $liberados
     */
    private function estadoDeBoleto(Connection $db, BoletoAsiento $b, EstadoBoletoAsiento $antes, array &$liberados): void
    {
        $legado = self::legacyId($db, $b);
        if ($legado === null) {
            return;
        }
        $estado = Traduccion::estadoBoleto($b->getEstado());
        if (Traduccion::ocupa($estado) && !Traduccion::ocupa(Traduccion::estadoBoleto($antes))) {
            $this->conLegado(fn() => $this->escritor->cambiarEstado($legado, $estado, $b->getBoletoVenta()?->getUsuario()?->getId(), "Revertido en FDN."));

            return;
        }
        if (!Traduccion::ocupa($estado)) {
            $liberados[] = $legado;
        }
        $id = (int) $b->getId();
        $this->despues[] = function () use ($db, $id, $legado, $estado, $b) {
            try {
                $this->escritor->cambiarEstado($legado, $estado, null, "Cambio en FDN: " . $b->getEstado()->etiqueta() . ".");
            } catch (\Throwable $e) {
                $this->logger->warning("Sincronización: el boleto {id} queda para el ciclo: {error}", ["id" => $id, "error" => $e->getMessage()]);
                $this->encolar($db, "boleto", $id);
            }
        };
    }

    /**
     * @param list<BoletoParaLegado> $boletos
     * @param list<string>           $liberados
     *
     * @return array<string, int>
     */
    private function ocupar(array $boletos, array $liberados): array
    {
        return $this->conLegado(fn() => $this->escritor->ocupar($boletos, $liberados));
    }

    /**
     * Lo que ocupa asientos no se hace aquí si el legado no confirma: mejor
     * rechazar la venta que venderla dos veces.
     *
     * @template T
     *
     * @param \Closure(): T $operacion
     *
     * @return T
     */
    private function conLegado(\Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (AsientosNoDisponibles $e) {
            throw new AsientosNoDisponibles($e->getMessage() . " (vendido en el sistema anterior)", $e->codigo, $e->estadoHttp, $e->detalle, $e);
        } catch (VentaRechazada $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error("Sincronización: el legado no confirmó el asiento: {error}", ["error" => $e->getMessage(), "exception" => $e]);

            throw new VentaRechazada("No se pudo confirmar el asiento en el sistema anterior. Intente de nuevo en un momento.", "sincronizacion_legado", 503, [], $e);
        }
    }

    private function anularOEncolar(Connection $db, string $legado): void
    {
        try {
            $this->escritor->cambiarEstado($legado, Traduccion::estadoBoleto(EstadoBoletoAsiento::ANULADO), null, "Venta no confirmada en FDN.");
        } catch (\Throwable $e) {
            $this->logger->warning("Sincronización: el boleto {legado} del legado queda para anular en el ciclo: {error}", ["legado" => $legado, "error" => $e->getMessage()]);
            $this->encolar($db, "boleto_anular", (int) $legado);
        }
    }

    private function encolar(Connection $db, string $entidad, int $id): void
    {
        $db->executeStatement("INSERT INTO sync_cambio (entidad, registro_id) VALUES (:e, :id)", ["e" => $entidad, "id" => $id]);
        $this->pendientes = true;
    }

    /** El de la base: el de la entidad en memoria puede no estar (se guarda con DBAL). */
    private static function legacyId(Connection $db, BoletoAsiento $b): ?string
    {
        if ($b->getId() === null) {
            return null;
        }
        $legado = $db->fetchOne("SELECT legacy_id FROM boleto_asiento WHERE id = :id", ["id" => $b->getId()]);

        return $legado === false || $legado === null ? null : (string) $legado;
    }

    private static function estado(EstadoBoletoAsiento|string $v): EstadoBoletoAsiento
    {
        return $v instanceof EstadoBoletoAsiento ? $v : EstadoBoletoAsiento::from($v);
    }
}
