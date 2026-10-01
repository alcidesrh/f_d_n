<?php

declare(strict_types=1);

namespace App\Venta\Mensaje;

use App\Entity\BoletoVenta;
use App\Venta\Boleto\BoletoPdf;
use App\Venta\Boleto\Comprobantes;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EnviarBoletoPorCorreoHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BoletoPdf $pdf,
        private readonly Comprobantes $comprobantes,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: "default:venta_correo_remitente_defecto:VENTA_CORREO_REMITENTE")]
        private readonly string $remitente,
    ) {}

    public function __invoke(EnviarBoletoPorCorreo $mensaje): void
    {
        $venta = $this->em->find(BoletoVenta::class, $mensaje->ventaId);
        $correo = $venta?->getCliente()?->getEmail();
        if ($venta === null || !$correo) {
            $this->logger->info("Boleto {id} sin venta o sin correo: no se envía.", ["id" => $mensaje->ventaId]);

            return;
        }

        $ventas = [$venta, ...array_values(array_filter(array_map(fn(int $id) => $this->em->find(BoletoVenta::class, $id), $mensaje->otrasVentas)))];
        $datos = $this->comprobantes->de($venta);
        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->remitente)
                ->to($correo)
                ->subject(sprintf(
                    "Su boleto %s – %s a %s%s",
                    $datos["codigoBarras"],
                    $datos["origen"]["nombre"] ?? "",
                    $datos["destino"]["nombre"] ?? "",
                    count($ventas) > 1 ? " (ida y vuelta)" : "",
                ))
                ->htmlTemplate("venta/boleto_correo.html.twig")
                ->context(["b" => $datos, "otros" => array_map(fn(BoletoVenta $v) => $this->comprobantes->de($v), array_slice($ventas, 1))])
                ->attach($this->pdf->generar(...$ventas), BoletoPdf::nombreArchivo($venta), "application/pdf"),
        );
    }
}
