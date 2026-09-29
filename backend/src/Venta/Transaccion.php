<?php

declare(strict_types=1);

namespace App\Venta;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Transacción explícita para las operaciones de venta. A diferencia de
 * `wrapInTransaction`, no cierra el EntityManager si algo falla (en modo
 * worker el mismo EM atiende las peticiones siguientes): revierte y
 * desprende lo que quedó a medias.
 */
final class Transaccion
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @template T
     *
     * @param callable(): T $operacion
     *
     * @return T
     */
    public function ejecutar(callable $operacion): mixed
    {
        $conexion = $this->em->getConnection();
        $conexion->beginTransaction();
        try {
            $resultado = $operacion();
            $this->em->flush();
            $conexion->commit();

            return $resultado;
        } catch (\Throwable $e) {
            if ($conexion->isTransactionActive()) {
                $conexion->rollBack();
            }
            $this->em->clear();

            throw $e;
        }
    }
}
