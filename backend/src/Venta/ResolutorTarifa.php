<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use App\Entity\Salida;
use App\Entity\Trayecto;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Money\Currency;
use Money\Money;

/**
 * Tarifa (`BoletoTarifa`) de un asiento en una salida para un trayecto, por
 * especificidad (`EspecificidadTarifa`). El trayecto puede ser el de la salida
 * o uno de sus subtrayectos: en ambos casos se usan la empresa y el bus de la
 * salida, y la hora estimada en el origen del trayecto (la de la salida si es
 * su origen; sin hora si no se conoce la duración hasta esa parada). Sin
 * tarifa que aplique, el asiento no se vende.
 *
 * Lee con DBAL a propósito: el `TenantFilter` del ORM dejaría fuera las
 * tarifas sin empresa (comodín), que también aplican.
 */
final class ResolutorTarifa
{
    public function __construct(
        private readonly Connection $conexion,
        private readonly HorasSalida $horas,
    ) {}

    public function precio(Salida $salida, Trayecto $trayecto, Asiento $asiento): ?Money
    {
        return $this->tarifa($salida, $trayecto, $asiento)?->precio;
    }

    public function tarifa(Salida $salida, Trayecto $trayecto, Asiento $asiento): ?CandidatoTarifa
    {
        return $this->porClase($salida, $trayecto, [$asiento->getClase()->value])[$asiento->getClase()->value] ?? null;
    }

    /**
     * Mejor tarifa por clase de asiento. Una tarifa sin clase compite en todas.
     *
     * @param list<string> $clases
     *
     * @return array<string, CandidatoTarifa>
     */
    public function porClase(Salida $salida, Trayecto $trayecto, array $clases): array
    {
        if ($clases === []) {
            return [];
        }
        $empresaId = $salida->getEmpresa()?->getId();
        $busId = $salida->getBus()?->getId();
        $hora = $this->horas->enParada($salida, (int) $trayecto->getOrigen()->getId())?->format("H:i");

        // Solo las que pueden aplicar: el trayecto exacto y, en lo demás, el
        // valor de la salida o comodín. La prioridad la decide la regla pura.
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT id, precio_monto, precio_moneda, clase, empresa_id, trayecto_id, bus_id, TO_CHAR(hora, 'HH24:MI') AS hora
               FROM boleto_tarifa
              WHERE trayecto_id = :trayecto
                AND (clase IN (:clases) OR clase IS NULL)
                AND (empresa_id = :empresa OR empresa_id IS NULL)
                AND (hora IS NULL OR TO_CHAR(hora, 'HH24:MI') = CAST(:hora AS TEXT))
                AND (bus_id = :bus OR bus_id IS NULL)",
            [
                "trayecto" => $trayecto->getId(),
                "clases" => array_values(array_unique($clases)),
                "empresa" => $empresaId ?? 0,
                "hora" => $hora,
                "bus" => $busId ?? 0,
            ],
            ["clases" => ArrayParameterType::STRING],
        );

        $candidatos = array_map(
            static fn(array $f) => new CandidatoTarifa(
                (int) $f["id"],
                new Money((int) $f["precio_monto"], new Currency($f["precio_moneda"])),
                (int) $f["trayecto_id"],
                $f["empresa_id"] !== null ? (int) $f["empresa_id"] : null,
                $f["hora"],
                $f["bus_id"] !== null ? (int) $f["bus_id"] : null,
                $f["clase"],
            ),
            $filas,
        );

        $resultado = [];
        foreach (array_unique($clases) as $clase) {
            $elegida = EspecificidadTarifa::elegir(
                $candidatos,
                $clase,
                (int) $trayecto->getId(),
                $empresaId,
                $hora,
                $busId,
            );
            if ($elegida !== null) {
                $resultado[$clase] = $elegida;
            }
        }

        return $resultado;
    }
}
