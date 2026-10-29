<?php

declare(strict_types=1);

namespace App\Ruta;

use App\Entity\Ruta;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Carga de una vez las paradas de varias rutas, con su enclave y sus tiempos
 * por clase de bus: puntas (`getOrigen()`, `getDestino()`) y duración sin una
 * consulta por ruta. Sin esto es peor que un N+1 común: `Parada::enclave`
 * apunta a una jerarquía (`Enclave`/`Estacion`, tabla única) y Doctrine no
 * puede dejarlo perezoso, así que trae cada enclave con su propia consulta.
 */
final class ParadasDeRutas
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @param list<int> $rutaIds */
    public function cargar(array $rutaIds): void
    {
        if ($rutaIds === []) {
            return;
        }
        $this->em->createQueryBuilder()
            ->select("r", "p", "e", "t")
            ->from(Ruta::class, "r")
            ->leftJoin("r.paradas", "p")
            ->leftJoin("p.enclave", "e")
            ->leftJoin("p.tiempos", "t")
            ->where("r.id IN (:ids)")
            ->orderBy("p.orden", "ASC")
            ->setParameter("ids", $rutaIds)
            ->getQuery()
            ->getResult();
    }
}
