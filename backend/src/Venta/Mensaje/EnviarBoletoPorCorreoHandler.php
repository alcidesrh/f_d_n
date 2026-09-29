<?php

declare(strict_types=1);

namespace App\Venta\Mensaje;

use App\Entity\BoletoVenta;
use App\Venta\Boleto\BoletoPdf;
use App\Venta\Boleto\DatosBoleto;
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

        $datos = DatosBoleto::de($venta);
        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->remitente)
                ->to($correo)
                ->subject(sprintf("Su boleto %s – %s a %s", $datos["codigoBarras"], $datos["origen"]["nombre"] ?? "", $datos["destino"]["nombre"] ?? ""))
                ->htmlTemplate("venta/boleto_correo.html.twig")
                ->context(["b" => $datos])
                ->attach($this->pdf->generar($venta), BoletoPdf::nombreArchivo($venta), "application/pdf"),
        );
    }
}
