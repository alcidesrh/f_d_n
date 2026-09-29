<?php

declare(strict_types=1);

namespace App\Venta;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Avisa por Mercure que cambió la ocupación de un recorrido (venta, reserva
 * o liberación). El aviso no lleva datos de clientes: los croquis abiertos
 * (taquilla y página) vuelven a pedir la ocupación.
 */
final class PublicadorOcupacion
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {}

    public static function topico(int $recorridoId): string
    {
        return sprintf("/recorridos/%d/ocupacion", $recorridoId);
    }

    public function cambio(int $recorridoId): void
    {
        try {
            $this->hub->publish(new Update(
                self::topico($recorridoId),
                json_encode(["recorrido" => $recorridoId], JSON_THROW_ON_ERROR),
            ));
        } catch (\Throwable $e) {
            // Sin Mercure los croquis se refrescan igual al reconsultar.
            $this->logger->warning("No se pudo publicar la ocupación del recorrido {id}: {error}", [
                "id" => $recorridoId,
                "error" => $e->getMessage(),
            ]);
        }
    }
}
