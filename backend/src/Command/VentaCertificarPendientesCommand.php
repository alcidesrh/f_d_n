<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BoletoVenta;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoFacturacion;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\Facturador;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Certifica las facturas que quedaron pendientes: ventas de taquilla en
 * contingencia ("continuar sin factura electrónica") y ventas web cuyo
 * certificador falló después del cobro. Pensado para cron.
 */
#[AsCommand(name: "app:venta:certificar-pendientes", description: "Reintenta certificar las facturas pendientes")]
final class VentaCertificarPendientesCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Facturador $facturador,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "Máximo de ventas por corrida")] int $limite = 100,
    ): int {
        /** @var list<BoletoVenta> $ventas */
        $ventas = $this->em->getRepository(BoletoVenta::class)->findBy(
            ["estado" => EstadoBoletoVenta::CONFIRMADA, "estadoFacturacion" => EstadoFacturacion::PENDIENTE],
            ["id" => "ASC"],
            $limite,
        );

        $ok = 0;
        foreach ($ventas as $venta) {
            try {
                $this->facturador->certificar($venta);
                $this->em->flush();
                $ok++;
                if ($venta->getCanal() === CanalVenta::WEB || $venta->isEnviarCorreo()) {
                    $this->bus->dispatch(new EnviarBoletoPorCorreo((int) $venta->getId()));
                }
            } catch (CertificacionFallida $e) {
                $venta->setErrorFacturacion($e->getMessage());
                $this->em->flush();
                $io->warning(sprintf("Venta %d: %s", $venta->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf("%d de %d facturas certificadas.", $ok, count($ventas)));

        return 0;
    }
}
