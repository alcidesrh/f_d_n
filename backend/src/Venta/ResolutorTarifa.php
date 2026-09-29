<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use App\Entity\Recorrido;
use App\Entity\Trayecto;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Money\Currency;
use Money\Money;

/**
 * Tarifa (`BoletoTarifa`) de un asiento en un recorrido para un trayecto,
 * por especificidad (`EspecificidadTarifa`).
 *
 * Lee con DBAL a propósito: el `TenantFilter` del ORM dejaría fuera las
 * tarifas sin empresa (comodín), que también aplican.
 */
final class ResolutorTarifa
{
    public function __construct(
        private readonly Connection $conexion,
    ) {}

    public function precio(Recorrido $recorrido, Trayecto $trayecto, Asiento $asiento): ?Money
    {
        return $this->tarifa($recorrido, $trayecto, $asiento)?->precio;
    }

    public function tarifa(Recorrido $recorrido, Trayecto $trayecto, Asiento $asiento): ?CandidatoTarifa
    {
        return $this->porClase($recorrido, $trayecto, [$asiento->getClase()->value])[$asiento->getClase()->value] ?? null;
    }

    /**
     * Mejor tarifa por clase de asiento.
     *
     * @param list<string> $clases
     *
     * @return array<string, CandidatoTarifa>
     */
    public function porClase(Recorrido $recorrido, Trayecto $trayecto, array $clases): array
    {
        if ($clases === []) {
            return [];
        }
        $empresaId = $recorrido->getEmpresa()?->getId();
        $busId = $recorrido->getBus()?->getId();

        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, precio_monto, precio_moneda, clase, empresa_id, trayecto_id, bus_id, hora
               FROM boleto_tarifa
              WHERE clase IN (:clases)
                AND (trayecto_id = :trayecto OR trayecto_id IS NULL)
                AND (empresa_id = :empresa OR empresa_id IS NULL)
                AND (bus_id = :bus OR bus_id IS NULL)',
            [
                "clases" => array_values(array_unique($clases)),
                "trayecto" => $trayecto->getId(),
                "empresa" => $empresaId ?? 0,
                "bus" => $busId ?? 0,
            ],
            ["clases" => ArrayParameterType::STRING],
        );

        $candidatos = array_map(
            static fn(array $f) => new CandidatoTarifa(
                (int) $f["id"],
                new Money((int) $f["precio_monto"], new Currency($f["precio_moneda"])),
                $f["clase"],
                $f["empresa_id"] !== null ? (int) $f["empresa_id"] : null,
                $f["trayecto_id"] !== null ? (int) $f["trayecto_id"] : null,
                $f["hora"] !== null ? substr((string) $f["hora"], 0, 5) : null,
                $f["bus_id"] !== null ? (int) $f["bus_id"] : null,
            ),
            $filas,
        );

        $hora = $recorrido->getFecha()->format("H:i");
        $resultado = [];
        foreach (array_unique($clases) as $clase) {
            $elegida = EspecificidadTarifa::elegir(
                $candidatos,
                $clase,
                $empresaId,
                (int) $trayecto->getId(),
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
