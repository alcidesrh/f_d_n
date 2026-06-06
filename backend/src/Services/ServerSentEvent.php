<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class ServerSentEvent implements ServiceSubscriberInterface {
    use ServiceMethodsSubscriberTrait;
    public EncoderInterface $encoderInterface {
        get {
            return $this->getSerializer();
        }
    }
    public static function getSubscribedServices(): array {
        return [
            'serializer' => SerializerInterface::class,
        ];
    }

    private function getSerializer(): SerializerInterface {
        return $this->container->get('serializer');
    }

    public function __construct(private HubInterface $hubInterface, private RequestStack $requestStack,  private string $sessionID = "") {
    }

    public function errorPago($mensaje = 'Ha ocurrido un error. No se ha realizado el pago.', $detalle = null) {

        // $lock = $this->lockFactory->createLock($this->requestStack->getSession()->getId());
        // $lock->release();

        $this->requestStack->getSession()->set('error_transferencia', true);

        $detalle = match (true) {
            \is_array($detalle) && isset($detalle['error']) => $detalle['error'],
            \is_array($detalle) && (isset($detalle['status']) || isset($detalle['errorInformation'])) => isset($detalle['errorInformation'])
                ? (isset($detalle['status']) ? '<br/>Status: ' . $detalle['status'] : ' ') . '<br/>Motivo: ' . $detalle['errorInformation']['reason'] . '<br/> Mensaje: ' . $detalle['errorInformation']['message']
                : '<br/>Status: ' . $detalle['status'],
            \is_object($detalle) && method_exists($detalle, 'getMessage') => $detalle->getMessage(),
            400 == $detalle => 'Por favor revise y vuelva a intentarlo. De no haber error en los datos puede llamar al +502 49319397 y le atenderemos.',
            default => $detalle,
        };
        $this->hubInterface->publish(new Update(
            'error_pago_' . $this->requestStack->getSession()->getId(),
            $this->environment->render('reservacion/_error_pago.stream.html.twig', ['error' => $mensaje, 'detalle' => $detalle])
        ));

        return new Response(null, Response::HTTP_FORBIDDEN, [
            'error-pago' => true,
        ]);
    }

    public function error($mensaje = 'MSG ServerSent: Ha ocurrido un error.', $detalle = null) {
        $temp = 'error';
        if ($tabid = $this->requestStack->getCurrentRequest()->headers->get('Tabid', '')) {
            // $temp .= '_tab' . $tabid;
        }
        $this->hubInterface->publish(
            new Update(
                $this->sessionID . $temp,
                $this->encoderInterface->encode(is_array($mensaje) ? $mensaje : ['mensaje' => $mensaje], 'json')
            )
        );

        return new Response(null, Response::HTTP_FORBIDDEN, [
            'error-pago' => true,
        ]);
    }

    public function form($form) {

        $this->hubInterface->publish(
            new Update(
                $this->sessionID . 'form',
                $this->encoderInterface->encode($form, 'json')
            )
        );

        return new Response(null, Response::HTTP_FORBIDDEN, [
            'error-form' => true,
        ]);
    }

    public function publish(string|array $topic, array|string $param, ?string $view = null) {

        try {
            $this->hubInterface->publish(new Update(
                is_array($topic) ? $topic[0] . '_' . $topic[1]
                    : $topic . '_' . $this->requestStack->getSession()->getId(),
                $view ? $this->environment->render($view, $param)
                    : (is_array($param) ? json_encode($param) : $param)
            ));
        } catch (\Exception $th) {
            return $th->getMessage();
        }

        return false;
    }

    public function procesandoPago(...$arguments) {
        $this->stream(...$arguments);

        return new Response(null, Response::HTTP_NO_CONTENT, [
            'procesando-pago' => true,
        ]);
    }

    public function stream(...$arguments) {
        return $this->publish(...$arguments);
    }
}
