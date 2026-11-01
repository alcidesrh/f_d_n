<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Venta\Facturacion\Forcon\AjustesForcon;

/**
 * Regla temporal: las compras en la página de algunas empresas se facturan a
 * nombre de otra (hoy, La Pionera y Maya de Oro como Transportes Rosita), como
 * hace el legado con el emisor de su estación web. Cuando cada empresa tenga
 * su propia cuenta, se vacían los emisores en línea de `ConfiguracionForcon`
 * (`app:fel:configuracion --sin-emisores-en-linea`).
 */
final class EmisorEnLinea
{
    public function __construct(
        private readonly AjustesForcon $ajustes = new AjustesForcon(),
    ) {}

    /** NIT (normalizado) de quien factura las ventas en línea de esa empresa, o null si las factura ella misma. */
    public function para(?string $nitEmpresa): ?string
    {
        if ($nitEmpresa === null) {
            return null;
        }
        $buscado = self::normalizar($nitEmpresa);
        foreach ($this->ajustes->actual()->getEmisoresEnLinea() as $empresa => $emisor) {
            if (self::normalizar((string) $empresa) === $buscado) {
                return self::normalizar((string) $emisor);
            }
        }

        return null;
    }

    public static function normalizar(string $nit): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $nit) ?? "");
    }
}
