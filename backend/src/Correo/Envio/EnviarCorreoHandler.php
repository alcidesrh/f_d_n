<?php

declare(strict_types=1);

namespace App\Correo\Envio;

use App\Correo\Adjuntos;
use App\Correo\Buzon;
use App\Correo\Dominios;
use App\Entity\CorreoMensaje;
use App\Entity\Enum\EnvioCorreo;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Entrega por el proveedor (`TransporteCorreo`: MailPace o `MAILER_DSN`) a los destinatarios de
 * fuera. Los de la casa ya lo recibieron en su buzón al enviarse: el sobre
 * SMTP lleva solo a los externos, aunque las cabeceras muestran a todos.
 * Si el proveedor lo rechaza, el enviado queda "fallido" con el motivo y se
 * puede reintentar; no reintenta solo (no duplicar un correo a medias).
 */
#[AsMessageHandler]
final class EnviarCorreoHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TransporteCorreo $transporte,
        private readonly Adjuntos $adjuntos,
        private readonly Dominios $dominios,
        private readonly Buzon $buzon,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(EnviarCorreo $mensaje): void
    {
        $m = $this->em->find(CorreoMensaje::class, $mensaje->mensajeId);
        if ($m === null || $m->getEnvio() !== EnvioCorreo::Pendiente) {
            return;
        }
        $externos = array_values(array_filter(
            [...$m->getPara(), ...$m->getCc(), ...$m->getCco()],
            fn(array $d) => !$this->dominios->esDeLaCasa($d["direccion"]),
        ));
        if ($externos === []) {
            $m->entregado();
            $this->em->flush();

            return;
        }

        try {
            $email = $this->correo($m);
            $this->transporte->send($email, new Envelope(new Address($m->getDeDireccion()), array_map(static fn(array $d) => new Address($d["direccion"]), $externos)));
            $m->entregado();
        } catch (\Throwable $e) {
            $this->logger->error("No se pudo enviar el correo {id}: {error}", ["id" => $m->getId(), "error" => $e->getMessage()]);
            $m->fallo($e->getMessage());
        }
        $this->em->flush();
        $this->buzon->avisar($m->getCuenta(), null, ["envio" => $m->getId()]);
    }

    private function correo(CorreoMensaje $m): Email
    {
        $direcciones = static fn(array $lista) => array_map(static fn(array $d) => new Address($d["direccion"], (string) ($d["nombre"] ?? "")), $lista);
        $email = (new Email())
            ->from(new Address($m->getDeDireccion(), (string) ($m->getDeNombre() ?? "")))
            ->subject($m->getAsunto())
            ->text($m->getTexto())
            ->date($m->getFecha());
        if ($m->getHtml() !== null) {
            $email->html($m->getHtml());
        }
        if ($m->getPara() !== []) {
            $email->to(...$direcciones($m->getPara()));
        }
        if ($m->getCc() !== []) {
            $email->cc(...$direcciones($m->getCc()));
        }
        if ($m->getResponderA() !== null) {
            $email->replyTo($m->getResponderA());
        }
        $cabeceras = $email->getHeaders();
        if ($m->getMessageId() !== null) {
            $cabeceras->addIdHeader("Message-ID", $m->getMessageId());
        }
        if ($m->getEnRespuestaA() !== null) {
            $cabeceras->addIdHeader("In-Reply-To", $m->getEnRespuestaA());
        }
        if ($m->getReferencias() !== null) {
            $cabeceras->addIdHeader("References", explode(" ", $m->getReferencias()));
        }
        foreach ($m->getAdjuntos() as $a) {
            $email->attach($this->adjuntos->contenido($a), $a->getNombre(), $a->getTipo());
        }

        return $email;
    }
}
