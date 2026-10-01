<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use App\Entity\Salida;
use App\Entity\ReservaAsiento;
use App\Venta\EnLinea\SolicitudCarrito;
use App\Venta\Excepcion\AsientosNoDisponibles;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Carrito de la página web (ADR-023): asientos apartados (`ReservaAsiento`,
 * la "precompra") mientras el cliente paga. Se apartan todos juntos al
 * pulsar "Pagar asientos": la ida y, si es ida y vuelta, el regreso. Si
 * alguno ya está ocupado no se aparta ninguno y se dice cuáles.
 *
 * Todas las reservas de un carrito vencen juntas: a los `DURACION_MINUTOS`
 * (se extiende mientras el pago sigue en curso) y nunca después de
 * `ReglasVenta::LIBERACION_MINUTOS` antes de la primera salida.
 */
final class Reservas
{
    public const DURACION_MINUTOS = 15;
    public const MAX_ASIENTOS = SolicitudCarrito::MAX_ASIENTOS;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly ReglasVenta $reglas,
        private readonly Disponibilidad $disponibilidad,
        private readonly PublicadorOcupacion $publicador,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Reemplaza el contenido del carrito (lo crea si `$token` es null) por
     * los asientos pedidos, todo o nada.
     *
     * @throws VentaRechazada si no se vende en línea o no hay tarifa
     * @throws AsientosNoDisponibles con los asientos que otro ocupó, por viaje
     */
    public function reservar(?Uuid $token, SolicitudCarrito $solicitud): Uuid
    {
        $token ??= Uuid::v4();
        $cambios = [];
        $this->transaccion->ejecutar(function () use ($token, $solicitud, &$cambios) {
            $salidas = [];
            foreach ($solicitud->salidasParaBloquear() as $id) {
                $salidas[$id] = $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE)
                    ?? throw new VentaRechazada("La salida elegida ya no existe.", "no_encontrado", 404);
            }
            // Lo que el carrito tenía antes deja de estorbar (y vuelve si algo falla: rollback).
            foreach ($this->delCarrito($token) as $r) {
                $cambios[$r->getSalida()->getId()] = true;
                $this->em->remove($r);
            }
            $this->em->flush();

            $viajes = array_map(fn(array $v) => [...$v, "entidad" => $salidas[$v["salida"]]], $solicitud->viajes);
            if (count($viajes) === 2 && $viajes[1]["entidad"]->getFecha() <= $viajes[0]["entidad"]->getFecha()) {
                throw new VentaRechazada("El regreso debe salir después de la ida.", "carrito_invalido");
            }

            $ocupados = [];
            $apartar = [];
            foreach ($viajes as $i => $v) {
                $salida = $v["entidad"];
                $this->reglas->exigirVendibleEnLinea($salida);
                $trayecto = $this->reglas->trayecto($salida, $v["trayecto"]);
                $asientos = $this->reglas->asientos($salida, $v["asientos"]);
                $estados = $this->disponibilidad->estados($salida, $this->reglas->tramo($salida, $trayecto), $token->toRfc4122());
                $noLibres = Ocupacion::noDisponibles($v["asientos"], $estados);
                if ($noLibres !== []) {
                    $ocupados[] = [
                        "viaje" => $i,
                        "salida" => (int) $salida->getId(),
                        "asientos" => array_values($noLibres),
                        "numeros" => array_values(array_map(
                            static fn(Asiento $a) => (int) $a->getNumero(),
                            array_filter($asientos, static fn(Asiento $a) => in_array($a->getId(), $noLibres, true)),
                        )),
                    ];
                    continue;
                }
                // No se aparta lo que no se puede cobrar.
                $this->reglas->cotizarEnLinea($salida, $trayecto, $asientos);
                $apartar[] = [$salida, $trayecto, $asientos];
            }
            if ($ocupados !== []) {
                throw AsientosNoDisponibles::enViajes($ocupados);
            }

            $expira = $this->vencimiento(array_values($salidas));
            foreach ($apartar as [$salida, $trayecto, $asientos]) {
                $cambios[$salida->getId()] = true;
                foreach ($asientos as $asiento) {
                    $this->em->persist(new ReservaAsiento($token, $salida, $asiento, $trayecto, $expira));
                }
            }
        });
        foreach (array_keys($cambios) as $id) {
            $this->publicador->cambio($id);
        }

        return $token;
    }

    public function vaciar(Uuid $token): void
    {
        $salidas = [];
        $this->transaccion->ejecutar(function () use ($token, &$salidas) {
            foreach ($this->delCarrito($token) as $r) {
                $salidas[$r->getSalida()->getId()] = true;
                $this->em->remove($r);
            }
        });
        foreach (array_keys($salidas) as $id) {
            $this->publicador->cambio($id);
        }
    }

    /**
     * Reservas vigentes del carrito, por salida (ida antes que regreso) y número de asiento.
     *
     * @return list<ReservaAsiento>
     */
    public function vigentes(Uuid $token): array
    {
        return $this->delCarrito($token, soloVigentes: true);
    }

    /**
     * Las reservas vigentes agrupadas por viaje, en orden de salida.
     *
     * @return list<list<ReservaAsiento>>
     */
    public function viajes(Uuid $token): array
    {
        return self::agrupar($this->vigentes($token));
    }

    /**
     * @param list<ReservaAsiento> $reservas
     *
     * @return list<list<ReservaAsiento>>
     */
    public static function agrupar(array $reservas): array
    {
        $grupos = [];
        foreach ($reservas as $r) {
            $grupos[$r->getSalida()->getId() . "-" . $r->getTrayecto()->getId()][] = $r;
        }
        $grupos = array_values($grupos);
        usort($grupos, static fn(array $a, array $b) => $a[0]->getSalida()->getFecha() <=> $b[0]->getSalida()->getFecha());

        return $grupos;
    }

    /**
     * Mantiene el carrito apartado mientras se procesa el pago (3-D Secure
     * puede tardar), sin pasar del límite de reservas de la salida.
     */
    public function extender(Uuid $token): void
    {
        $this->transaccion->ejecutar(function () use ($token) {
            $reservas = $this->delCarrito($token, soloVigentes: true);
            $expira = $this->vencimiento(array_map(static fn(ReservaAsiento $r) => $r->getSalida(), $reservas));
            foreach ($reservas as $r) {
                $r->extenderHasta($expira);
            }
        });
    }

    /**
     * Borra las reservas vencidas de todos los carritos y avisa a los
     * croquis abiertos de esas salidas (los asientos se ven libres al instante).
     */
    public function purgar(): int
    {
        $ahora = $this->reloj->now();
        $salidas = array_map("intval", array_column($this->em->createQueryBuilder()
            ->select("DISTINCT IDENTITY(r.salida) AS salida")
            ->from(ReservaAsiento::class, "r")
            ->where("r.expiraEn <= :ahora")
            ->setParameter("ahora", $ahora)
            ->getQuery()
            ->getArrayResult(), "salida"));
        $borradas = (int) $this->em->createQueryBuilder()
            ->delete(ReservaAsiento::class, "r")
            ->where("r.expiraEn <= :ahora")
            ->setParameter("ahora", $ahora)
            ->getQuery()
            ->execute();
        foreach ($salidas as $id) {
            $this->publicador->cambio($id);
        }

        return $borradas;
    }

    /**
     * Mismo vencimiento para todo el carrito: deslizante, sin pasar del
     * límite de reservas de la primera salida.
     *
     * @param list<Salida> $salidas
     */
    private function vencimiento(array $salidas): \DateTimeImmutable
    {
        $limites = array_map(fn(Salida $s) => $this->reglas->limiteReservas($s), $salidas);

        return min($this->reloj->now()->modify(sprintf("+%d minutes", self::DURACION_MINUTOS)), ...$limites);
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
            ->join("r.salida", "s")
            ->orderBy("s.fecha")
            ->addOrderBy("a.numero");
        if ($soloVigentes) {
            $qb->andWhere("r.expiraEn > :ahora")->setParameter("ahora", $this->reloj->now());
        }

        return $qb->getQuery()->getResult();
    }
}
