<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Recorrido;
use App\Entity\ReservaAsiento;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Qué ocupa cada asiento de un recorrido: boletos vivos (no anulados ni
 * reasignados, incluidos los de ventas pendientes de factura) y reservas
 * web vigentes. Las reservas vencidas no cuentan aunque la fila siga ahí.
 */
final class Disponibilidad
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Itinerarios $itinerarios,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * @return list<Ocupante>
     */
    public function ocupantes(Recorrido $recorrido): array
    {
        $vendidos = $this->em->createQueryBuilder()
            ->select(
                "IDENTITY(b.asiento) AS asiento",
                "IDENTITY(b.trayecto) AS trayecto",
                "v.canal AS canal",
            )
            ->from(BoletoAsiento::class, "b")
            ->join("b.boletoVenta", "v")
            ->where("b.recorrido = :recorrido")
            ->andWhere("b.estado NOT IN (:libres)")
            ->setParameter("recorrido", $recorrido)
            ->setParameter("libres", [
                EstadoBoletoAsiento::ANULADO->value,
                EstadoBoletoAsiento::REASIGNADO->value,
            ])
            ->getQuery()
            ->getArrayResult();

        $reservas = $this->em->createQueryBuilder()
            ->select(
                "IDENTITY(r.asiento) AS asiento",
                "IDENTITY(r.trayecto) AS trayecto",
                "r.token AS token",
            )
            ->from(ReservaAsiento::class, "r")
            ->where("r.recorrido = :recorrido")
            ->andWhere("r.expiraEn > :ahora")
            ->setParameter("recorrido", $recorrido)
            ->setParameter("ahora", $this->reloj->now())
            ->getQuery()
            ->getArrayResult();

        return [
            ...array_map(
                static fn(array $f) => new Ocupante(
                    (int) $f["asiento"],
                    (int) $f["trayecto"],
                    Ocupante::VENDIDO,
                    $f["canal"] instanceof CanalVenta
                        ? $f["canal"]
                        : CanalVenta::tryFrom((string) $f["canal"]),
                ),
                $vendidos,
            ),
            ...array_map(
                static fn(array $f) => new Ocupante(
                    (int) $f["asiento"],
                    (int) $f["trayecto"],
                    Ocupante::RESERVADO,
                    null,
                    (string) $f["token"],
                ),
                $reservas,
            ),
        ];
    }

    /**
     * Estado de los asientos ocupados del recorrido para un tramo.
     *
     * @return array<int, array{estado: string, canal: ?string}>
     */
    public function estados(Recorrido $recorrido, Tramo $tramo, ?string $tokenPropio = null): array
    {
        return Ocupacion::estados(
            $this->itinerarios->deTrayecto($recorrido->getTrayecto()),
            $tramo,
            $this->ocupantes($recorrido),
            $tokenPropio,
        );
    }
}
