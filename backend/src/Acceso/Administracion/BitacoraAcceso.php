<?php

declare(strict_types=1);

namespace App\Acceso\Administracion;

use App\Bitacora\Operacion;
use App\Bitacora\TipoRegistro;
use App\Entity\Usuario;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bitácora de accesos (ADR-039): cada cambio de asignaciones, roles,
 * activación o contraseña queda en `bitacora` (entidad `usuario` o `rol`)
 * con quién lo hizo, en la misma transacción que el cambio.
 */
final class BitacoraAcceso
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** @param array<string, mixed> $detalle */
    public function anotar(TipoRegistro $tipo, int $id, Operacion $operacion, ?Usuario $quien, array $detalle = []): void
    {
        $detalle = array_filter($detalle, static fn(mixed $v) => $v !== null);
        $this->em->getConnection()->insert(
            'bitacora',
            [
                'entidad' => $tipo->value,
                'registro_id' => $id,
                'operacion' => $operacion->value,
                'usuario_id' => $quien?->getId(),
                'usuario_nombre' => $quien === null ? null : mb_substr((string) ($quien->getFullName() ?: $quien->getUsername()), 0, 100),
                'fecha' => new \DateTimeImmutable(),
                'detalle' => $detalle === [] ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ],
            ['fecha' => Types::DATETIME_IMMUTABLE],
        );
    }
}
