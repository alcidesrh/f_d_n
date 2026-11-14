<?php

declare(strict_types=1);

namespace App\Venta\Mensaje;

use App\Correo\Configuracion\AjustesCorreo;
use App\Entity\BoletoVenta;
use App\Entity\Enum\CanalVenta;
use App\Entity\PagoWeb;
use App\Venta\EnLinea\Modificacion\LlaveCliente;
use App\Venta\Boleto\BoletoPdf;
use App\Venta\Boleto\Comprobantes;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class EnviarBoletoPorCorreoHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BoletoPdf $pdf,
        private readonly Comprobantes $comprobantes,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $reloj,
        private readonly AjustesCorreo $ajustesCorreo,
        private readonly LlaveCliente $llaves,
        private readonly UrlGeneratorInterface $urls,
    ) {}

    public function __invoke(EnviarBoletoPorCorreo $mensaje): void
    {
        $venta = $this->em->find(BoletoVenta::class, $mensaje->ventaId);
        $correo = $mensaje->correo ?? $venta?->getCliente()?->getEmail();
        if ($venta === null || !$correo) {
            $this->logger->info("Boleto {id} sin venta o sin correo: no se envía.", ["id" => $mensaje->ventaId]);

            return;
        }

        $ventas = [$venta, ...array_values(array_filter(array_map(fn(int $id) => $this->em->find(BoletoVenta::class, $id), $mensaje->otrasVentas)))];
        $datos = $this->comprobantes->de($venta);
        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->ajustesCorreo->actual()->getRemitenteBoletos())
                ->to($correo)
                ->subject(sprintf(
                    "Su boleto %s – %s a %s%s",
                    $datos["codigoBarras"],
                    $datos["origen"]["nombre"] ?? "",
                    $datos["destino"]["nombre"] ?? "",
                    count($ventas) > 1 ? " (ida y vuelta)" : "",
                ))
                ->htmlTemplate("venta/boleto_correo.html.twig")
                ->context([
                    "b" => $datos,
                    "otros" => array_map(fn(BoletoVenta $v) => $this->comprobantes->de($v), array_slice($ventas, 1)),
                    "misCompras" => $this->misCompras($venta, $correo),
                ])
                ->attach($this->pdf->generar(...$ventas), BoletoPdf::nombreArchivo($venta), "application/pdf"),
        );

        $ahora = $this->reloj->now();
        foreach ($ventas as $v) {
            $v->marcarCorreoEnviado($ahora);
        }
        $this->em->flush();
    }

    /**
     * Enlace a «Mis compras» con la llave del comprador, solo si el correo
     * va al comprador de una compra en línea (un reenvío a otra dirección no
     * le da acceso a las compras de él).
     */
    private function misCompras(BoletoVenta $venta, string $correo): ?string
    {
        if ($venta->getCanal() !== CanalVenta::WEB) {
            return null;
        }
        $correo = LlaveCliente::normalizar($correo);
        $pago = $this->em->createQueryBuilder()
            ->select("p")
            ->from(PagoWeb::class, "p")
            ->where("p.boletoVenta = :venta OR p.boletoVentaRegreso = :venta")
            ->setParameter("venta", $venta)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$pago instanceof PagoWeb || $pago->getCorreo() !== $correo) {
            return null;
        }

        return $this->urls->generate("pagina", ["ruta" => "es/mis-compras", "llave" => $this->llaves->emitir($correo)], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
