<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/** Clase de artículo de una encomienda: decide su tarifa (ADR-035). */
enum TipoArticuloEncomienda: string
{
    /** Cualquier bulto: tarifa por peso más recargo por distancia. */
    case PAQUETE = "paquete";
    /** Un tipo del catálogo `EncomiendaEspecial` (sobre, llanta, bicicleta…): su precio fijo. */
    case ESPECIAL = "especial";
}
