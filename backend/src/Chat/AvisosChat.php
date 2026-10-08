<?php

declare(strict_types=1);

namespace App\Chat;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Avisos del chat por Mercure, en un tópico privado por usuario (solo lo
 * escucha quien tiene el token que entrega `token()`). El aviso no lleva
 * tarjetas: el cliente pide el mensaje y lo ve con sus propios permisos.
 *
 * Tipos: `mensaje` (nuevo en un canal), `leido` (el usuario leyó en otro
 * dispositivo), `canal` (lo agregaron a un grupo).
 */
final class AvisosChat
{
    private const VIGENCIA_TOKEN = "+12 hours";

    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {}

    public static function topico(int $usuarioId): string
    {
        return sprintf("/chat/usuarios/%d", $usuarioId);
    }

    /** @return array{token: string, topico: string, hub: string} */
    public function token(int $usuarioId): array
    {
        $factory = $this->hub->getFactory() ?? throw new ChatRechazado("Mercure no está configurado.", 503);

        return [
            "token" => $factory->create([self::topico($usuarioId)], [], ["exp" => new \DateTimeImmutable(self::VIGENCIA_TOKEN)]),
            "topico" => self::topico($usuarioId),
            "hub" => $this->hub->getPublicUrl(),
        ];
    }

    /**
     * @param list<int>            $usuarios
     * @param array<string, mixed> $datos
     */
    public function avisar(array $usuarios, string $tipo, array $datos): void
    {
        if ($usuarios === []) {
            return;
        }
        try {
            $this->hub->publish(new Update(
                array_map(self::topico(...), array_values(array_unique($usuarios))),
                json_encode(["tipo" => $tipo, ...$datos], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $e) {
            // Sin Mercure el cliente igual se entera al refrescar la bandeja.
            $this->logger->warning("No se pudo avisar por el chat: {error}", ["error" => $e->getMessage()]);
        }
    }
}
