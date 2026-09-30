<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

class ServerSentEvent {
    public function __construct(private LockFactory $lockFactory, private HubInterface $hubInterface, private RequestStack $requestStack, private Environment $environment) {
    }

    public function errorPago($mensaje = 'Ha ocurrido un error. No se ha realizado el pago.', $detalle = null, $conservarPagoEnCurso = false) {

        // $lock = $this->lockFactory->createLock($this->requestStack->getSession()->getId());
        // $lock->release();

        $this->requestStack->getSession()->set('error_transferencia', true);

        // Un error concluye el flujo de pago en línea (salvo el guard anti doble pago,
        // que conserva la marca para seguir bloqueando reintentos mientras el pago siga vivo).
        if (!$conservarPagoEnCurso) {
            $this->requestStack->getSession()->remove('pago_en_curso');
        }

        $detalle = match (true) {
            \is_array($detalle) && isset($detalle['error']) => $detalle['error'],
            \is_array($detalle) && (isset($detalle['status']) || isset($detalle['errorInformation'])) => isset($detalle['errorInformation'])
                ? (isset($detalle['status']) ? '<br/>Status: ' . $detalle['status'] : ' ') . '<br/>Motivo: ' . $detalle['errorInformation']['reason'] . '<br/> Mensaje: ' . $detalle['errorInformation']['message']
                : '<br/>Status: ' . $detalle['status'],
            \is_object($detalle) && method_exists($detalle, 'getMessage') => $detalle->getMessage(),
            400 == $detalle => 'Por favor revise y vuelva a intentarlo',
            default => $detalle,
        };
        try {
            $this->hubInterface->publish(new Update(
                'error_pago_' . $this->requestStack->getSession()->getId(),
                $this->environment->render('reservacion/_error_pago.stream.html.twig', ['error' => $mensaje, 'detalle' => $detalle])
            ));
        } catch (\Throwable $th) {
            // Un hub inalcanzable o que rechaza (p.ej. MERCURE_URL=http:// detrás de
            // un redirect de Cloudflare -> el hub devuelve 400) NO debe convertir el
            // 403 del pago en un 500: el usuario pierde el banner, pero el flujo sigue
            // y el header error-pago llega igual a Turbo (KA-09648).
            error_log(\sprintf('[Mercure] errorPago falló al publicar: %s', $th->getMessage()));
        }

        return new Response(null, Response::HTTP_FORBIDDEN, [
            'error-pago' => true,
        ]);
    }

    public function errorGenerico($mensaje = 'Ha ocurrido un error. No se ha realizado el pago.', $detalle = null) {

        try {
        $this->hubInterface->publish(new Update(
            'error_generico',
            $this->environment->render('reservacion/_error_generico.stream.html.twig', ['error' => $mensaje, 'detalle' => $detalle])
        ));
    } catch (\Throwable $th) {
        // Ídem errorPago: el fallo del hub no puede convertir el 403 en 500.
        error_log(\sprintf('[Mercure] errorGenerico falló al publicar: %s', $th->getMessage()));
    }

        return new Response(null, Response::HTTP_FORBIDDEN, [
            'error-pago' => true,
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
        } catch (\Throwable $th) {
            error_log(\sprintf(
                '[Mercure] publish falló (topic=%s): %s',
                \is_array($topic) ? \implode('_', $topic) : $topic,
                $th->getMessage()
            ));
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
