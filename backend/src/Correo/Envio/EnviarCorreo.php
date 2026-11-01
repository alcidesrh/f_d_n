<?php

declare(strict_types=1);

namespace App\Correo\Envio;

/** Entregar un correo enviado a sus destinatarios de fuera, por el proveedor SMTP. Asíncrono. */
final readonly class EnviarCorreo
{
    public function __construct(public int $mensajeId) {}
}
