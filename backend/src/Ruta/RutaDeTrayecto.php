<?php

declare(strict_types=1);

namespace App\Ruta;

use App\Entity\Ruta;
use App\Entity\Trayecto;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ruta para una salida que todavía se elige por trayecto (programador y
 * edición, hasta la fase 5 de ADR-030): la ruta activa con esas mismas
 * puntas que más salidas tiene. Transitorio: se retira cuando la salida y
 * el esquema se programen eligiendo la ruta.
 */
final class RutaDeTrayecto
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function para(Trayecto $trayecto): ?Ruta
    {
        $id = $this->em->getConnection()->fetchOne(
            "SELECT r.id FROM ruta r
               JOIN parada po ON po.ruta_id = r.id AND po.orden = 0 AND po.enclave_id = :o
               JOIN parada pd ON pd.ruta_id = r.id AND pd.enclave_id = :d
                AND pd.orden = (SELECT MAX(orden) FROM parada WHERE ruta_id = r.id)
              WHERE r.activo
              ORDER BY (SELECT COUNT(*) FROM salida s WHERE s.ruta_id = r.id) DESC, r.id
              LIMIT 1",
            ["o" => $trayecto->getOrigen()->getId(), "d" => $trayecto->getDestino()->getId()],
        );

        return $id === false ? null : $this->em->find(Ruta::class, (int) $id);
    }
}
