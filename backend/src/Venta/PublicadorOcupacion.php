<?php

declare(strict_types=1);

namespace App\Venta;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Avisa por Mercure que cambió la ocupación de un salida (venta, reserva
 * o liberación). El aviso no lleva datos de clientes: los croquis abiertos
 * (taquilla y página) vuelven a pedir la ocupación.
 */
final class PublicadorOcupacion
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {}

    public static function topico(int $salidaId): string
    {
        return sprintf("/salidas/%d/ocupacion", $salidaId);
    }

    public function cambio(int $salidaId): void
    {
        try {
            $this->hub->publish(new Update(
                self::topico($salidaId),
                json_encode(["salida" => $salidaId], JSON_THROW_ON_ERROR),
            ));
        } catch (\Throwable $e) {
            // Sin Mercure los croquis se refrescan igual al reconsultar.
            $this->logger->warning("No se pudo publicar la ocupación del salida {id}: {error}", [
                "id" => $salidaId,
                "error" => $e->getMessage(),
            ]);
        }
    }
}
