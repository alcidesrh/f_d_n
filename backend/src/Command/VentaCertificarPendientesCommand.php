<?php

declare(strict_types=1);

namespace App\Command;

use App\Venta\Facturacion\CertificacionPendientes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Una pasada de la certificación de facturas pendientes
 * (`CertificacionPendientes`). El programador de tareas la corre cada 10
 * minutos (ADR-044): esto es para hacerlo a mano.
 */
#[AsCommand(name: "app:venta:certificar-pendientes", description: "Reintenta certificar las facturas pendientes")]
final class VentaCertificarPendientesCommand
{
    public function __construct(private readonly CertificacionPendientes $certificacion) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "Máximo de ventas por corrida")] int $limite = CertificacionPendientes::LIMITE,
    ): int {
        $r = $this->certificacion->certificar($limite);
        foreach ($r["errores"] as $error) {
            $io->warning($error);
        }
        $io->success(sprintf("%d de %d facturas certificadas.", $r["certificadas"], $r["intentos"]));

        return 0;
    }
}
