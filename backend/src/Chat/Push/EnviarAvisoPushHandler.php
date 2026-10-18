<?php

declare(strict_types=1);

namespace App\Chat\Push;

use App\Entity\SuscripcionPush;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Envía la notificación a cada navegador suscrito. El contenido va cifrado
 * para el navegador (el servicio de push no lo lee). Las suscripciones que
 * el servicio da por vencidas (404/410) se borran.
 */
#[AsMessageHandler]
final class EnviarAvisoPushHandler
{
    /** Una notificación de chat que no llega en un día ya no sirve. */
    private const TTL = 86400;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ClientInterface $http,
        #[Autowire(env: "default::PUSH_VAPID_PUBLICA")] private readonly ?string $clavePublica,
        #[Autowire(env: "default::PUSH_VAPID_PRIVADA")] private readonly ?string $clavePrivada,
        #[Autowire(env: "default:push_vapid_contacto_defecto:PUSH_VAPID_CONTACTO")] private readonly string $contacto,
    ) {}

    public function __invoke(EnviarAvisoPush $mensaje): void
    {
        if (!$this->clavePublica || !$this->clavePrivada || $mensaje->usuarios === []) {
            return;
        }
        /** @var list<SuscripcionPush> $suscripciones */
        $suscripciones = $this->em->getRepository(SuscripcionPush::class)->findBy(["usuario" => $mensaje->usuarios]);
        if ($suscripciones === []) {
            return;
        }

        $push = new WebPush(
            ["VAPID" => ["subject" => $this->contacto, "publicKey" => $this->clavePublica, "privateKey" => $this->clavePrivada]],
            ["TTL" => self::TTL, "urgency" => "high", "topic" => substr(preg_replace('/[^A-Za-z0-9_-]/', "", $mensaje->contenido["etiqueta"]) ?? "", 0, 32)],
            client: $this->http,
            logger: $this->logger,
        );
        $carga = json_encode($mensaje->contenido, JSON_THROW_ON_ERROR);
        $porEndpoint = [];
        foreach ($suscripciones as $s) {
            $porEndpoint[$s->getEndpoint()] = $s;
            $push->queueNotification(new Subscription($s->getEndpoint(), $s->getClave(), $s->getSecreto(), "aes128gcm"), $carga);
        }

        $vencidas = 0;
        foreach ($push->flush() as $reporte) {
            if ($reporte->isSuccess()) {
                continue;
            }
            if ($reporte->isSubscriptionExpired() && isset($porEndpoint[$reporte->getEndpoint()])) {
                $this->em->remove($porEndpoint[$reporte->getEndpoint()]);
                ++$vencidas;
            } else {
                $this->logger->warning("Notificación push no enviada: {motivo}", ["motivo" => $reporte->getReason()]);
            }
        }
        if ($vencidas > 0) {
            $this->em->flush();
        }
    }
}
