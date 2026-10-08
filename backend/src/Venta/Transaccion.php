<?php

declare(strict_types=1);

namespace App\Venta;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Transacción explícita para las operaciones de venta. A diferencia de
 * `wrapInTransaction`, no cierra el EntityManager si algo falla (en modo
 * worker el mismo EM atiende las peticiones siguientes): revierte y
 * desprende lo que quedó a medias.
 *
 * `despuesDeConfirmar()` difiere efectos externos (avisos, eventos) hasta el
 * commit de la transacción más externa; si se revierte, se descartan.
 */
final class Transaccion
{
    private int $profundidad = 0;

    /** @var list<callable(): void> */
    private array $pendientes = [];

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
        if ($this->profundidad > 0) {
            // Anidada: es parte de la externa (un solo commit, un solo rollback).
            return $operacion();
        }
        $conexion = $this->em->getConnection();
        $conexion->beginTransaction();
        $this->profundidad = 1;
        try {
            $resultado = $operacion();
            $this->em->flush();
            $conexion->commit();
        } catch (\Throwable $e) {
            if ($conexion->isTransactionActive()) {
                $conexion->rollBack();
            }
            $this->em->clear();
            $this->pendientes = [];

            throw $e;
        } finally {
            $this->profundidad = 0;
        }
        $this->liberar();

        return $resultado;
    }

    /** Ejecuta `$efecto` tras el commit (o ya, si no hay transacción en curso). */
    public function despuesDeConfirmar(callable $efecto): void
    {
        if ($this->profundidad === 0) {
            $efecto();

            return;
        }
        $this->pendientes[] = $efecto;
    }

    private function liberar(): void
    {
        [$pendientes, $this->pendientes] = [$this->pendientes, []];
        foreach ($pendientes as $efecto) {
            $efecto();
        }
    }
}
