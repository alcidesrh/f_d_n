<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use App\Entity\Recorrido;
use App\Entity\ReservaAsiento;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Carrito de la página web: asientos apartados (`ReservaAsiento`) mientras
 * el cliente paga. Un carrito es un recorrido + un trayecto; cambiar de viaje
 * es empezar otro. Apartar extiende todo el carrito (vencimiento deslizante)
 * hasta `DURACION_MINUTOS`, sin pasar del cierre de venta en línea.
 */
final class Reservas
{
    public const DURACION_MINUTOS = 15;
    public const MAX_ASIENTOS = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly ReglasVenta $reglas,
        private readonly PublicadorOcupacion $publicador,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Aparta un asiento; crea el carrito si `$token` es null.
     *
     * @throws VentaRechazada
     */
    public function apartar(?Uuid $token, int $recorridoId, ?int $trayectoId, int $asientoId): Uuid
    {
        $token ??= Uuid::v4();
        $this->transaccion->ejecutar(function () use ($token, $recorridoId, $trayectoId, $asientoId) {
            $recorrido = $this->em->find(Recorrido::class, $recorridoId, LockMode::PESSIMISTIC_WRITE)
                ?? throw new VentaRechazada("El recorrido no existe.", "no_encontrado", 404);
            $this->reglas->exigirVendibleEnLinea($recorrido);
            $trayecto = $this->reglas->trayecto($recorrido, $trayectoId);

            $carrito = $this->delCarrito($token, soloVigentes: true);
            foreach ($carrito as $r) {
                if ($r->getRecorrido()->getId() !== $recorrido->getId() || $r->getTrayecto()->getId() !== $trayecto->getId()) {
                    throw new VentaRechazada("Su selección es de otro viaje: termine o vacíe esa compra primero.", "carrito_otro_viaje", 409);
                }
                if ($r->getAsiento()->getId() === $asientoId) {
                    return;
                }
            }
            if (count($carrito) >= self::MAX_ASIENTOS) {
                throw new VentaRechazada(sprintf("Puede comprar hasta %d asientos por compra.", self::MAX_ASIENTOS), "carrito_lleno");
            }

            [$asiento] = $this->reglas->asientos($recorrido, [$asientoId]);
            $this->reglas->exigirDisponibles($recorrido, $this->reglas->tramo($recorrido, $trayecto), [$asiento], $token->toRfc4122());
            // Reglas de tarifa: no se aparta lo que no se puede cobrar.
            $this->reglas->cotizar($recorrido, $trayecto, [$asiento]);

            $this->borrarVencidas($token);
            $expira = $this->vencimiento($recorrido);
            $this->em->persist(new ReservaAsiento($token, $recorrido, $asiento, $trayecto, $expira));
            foreach ($carrito as $r) {
                $r->extenderHasta($expira);
            }
        });
        $this->publicador->cambio($recorridoId);

        return $token;
    }

    public function liberar(Uuid $token, int $asientoId): void
    {
        $recorridoId = null;
        $this->transaccion->ejecutar(function () use ($token, $asientoId, &$recorridoId) {
            foreach ($this->delCarrito($token) as $r) {
                if ($r->getAsiento()->getId() === $asientoId) {
                    $recorridoId = $r->getRecorrido()->getId();
                    $this->em->remove($r);
                }
            }
        });
        if ($recorridoId !== null) {
            $this->publicador->cambio($recorridoId);
        }
    }

    public function vaciar(Uuid $token): void
    {
        $recorridos = [];
        $this->transaccion->ejecutar(function () use ($token, &$recorridos) {
            foreach ($this->delCarrito($token) as $r) {
                $recorridos[$r->getRecorrido()->getId()] = true;
                $this->em->remove($r);
            }
        });
        foreach (array_keys($recorridos) as $id) {
            $this->publicador->cambio($id);
        }
    }

    /**
     * Reservas vigentes del carrito.
     *
     * @return list<ReservaAsiento>
     */
    public function vigentes(Uuid $token): array
    {
        return $this->delCarrito($token, soloVigentes: true);
    }

    /**
     * Mantiene el carrito apartado mientras se procesa el pago (3-D Secure
     * puede tardar), sin pasar del cierre de venta en línea.
     */
    public function extender(Uuid $token): void
    {
        $this->transaccion->ejecutar(function () use ($token) {
            foreach ($this->delCarrito($token, soloVigentes: true) as $r) {
                $r->extenderHasta($this->vencimiento($r->getRecorrido()));
            }
        });
    }

    /** Borra las reservas vencidas de todos los carritos. */
    public function purgar(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->delete(ReservaAsiento::class, "r")
            ->where("r.expiraEn <= :ahora")
            ->setParameter("ahora", $this->reloj->now())
            ->getQuery()
            ->execute();
    }

    private function vencimiento(Recorrido $recorrido): \DateTimeImmutable
    {
        $deslizante = $this->reloj->now()->modify(sprintf("+%d minutes", self::DURACION_MINUTOS));
        $cierre = $this->reglas->cierreEnLinea($recorrido);

        return min($deslizante, $cierre);
    }

    /**
     * @return list<ReservaAsiento>
     */
    private function delCarrito(Uuid $token, bool $soloVigentes = false): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("r", "a")
            ->from(ReservaAsiento::class, "r")
            ->join("r.asiento", "a")
            ->where("r.token = :token")
            ->setParameter("token", $token, "uuid")
            ->orderBy("a.numero");
        if ($soloVigentes) {
            $qb->andWhere("r.expiraEn > :ahora")->setParameter("ahora", $this->reloj->now());
        }

        return $qb->getQuery()->getResult();
    }

    private function borrarVencidas(Uuid $token): void
    {
        foreach ($this->delCarrito($token) as $r) {
            if (!$r->vigente($this->reloj->now())) {
                $this->em->remove($r);
            }
        }
        $this->em->flush();
    }
}
