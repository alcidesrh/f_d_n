<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Regla temporal: las compras en la página de algunas empresas se facturan a
 * nombre de otra (hoy, La Pionera y Maya de Oro como Transportes Rosita), como
 * hace el legado con el emisor de su estación web. Cuando cada empresa tenga
 * su propia cuenta, se vacía `fel_emisor_en_linea` (`config/services.yaml`).
 */
final class EmisorEnLinea
{
    /** @var array<string, string> NIT normalizado de la empresa => NIT normalizado del emisor */
    private readonly array $emisores;

    /** @param array<string, string> $emisores NIT de la empresa => NIT de quien factura sus ventas en línea */
    public function __construct(
        #[Autowire(param: "fel_emisor_en_linea")]
        array $emisores = [],
    ) {
        $normalizados = [];
        foreach ($emisores as $empresa => $emisor) {
            $normalizados[self::normalizar((string) $empresa)] = self::normalizar((string) $emisor);
        }
        $this->emisores = $normalizados;
    }

    /** NIT (normalizado) de quien factura las ventas en línea de esa empresa, o null si las factura ella misma. */
    public function para(?string $nitEmpresa): ?string
    {
        return $nitEmpresa === null ? null : $this->emisores[self::normalizar($nitEmpresa)] ?? null;
    }

    public static function normalizar(string $nit): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $nit) ?? "");
    }
}
