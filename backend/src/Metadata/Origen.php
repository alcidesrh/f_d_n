<?php

declare(strict_types=1);

namespace App\Metadata;

/** Qué creó una revisión de la metadata del cliente. */
enum Origen: string
{
    /** Se guardó configuración en modo automático. */
    case AUTOMATICA = "automatica";
    /** Un administrador publicó. */
    case MANUAL = "manual";
    /** Cambió el schema GraphQL o la configuración al desplegar (o es la primera revisión). */
    case DESPLIEGUE = "despliegue";
}
