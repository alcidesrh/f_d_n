<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\OperacionBoleto;
use Doctrine\DBAL\Connection;

/**
 * Quién anuló, reasignó o emitió cortesías en un día (ADR-044), para el
 * resumen diario: a diferencia del tablero (que cuenta a quien vendió), aquí
 * cuenta quien hizo la operación (la bitácora). Por empresa de la salida.
 */
final class ConsultaIncidencias
{
    public const TOP = 5;

    public function __construct(private readonly Connection $db) {}

    /**
     * @return array{anulados: list<array{nombre: string, usuario: string, boletos: int}>, reasignados: list<array{nombre: string, usuario: string, boletos: int}>, cortesias: list<array{nombre: string, usuario: string, boletos: int}>}
     */
    public function porUsuario(\DateTimeImmutable $dia, ?int $empresa): array
    {
        $rango = ["desde" => $dia->setTime(0, 0)->format("Y-m-d H:i:s"), "hasta" => $dia->setTime(0, 0)->modify("+1 day")->format("Y-m-d H:i:s")];
        $deEmpresa = $empresa === null ? "" : " AND s.empresa_id = :empresa";
        $parametros = $rango + ($empresa === null ? [] : ["empresa" => $empresa]);
        $usuario = "COALESCE(NULLIF(TRIM(CONCAT(u.nombre, ' ', u.apellido)), ''), u.username) AS nombre, u.username AS usuario";

        $deBitacora = fn(OperacionBoleto $op) => $this->db->fetchAllAssociative(
            "SELECT {$usuario}, COUNT(*) AS boletos
               FROM bitacora k
               JOIN usuario u ON u.id = k.usuario_id
               JOIN boleto_asiento b ON b.id = k.registro_id
               JOIN salida s ON s.id = b.salida_id
              WHERE k.entidad = 'boleto' AND k.operacion = :operacion AND k.fecha >= :desde AND k.fecha < :hasta{$deEmpresa}
              GROUP BY u.id, u.nombre, u.apellido, u.username
              ORDER BY boletos DESC, nombre
              LIMIT " . self::TOP,
            $parametros + ["operacion" => $op->value],
        );
        $cortesias = $this->db->fetchAllAssociative(
            "SELECT {$usuario}, COUNT(b.id) AS boletos
               FROM boleto_venta v
               JOIN usuario u ON u.id = v.usuario_id
               JOIN boleto_asiento b ON b.boleto_venta_id = v.id
               JOIN salida s ON s.id = b.salida_id
              WHERE v.cortesia = true AND v.created_at >= :desde AND v.created_at < :hasta AND b.estado <> :anulado{$deEmpresa}
              GROUP BY u.id, u.nombre, u.apellido, u.username
              ORDER BY boletos DESC, nombre
              LIMIT " . self::TOP,
            $parametros + ["anulado" => EstadoBoletoAsiento::ANULADO->value],
        );
        $filas = static fn(array $lista) => array_map(static fn(array $f) => [
            "nombre" => (string) $f["nombre"],
            "usuario" => (string) $f["usuario"],
            "boletos" => (int) $f["boletos"],
        ], $lista);

        return [
            "anulados" => $filas($deBitacora(OperacionBoleto::ANULADO)),
            "reasignados" => $filas($deBitacora(OperacionBoleto::REASIGNADO)),
            "cortesias" => $filas($cortesias),
        ];
    }
}
