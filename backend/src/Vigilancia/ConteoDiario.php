<?php

declare(strict_types=1);

namespace App\Vigilancia;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Lo que hizo hoy un usuario de lo que tiene límite diario (ADR-039, ADR-044):
 * boletos anulados y reasignados (por la bitácora, donde quedan con quien los
 * hizo) y asientos de cortesía emitidos (sin los ya anulados). "Hoy" es desde
 * la medianoche de America/Guatemala.
 */
final class ConteoDiario
{
    public function __construct(
        private readonly Connection $db,
        private readonly ClockInterface $reloj,
    ) {}

    public function anulados(Usuario $usuario): int
    {
        return $this->deBitacora($usuario, OperacionBoleto::ANULADO);
    }

    public function reasignados(Usuario $usuario): int
    {
        return $this->deBitacora($usuario, OperacionBoleto::REASIGNADO);
    }

    public function cortesias(Usuario $usuario): int
    {
        return (int) $this->db->fetchOne(
            "SELECT COUNT(b.id) FROM boleto_asiento b JOIN boleto_venta v ON v.id = b.boleto_venta_id
              WHERE v.usuario_id = :usuario AND v.cortesia = true AND v.created_at >= :hoy AND b.estado <> :anulado",
            ["usuario" => $usuario->getId(), "hoy" => $this->hoy(), "anulado" => EstadoBoletoAsiento::ANULADO->value],
        );
    }

    private function deBitacora(Usuario $usuario, OperacionBoleto $operacion): int
    {
        return (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM bitacora WHERE entidad = 'boleto' AND operacion = :operacion AND usuario_id = :usuario AND fecha >= :hoy",
            ["operacion" => $operacion->value, "usuario" => $usuario->getId(), "hoy" => $this->hoy()],
        );
    }

    private function hoy(): string
    {
        return \DateTimeImmutable::createFromInterface($this->reloj->now())->setTime(0, 0)->format("Y-m-d H:i:s");
    }
}
