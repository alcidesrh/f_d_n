<?php

declare(strict_types=1);

namespace App\Venta;

use App\Venta\Excepcion\VentaRechazada;

/** Datos del comprador en la página web (se convierten en `Cliente`). */
final readonly class Comprador
{
    public function __construct(
        public string $nombre,
        public ?string $apellido,
        public string $email,
        public ?string $telefono,
        public string $nit,
        public ?int $tipoDocumentoId = null,
        public ?string $numeroDocumento = null,
        public ?int $nacionalidadId = null,
    ) {}

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArray(array $datos): self
    {
        $texto = static fn(string $campo, int $max) => ($v = trim((string) ($datos[$campo] ?? ""))) !== "" ? mb_substr($v, 0, $max) : null;
        $id = static fn(string $campo) => is_numeric($datos[$campo] ?? null) && (int) $datos[$campo] > 0 ? (int) $datos[$campo] : null;

        $nombre = $texto("nombre", 255);
        if ($nombre === null) {
            throw new VentaRechazada("Indique su nombre.", "comprador_nombre");
        }
        $email = $texto("email", 50);
        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new VentaRechazada("Indique un correo electrónico válido: ahí recibirá su boleto.", "comprador_email");
        }
        $nit = Facturacion\Facturador::normalizarNit($texto("nit", 20));
        if ($nit !== "CF" && !self::nitValido($nit)) {
            throw new VentaRechazada("El NIT no es válido. Si no tiene, use CF.", "comprador_nit");
        }

        return new self(
            nombre: $nombre,
            apellido: $texto("apellido", 50),
            email: $email,
            telefono: $texto("telefono", 15),
            nit: $nit,
            tipoDocumentoId: $id("tipoDocumento"),
            numeroDocumento: $texto("numeroDocumento", 40),
            nacionalidadId: $id("nacionalidad"),
        );
    }

    /**
     * NIT guatemalteco: dígitos + dígito verificador (0–9 o K) por módulo 11.
     */
    public static function nitValido(string $nit): bool
    {
        $nit = strtoupper(preg_replace('/[\s-]+/', '', $nit) ?? "");
        if (!preg_match('/^(\d+)([\dK])$/', $nit, $m)) {
            return false;
        }
        $cuerpo = strrev($m[1]);
        $suma = 0;
        for ($i = 0, $n = strlen($cuerpo); $i < $n; $i++) {
            $suma += (int) $cuerpo[$i] * ($i + 2);
        }
        $verificador = (11 - ($suma % 11)) % 11;

        return $m[2] === ($verificador === 10 ? "K" : (string) $verificador);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
