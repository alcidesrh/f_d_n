<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Vista propia de un tipo de registro dentro del chat (boleto, salida…). Los
 * tipos sin tarjeta propia usan `TarjetaGenerica` (etiqueta + enlace).
 *
 * `datos` debe incluir siempre `titulo`: el frontend lo usa si no conoce el tipo.
 */
#[AutoconfigureTag("app.chat.tarjeta")]
interface Tarjeta
{
    /** Nombre corto de la entidad (`BoletoAsiento`), el mismo del CRUD genérico. */
    public function tipo(): string;

    /** ¿El usuario de la petición actual puede ver este tipo de registro? */
    public function puedeVer(): bool;

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>> datos por id, solo los que existen
     */
    public function resolver(array $ids): array;
}
