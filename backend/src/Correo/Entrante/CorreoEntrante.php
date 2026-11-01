<?php

declare(strict_types=1);

namespace App\Correo\Entrante;

/**
 * Un correo que llegó de fuera, ya leído del formato del proveedor. Las
 * direcciones son listas `{direccion, nombre}`; los adjuntos traen el
 * contenido decodificado.
 */
final class CorreoEntrante
{
    /**
     * @param array{direccion: string, nombre: ?string}            $de
     * @param list<array{direccion: string, nombre: ?string}>      $para
     * @param list<array{direccion: string, nombre: ?string}>      $cc
     * @param list<array{direccion: string, nombre: ?string}>      $cco  los que el proveedor sabe que lo reciben sin figurar
     * @param list<array{nombre: string, tipo: string, contenido: string, contentId: ?string, enLinea: bool}> $adjuntos
     */
    public function __construct(
        public readonly array $de,
        public readonly array $para,
        public readonly array $cc,
        public readonly array $cco,
        public readonly ?string $responderA,
        public readonly string $asunto,
        public readonly string $texto,
        public readonly ?string $html,
        public readonly ?string $messageId,
        public readonly ?string $enRespuestaA,
        public readonly ?string $referencias,
        public readonly array $adjuntos,
    ) {}

    /** @return list<string> todas las direcciones a las que va */
    public function destinatarios(): array
    {
        return array_values(array_unique(array_map(static fn(array $d) => $d["direccion"], [...$this->para, ...$this->cc, ...$this->cco])));
    }
}
