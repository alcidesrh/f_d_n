<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Entity\BoletoVenta;
use App\Entity\Encomienda;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoFacturacion;
use App\Venta\Mensaje\EnviarBoletoPorCorreo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Certifica las facturas que quedaron pendientes: ventas de taquilla en
 * contingencia ("continuar sin factura electrónica"), ventas web cuyo
 * certificador falló después del cobro y encomiendas cobradas en
 * contingencia (al recibirlas o al entregarlas, ADR-035). La corre el
 * programador cada 10 minutos (`Tarea::CERTIFICAR_PENDIENTES`, ADR-044) y,
 * a mano, `app:venta:certificar-pendientes`. Una venta web certificada
 * aquí recibe su boleto por correo.
 */
final class CertificacionPendientes
{
    public const LIMITE = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Facturador $facturador,
        private readonly MessageBusInterface $bus,
    ) {}

    /**
     * @return array{intentos: int, certificadas: int, errores: list<string>}
     */
    public function certificar(int $limite = self::LIMITE): array
    {
        /** @var list<BoletoVenta> $ventas */
        $ventas = $this->em->getRepository(BoletoVenta::class)->findBy(
            ["estado" => EstadoBoletoVenta::CONFIRMADA, "estadoFacturacion" => EstadoFacturacion::PENDIENTE],
            ["id" => "ASC"],
            $limite,
        );

        $ok = 0;
        $errores = [];
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
                $errores[] = sprintf("Venta %d: %s", $venta->getId(), $e->getMessage());
            }
        }

        /** @var list<Encomienda> $encomiendas */
        $encomiendas = $this->em->getRepository(Encomienda::class)->findBy(
            ["estadoFacturacion" => EstadoFacturacion::PENDIENTE],
            ["id" => "ASC"],
            $limite,
        );
        $intentos = count($ventas);
        foreach ($encomiendas as $encomienda) {
            // Solo las que se cobraron (en contingencia): las demás aún se están registrando.
            if ($encomienda->getNumeroAcceso() === null) {
                continue;
            }
            $intentos++;
            try {
                $this->facturador->certificarEncomienda($encomienda);
                $this->em->flush();
                $ok++;
            } catch (CertificacionFallida $e) {
                $encomienda->setErrorFacturacion($e->getMessage());
                $this->em->flush();
                $errores[] = sprintf("Encomienda %s: %s", $encomienda->getCodigo(), $e->getMessage());
            }
        }

        return ["intentos" => $intentos, "certificadas" => $ok, "errores" => array_slice($errores, 0, 20)];
    }
}
