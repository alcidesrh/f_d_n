<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\ReservaAsiento;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cuántos asientos tiene comprometidos cada salida: boletos vivos (todo menos
 * anulado/reasignado) más reservas vigentes de la página web (un cliente que
 * está pagando). Una salida con asientos comprometidos no se edita, anula ni
 * elimina: primero hay que reasignar o anular esos asientos (ADR-024).
 */
final class AsientosVendidos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @param list<int> $salidaIds
     *
     * @return array<int, int> asientos comprometidos por salida (solo las que tienen alguno)
     */
    public function porSalida(array $salidaIds): array
    {
        if ($salidaIds === []) {
            return [];
        }
        $vendidos = [];
        $boletos = $this->em->createQueryBuilder()
            ->select("IDENTITY(b.salida) AS salida", "COUNT(DISTINCT b.asiento) AS n")
            ->from(BoletoAsiento::class, "b")
            ->where("b.salida IN (:salidas)")
            ->andWhere("b.estado NOT IN (:libres)")
            ->groupBy("b.salida")
            ->setParameter("salidas", $salidaIds)
            ->setParameter("libres", [EstadoBoletoAsiento::ANULADO->value, EstadoBoletoAsiento::REASIGNADO->value])
            ->getQuery()
            ->getArrayResult();
        foreach ($boletos as $f) {
            $vendidos[(int) $f["salida"]] = (int) $f["n"];
        }

        $reservas = $this->em->createQueryBuilder()
            ->select("IDENTITY(r.salida) AS salida", "COUNT(r.id) AS n")
            ->from(ReservaAsiento::class, "r")
            ->where("r.salida IN (:salidas)")
            ->andWhere("r.expiraEn > :ahora")
            ->groupBy("r.salida")
            ->setParameter("salidas", $salidaIds)
            ->setParameter("ahora", new \DateTimeImmutable())
            ->getQuery()
            ->getArrayResult();
        foreach ($reservas as $f) {
            $id = (int) $f["salida"];
            $vendidos[$id] = ($vendidos[$id] ?? 0) + (int) $f["n"];
        }

        return $vendidos;
    }

    /**
     * Salidas con cualquier boleto, aunque esté anulado: no se pueden borrar
     * de la base de datos sin perder el historial de ventas (se anulan).
     *
     * @param list<int> $salidaIds
     *
     * @return array<int, true>
     */
    public function conHistorial(array $salidaIds): array
    {
        if ($salidaIds === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("DISTINCT IDENTITY(b.salida) AS salida")
            ->from(BoletoAsiento::class, "b")
            ->where("b.salida IN (:salidas)")
            ->setParameter("salidas", $salidaIds)
            ->getQuery()
            ->getArrayResult();

        return array_fill_keys(array_map(static fn(array $f) => (int) $f["salida"], $filas), true);
    }
}
