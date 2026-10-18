<?php

declare(strict_types=1);

namespace App\Chat\Push;

/** Enviar una notificación del chat a los navegadores suscritos de esos usuarios. Asíncrono. */
final readonly class EnviarAvisoPush
{
    /**
     * @param list<int>                                                           $usuarios
     * @param array{titulo: string, cuerpo: string, url: string, etiqueta: string} $contenido
     */
    public function __construct(
        public array $usuarios,
        public array $contenido,
    ) {}
}
