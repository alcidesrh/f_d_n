<?php

declare(strict_types=1);

namespace App\Correo\Envio;

use App\Correo\Configuracion\AjustesCorreo;
use App\Correo\Direcciones;
use App\Entity\ConfiguracionCorreo;
use App\Venta\CifradoCredenciales;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Por donde sale todo correo del sistema (decora `mailer.transports`: el
 * correo de la empresa, los boletos, los reportes, el contacto de la página).
 *
 * Con MailPace encendido en `ConfiguracionCorreo`, cada correo sale por su
 * SMTP con el token del dominio del remitente (MailPace da uno por dominio
 * verificado). Apagado, sigue por `MAILER_DSN` como siempre (en desarrollo,
 * `null://`). La configuración se lee en cada envío; la conexión se guarda
 * mientras no cambie.
 */
#[AsDecorator("mailer.transports")]
final class TransporteCorreo implements TransportInterface
{
    /** Contexto de `CifradoCredenciales` para los tokens. */
    public const CONTEXTO = "fdn-credencial-correo";

    /** @var array<string, EsmtpTransport> por servidor, puerto y token */
    private array $conexiones = [];

    public function __construct(
        #[AutowireDecorated]
        private readonly TransportInterface $porDefecto,
        private readonly AjustesCorreo $ajustes,
        private readonly CifradoCredenciales $cifrado,
        private readonly ?EventDispatcherInterface $eventos = null,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $config = $this->ajustes->actual();
        if (!$config->isActivo()) {
            return $this->porDefecto->send($message, $envelope);
        }
        $envelope ??= Envelope::create($message);

        return $this->mailpace($config, $envelope->getSender()->getAddress())->send($message, $envelope);
    }

    /**
     * El SMTP de MailPace con el token del dominio de `$remitente`, aunque
     * MailPace esté apagado (sirve para probar antes de encenderlo).
     *
     * @throws TransportException si ese dominio no tiene token o no se puede leer
     */
    public function mailpace(ConfiguracionCorreo $config, string $remitente): TransportInterface
    {
        $dominio = Direcciones::partes($remitente)[1];
        $cifrado = $config->getTokens()[$dominio] ?? null;
        if ($cifrado === null) {
            throw new TransportException(sprintf("No hay token de MailPace para %s: cárguelo en la configuración del correo.", $dominio));
        }
        $token = $this->cifrado->descifrar(self::CONTEXTO, $cifrado)
            ?? throw new TransportException(sprintf("No se pudo leer el token de MailPace de %s (¿cambió APP_SECRET?): vuelva a cargarlo.", $dominio));

        $clave = hash("sha256", $config->getServidor() . "|" . $config->getPuerto() . "|" . $token);
        if (!isset($this->conexiones[$clave])) {
            // 465: TLS implícito; otro puerto (587, 25, 2525): STARTTLS si el servidor lo ofrece.
            $smtp = new EsmtpTransport($config->getServidor(), $config->getPuerto(), $config->getPuerto() === 465, $this->eventos, $this->logger);
            $smtp->setUsername($token);
            $smtp->setPassword($token);
            $this->conexiones[$clave] = $smtp;
        }

        return $this->conexiones[$clave];
    }

    public function __toString(): string
    {
        return "fdn://correo";
    }
}
