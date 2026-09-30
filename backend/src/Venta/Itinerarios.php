<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Subtrayecto;
use App\Entity\Trayecto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Lee el `Itinerario` de un trayecto desde sus subtrayectos. Memoiza por
 * petición (se vacía en cada request: FrankenPHP en modo worker reutiliza
 * el servicio).
 */
final class Itinerarios implements ResetInterface
{
    /** @var array<int, Itinerario> */
    private array $cache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function deTrayecto(Trayecto $trayecto): Itinerario
    {
        return $this->deTrayectos([$trayecto])[$trayecto->getId()];
    }

    /**
     * @param iterable<Trayecto> $trayectos
     *
     * @return array<int, Itinerario> por id de trayecto
     */
    public function deTrayectos(iterable $trayectos): array
    {
        $faltan = [];
        $resultado = [];
        foreach ($trayectos as $t) {
            if (isset($this->cache[$t->getId()])) {
                $resultado[$t->getId()] = $this->cache[$t->getId()];
            } else {
                $faltan[$t->getId()] = $t;
            }
        }
        if ($faltan === []) {
            return $resultado;
        }

        $filas = $this->em->createQueryBuilder()
            ->select(
                "IDENTITY(s.belowTo) AS padre",
                "t.id AS trayecto",
                "IDENTITY(t.origen) AS origen",
                "IDENTITY(t.destino) AS destino",
                "t.duracionEstimadaMinutos AS minutos",
            )
            ->from(Subtrayecto::class, "s")
            ->join("s.trayecto", "t")
            ->where("s.belowTo IN (:padres)")
            ->andWhere("s.activo IS NULL OR s.activo = true")
            ->setParameter("padres", array_keys($faltan))
            ->getQuery()
            ->getArrayResult();

        $porPadre = [];
        foreach ($filas as $f) {
            $porPadre[(int) $f["padre"]][] = [
                "trayecto" => (int) $f["trayecto"],
                "origen" => (int) $f["origen"],
                "destino" => (int) $f["destino"],
                "minutos" => $f["minutos"] !== null ? (int) $f["minutos"] : null,
            ];
        }

        foreach ($faltan as $id => $t) {
            $resultado[$id] = $this->cache[$id] = Itinerario::construir(
                $id,
                (int) $t->getOrigen()->getId(),
                (int) $t->getDestino()->getId(),
                $porPadre[$id] ?? [],
                $t->getDuracionEstimadaMinutos(),
            );
        }

        return $resultado;
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
