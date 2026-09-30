<?php

declare(strict_types=1);

namespace App\Venta\Facturacion\Forcon;

use App\Venta\Facturacion\ConsultaContribuyente;

/** Servicio "Consulta de NIT de Contribuyentes" de Forcon. */
final class ConsultaContribuyenteForcon implements ConsultaContribuyente
{
    public function __construct(
        private readonly ClienteForcon $cliente,
    ) {}

    public function nombreDeNit(string $nit): ?string
    {
        $r = $this->cliente->get(ClienteForcon::CONSULTA_NIT, ["NIT" => $nit], null);

        return ($r["Resultado"] ?? false) === true && !empty($r["RazonSocial"])
            ? trim((string) $r["RazonSocial"])
            : null;
    }
}
