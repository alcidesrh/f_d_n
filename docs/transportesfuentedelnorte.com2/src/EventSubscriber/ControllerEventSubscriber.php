<?php

// src/EventSubscriber/TokenSubscriber.php
namespace App\EventSubscriber;

use App\Entity\Reservacion;
use App\Services\ReservacionService;
use App\Services\ServerSentEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

class ControllerEventSubscriber implements EventSubscriberInterface {


    public function __construct(
        #[Autowire('%reservacion_minutos%')] private $reservacion_minutos,
        #[Autowire('%credenciales%')] private $credenciales,
        #[Autowire('%org_id%')] private $org_id,
        #[Autowire(service: 'event_dispatcher')] private $eventDispatcherInterface,
        #[Autowire(service: 'router')] private $router,
        private EntityManagerInterface $entityManagerInterface,
        private Reservacion $reservacion,
        private ReservacionService $reservacionService,
        private ServerSentEvent $serverSentEvent
    ) {
    }

    public function onKernelController(ControllerEvent $event) {

        if (!$event->isMainRequest()) {
            return;
        }
        $controller = $event->getController();

        // when a controller class defines multiple action methods, the controller
        // is returned as [$controllerInstance, 'methodName']
        if (is_array($controller)) {
            $controller = $controller[0];
        }

        if ($controller instanceof ControllerEventInterface) {

            $request = $event->getRequest();

            $excluir = ['transferencia', 'payer_authentication_check_enrollment', 'payer_authentication_validation_service'];

            if ($request && !\in_array($request->attributes->get('_route'), $excluir) &&  ($session = $request->getSession()) && $this->reservacion?->getId()) {

                if ($this->reservacion_minutos < $this->reservacion->getMinutosEditadaCreada()) {

                    $session->getFlashBag()->add(
                        'session_terminada',
                        'session_terminada_mensaje'
                    );
                    $session->getFlashBag()->add(
                        'RESERVACION_MINUTOS',
                        $this->reservacion_minutos
                    );

                    $this->reservacionService->reiniciar();

                    $response = new Response(null, 200, [
                        'Turbo-Location' => $this->router->generate('inicio'),
                        'session-terminada' => true,
                    ]);
                    $event->stopPropagation();
                    $event->setController(function () use ($response) {
                        return $response;
                    });
                } else if ($this->reservacion) {

                    $paso_completado_actual = $this->reservacion->getPasoCompletado();

                    if (
                        $paso_completado_actual != ($paso_completado =
                            match ($request->attributes->get('_route')) {
                                'ruta' => 0,
                                'salida', 'salida_form' => 1,
                                'asientos' => 2,
                                'pagar' => 3,
                                'confirmacion' => 4,
                                default => $paso_completado_actual
                            })
                    ) {
                        $this->reservacion->setPasoCompletado($paso_completado)->setLocale($session->get('_locale'));
                        $this->entityManagerInterface->flush();
                        if ($paso_completado == 2 && $paso_completado_actual < 2 && $this->reservacion->getEmpresaFactura()) {

                            $merchant_id = $this->credenciales[$this->reservacion->getEmpresaFactura()->getSlug()]['CYBERSOURCE_MERCHANT_ID'];

                            if (!$uuid = $request->getSession()->get('uuid')) {
                                $request->getSession()->set('uuid', $uuid = (string) Uuid::v1());
                            }
                            $this->serverSentEvent->stream(
                                'fingerprint',
                                [
                                    'cybersource_session_id' => $merchant_id . $uuid,
                                    'org_id' => $this->org_id,
                                    'org_id' => $this->org_id,
                                ],
                                'reservacion/_fingerprint.stream.html.twig'
                            );
                        }
                    }
                }
            }


            // throw new AccessDeniedHttpException('This action needs a valid token!');
        }
    }

    public static function getSubscribedEvents() {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
