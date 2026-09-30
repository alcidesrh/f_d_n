<?php

declare(strict_types=1);

namespace App\Venta\Pago\Cybersource;

/** Credenciales de un comercio en Cybersource (en memoria, ya descifradas). */
final readonly class Comercio
{
    public function __construct(
        public string $id,
        public string $llave,
        /** Llave secreta compartida en base64, tal como la entrega el Business Center. */
        public string $secreto,
    ) {}

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ["id" => $this->id, "llave" => $this->llave];
    }
}
