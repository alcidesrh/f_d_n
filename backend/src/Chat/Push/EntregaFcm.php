<?php

declare(strict_types=1);

namespace App\Chat\Push;

/** Qué pasó con una notificación enviada a un teléfono por FCM. */
enum EntregaFcm
{
    case Entregada;
    /** Firebase ya no conoce ese token (app desinstalada o token renovado): se borra. */
    case Vencida;
    case Fallida;
}
