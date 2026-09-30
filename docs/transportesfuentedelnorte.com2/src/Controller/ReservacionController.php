<?php

namespace App\Controller;

use App\Entity\Asiento;
use App\Entity\ClienteReservacion;
use App\Entity\Configuracion;
use App\Entity\Countries;
use App\Entity\Empresa;
use App\Entity\ErrorFdn;
use App\Entity\Reservacion;
use App\Entity\RutaReservacion;
use App\Entity\SalidaReservacion;
use App\Entity\States;
use App\Entity\Tarjeta;
use App\EventSubscriber\ControllerEventInterface;
use App\Form\RutaReservacionType;
use App\Form\SalidaReservacionType;
use App\Form\Type\CiudadAutocompleteType;
use App\Form\Type\PagoDatosType;
use App\Form\Type\PaisAutocompleteType;
use App\Form\Type\ProvinciaAutocompleteType;
use App\Services\CybersourceApi;
use App\Services\Factories\ReservacionFactory;
use App\Services\Lock\LockReservacion;
use App\Services\Mails;
use App\Services\RemoteDatabaseQueries;
use App\Services\ReservacionService;
use App\Services\SatRestService;
use App\Services\ServerSentEvent;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReservacionController extends AbstractController implements ControllerEventInterface {
    private ?Reservacion $reservacion = null;
    public function __construct(
        private ReservacionService $reservacionService,
        private ReservacionFactory $reservacionFactory,
        #[Autowire('%credenciales%')] private array $credenciales = [],
    ) {
        $this->reservacion = $reservacionFactory();
    }

    #[Route('/ruta-form', name: 'ruta')]
    public function ruta(Request $request, EntityManagerInterface $entityManagerInterface, $primer_render = null): Response {

        if (!$ruta = $this->reservacion?->getRuta()) {
            $ruta = new RutaReservacion();
            $this->reservacion->setRuta($ruta)->setUri($request->getClientIp());
        }


        $form = $this->createForm(RutaReservacionType::class, $ruta, [
            'action' => $this->generateUrl('ruta', ['reservacion' => $this->reservacion?->getId()]),
            'reservacion' => $this->reservacion,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->reservacion->setIdaVuelta($form->get('ida_vuelta')->getData());
            if ($salida = $this->reservacion->getSalida()) {
                $entityManagerInterface->remove($salida);
            }
            if ($regreso = $this->reservacion->getRegreso()) {
                $entityManagerInterface->remove($regreso);
            }

            if (!$this->reservacion->getId()) {
                $entityManagerInterface->persist($this->reservacion);
            }
            $entityManagerInterface->flush();
            $entityManagerInterface->refresh($this->reservacion);

            if ($session = $request->getSession()) {
                $this->reservacionService->limpiarSesion();
                $session->set('reservacion', $this->reservacion->getId());
                $request->getSession()->getMetadataBag()->stampNew();
            }

            return $this->forward('App\Controller\ReservacionController::salida');
        }

        return $this->render('reservacion/ruta.html.twig', [
            'form' => $form,
            'primer_render' => $primer_render,
        ]);
    }

    #[Route('/salida', name: 'salida')]
    public function salida(Request $request, EntityManagerInterface $entityManagerInterface,  TranslatorInterface $translatorInterface, $primer_render = null): Response {
        $errors = [];
        if ($request->query->has('siguiente')) {
            if (!$this->reservacion->getSalida() || !$this->reservacion->getSalida()->getSalidaId()) {
                $errors[] = $translatorInterface->trans('Debe definir la salida');
            } else if ($salidas = $request->getSession()->get('salidas')) {
                $salida_id = $this->reservacion->getSalida()->getSalidaId();

                $empresa_id = \array_map(
                    fn($item) => $item['empresa_id'],
                    array_values(\array_filter($salidas, fn($item) => $salida_id == $item['salida_id']))
                );

                $empresa = $entityManagerInterface->getRepository(Empresa::class)->findOneBy(['empresa_id' => $empresa_id[0]]);

                $mitocha = $entityManagerInterface->getRepository(Empresa::class)->findOneBy(['slug' => Empresa::mitocha]);

                $rosita = $entityManagerInterface->getRepository(Empresa::class)->findOneBy(['slug' => Empresa::rosita]);

                $this->reservacion->setFacturaConjunta(false)->setEmpresaFactura($empresa != $mitocha ? $rosita : $mitocha)->getSalida()->setEmpresa($empresa);
            }

            if ($this->reservacion->isIdaVuelta()) {
                if ((!$this->reservacion->getRegreso() || !$this->reservacion->getRegreso()->getSalidaId())) {
                    $errors[] = $translatorInterface->trans('Debe definir el regreso');
                } else {
                    $salida_id = $this->reservacion->getRegreso()->getSalidaId();

                    $empresa_id_vuelta = \array_map(
                        fn($item) => $item['empresa_id'],
                        array_values(\array_filter($request->getSession()->get('salidas_vuelta', []), fn($item) => $salida_id == $item['salida_id']))
                    );

                    $empresa_vuelta = $entityManagerInterface->getRepository(Empresa::class)->findOneBy(['empresa_id' => $empresa_id_vuelta[0]]);

                    $this->reservacion->getRegreso()->setEmpresa($empresa_vuelta);

                    if ($mitocha == $empresa && $mitocha == $empresa_vuelta) {
                        $this->reservacion->setEmpresaFactura($mitocha);
                    } else {
                        $this->reservacion->setEmpresaFactura($rosita)->setFacturaConjunta(true);
                    }
                }
            }

            $entityManagerInterface->flush();
            if (empty($errors)) {
                return $this->redirectToRoute('asientos', ['reservacion' => $this->reservacion->getId()]);
            }
        } else if ($request->query->has('reservacion')) {
            $session = $request->getSession();
            $session->remove('salidas');
            $session->remove('salidas_vuelta');
            $salidas = $this->reservacion->getSalidasArray();
            foreach ($salidas as $key => $value) {
                $entityManagerInterface->remove($value);
            }
            $entityManagerInterface->flush();
            $entityManagerInterface->refresh($this->reservacion);
            $errors[] = $translatorInterface->trans('Se han descartado las salidas y los asientos(si había) seleccionados.');
        }
        return $this->render('reservacion/salida.html.twig', [
            'ida_vuelta' => $this->reservacion->isIdaVuelta(),
            'reservacion_id' => $this->reservacion->getId(),
            'errors' => $errors,
            'primer_render' => $primer_render,
        ]);
    }

    #[Route('/salida-form/{ida_vuelta}', name: 'salida_form')]
    public function salidaForm(ServerSentEvent $serverSentEvent, Request $request, EntityManagerInterface $entityManagerInterface, RemoteDatabaseQueries $sistema, TranslatorInterface $translatorInterface, $ida_vuelta = null) {

        try {
            if ($salida_reservacion = $this->reservacion->getSalidaORegreso($ida_vuelta)) {
                $salida_reservacion_actual =  clone $salida_reservacion;
            } else {
                $salida_reservacion = (new SalidaReservacion())->setRegreso($ida_vuelta);
                $entityManagerInterface->persist($salida_reservacion);
                $this->reservacion->setSalidaRegreso($salida_reservacion, $ida_vuelta);
                $entityManagerInterface->flush();
                $entityManagerInterface->refresh($this->reservacion);
            }
        } catch (\Throwable $th) {

            $serverSentEvent->errorGenerico(mensaje: $th->getMessage());

            $error = true;
        }

        $session_salida_regreso = $ida_vuelta ? 'salidas_vuelta' : 'salidas';

        $form = $this->createForm(SalidaReservacionType::class, $salida_reservacion, [
            'action' => $this->generateUrl('salida_form', ['reservacion' => $this->reservacion->getId(), 'ida_vuelta' => $ida_vuelta]),
            'ida_vuelta' => $ida_vuelta,
        ]);

        $session = $request->getSession();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {


            if (isset($salida_reservacion_actual)) {
                if (
                    $salida_reservacion_actual->getSalidaId() !=  $salida_reservacion->getSalidaId()
                    ||
                    $salida_reservacion_actual->getSalidaFecha() !=  $salida_reservacion->getSalidaFecha()
                ) {

                    if ($salida_reservacion_actual->getSalidaFecha() !=  $salida_reservacion->getSalidaFecha()) {
                        $session->remove($session_salida_regreso);
                        $salida_reservacion->setSalidaId(null);
                    }

                    // if ($salida_reservacion_actual->getId()) {
                    //     // $entityManagerInterface->detach($salida_reservacion);
                    //     $session->remove($session_salida_regreso);
                    //     $entityManagerInterface->remove($salida_reservacion_actual);
                    //     $entityManagerInterface->flush();
                    //     die(var_dump([$salida_reservacion_actual->getSalidaFecha(), $salida_reservacion->getSalidaFecha()]));
                    // }
                }
            }

            $entityManagerInterface->flush();
        }

        if ((!$salidas = $session->get($session_salida_regreso))  && !isset($error)) {
            try {
                if ($salidas = $sistema->getSalidas($this->reservacion->getRuta()->getEstacion($ida_vuelta)->getEstacionId(), $this->reservacion->getRuta()->getEstacion(!$ida_vuelta)->getEstacionId(), $salida_reservacion->getSalidaFecha(), $this->getParameter('salida_minutos_antes'))) {

                    $empresasInactivas = \array_map(fn(Empresa $item) => $item->getEmpresaId(), $entityManagerInterface->getRepository(Empresa::class)->findBy(['activa' => false]));
                    if ($salidas = \array_filter($salidas, fn($item) => !in_array($item['empresa_id'], $empresasInactivas))) {
                        if (!isset($salidas['error'])) {


                            $precios = $sistema->getSalidasAsientoPrecio($salidas);

                            if (!empty($precios)) {

                                $confi = $entityManagerInterface->getRepository(Configuracion::class)->findOneBy([]);

                                foreach ($precios as $index => $value) {
                                    if (\is_array($value)) {
                                        foreach ($value['asientos'] as $index2 => $value2) {
                                            if (isset($value2['precio'])) {
                                                $value2['precio'] = ReservacionService::precio_calcular($confi->getCompraPorciento(), $value2['precio']);
                                                $value['asientos'][$index2] = $value2;
                                            }
                                        }
                                        $salidas[$index]['asientos'] = $value['asientos'];
                                    }
                                }
                            }

                            $session->set($session_salida_regreso, $salidas);
                        }
                    }
                }
            } catch (\Throwable $th) {
                $serverSentEvent->errorGenerico(mensaje: $th->getMessage());
            }

            if (is_null($salidas) || (isset($salidas['error']) && isset($salidas['code']) && $salidas['code'] == 10057)) {
                $sistema_conexion_error = $translatorInterface->trans('No se pudo establecer la conexión con el sistema de reserva. Por favor inténtelo más tarde');
                $salidas = [];
            } elseif (isset($salidas['error'])) {
                $sistema_conexion_error = $salidas['error'];
                $salidas = [];
            }
        }

        return $this->render(
            'reservacion/salidaForm.html.twig',
            [
                'form' => $form,
                'ida_vuelta' => $ida_vuelta,
                'salida_reservacion' => $salida_reservacion,
                'salidas' => $salidas ?? null,
                'sistema_conexion_error' => $sistema_conexion_error ?? null,
                'noframe' => $request->get('noframe'),
                'reservacion' => $this->reservacion,
                'fecha' => $this->reservacion->getSalidaORegreso($ida_vuelta)->getSalidaFechaFactura($request->getLocale())
            ]
        );
    }

    #[Route('/asientos', name: 'asientos')]
    public function asientos(Request $request, EntityManagerInterface $entityManagerInterface, TranslatorInterface $translatorInterface, RemoteDatabaseQueries $remoteDatabaseQueries, $primer_render = null): Response {
        $errors = [];

        $transformAsientos = new CallbackTransformer(
            function (Collection|null $asientos = null) {
                if ($asientos) {
                    return json_encode($asientos->map(fn(Asiento $item) => ['id' => $item->getAsientoId(), 'numero' => $item->getNumero()])->toArray());
                }

                return null;
            },
            function ($asientos) {
                return json_decode($asientos);
            }
        );

        $form = $this->createFormBuilder();
        $key = ['asientos_salida', 'asientos_regreso'];

        $salidas = $this->reservacion->getSalidasArray();

        foreach ($salidas as $key => $salida) {
            $form->add($key, TextType::class, [
                'required' => false,
                'data' => $salida?->getAsientos(),
                'data_class' => null,
            ]);
            $form->get($key)->addModelTransformer($transformAsientos);
        }

        $form = $form->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            if ($form->isValid()) {

                $data = $form->getData();

                $array_ids = fn($array) => array_map(fn($i) => $i->id, $array);

                foreach ($salidas as $key => $salida) {

                    if (empty($data[$key])) {

                        $errors[] = $translatorInterface->trans('Debe escoger al menos un asiento') . ' ' .  ($translatorInterface->trans($key == 'salida' ? 'en la salida' : 'en el regreso'));

                        foreach ($salida->getAsientos()->toArray() as $value) {
                            $entityManagerInterface->remove($value);
                        }
                        $entityManagerInterface->flush();
                    } else {

                        $ids = $array_ids($data[$key]);

                        foreach ($salida->getAsientos()->toArray() as $value) {

                            if (!in_array($value->getAsientoId(), $ids)) {
                                $entityManagerInterface->remove($value);
                            } else {
                                unset($data[$key][array_search($value->getAsientoId(), $ids)]);
                            }
                        }
                        foreach ($data[$key] as $value) {

                            $entityManagerInterface->persist((new Asiento())
                                ->setAsientoId($value->id)->setNumero($value->numero)->setSalidaReservacion($salida));
                        }
                        $entityManagerInterface->flush();
                        $entityManagerInterface->refresh($salida);
                    }
                }

                if (empty($errors)) {
                    try {
                        if (!empty($asientos_ocupados = $remoteDatabaseQueries->getAsientosOcupados())) {

                            $errors = [];

                            foreach ($asientos_ocupados as $key => $value) {

                                $errors[$key] = sprintf($translatorInterface->trans('Desafortunadamente han sido ocupados los asientos con números') . ' : %s.', \implode(', ', $value));
                            }
                        } else {
                            $precios = $remoteDatabaseQueries->getAsientosTempPrecios($this->reservacion);


                            if (isset($precios['error']) && $precios['error']) {
                                $errors[] = $precios['error'];
                            } else if (\is_array($precios)) {

                                if ($confi = $entityManagerInterface->getRepository(Configuracion::class)->findOneBy([])) {
                                    $this->reservacion->setCompraPorcientoActual($confi->getCompraPorciento());
                                    $this->reservacion->setDolarCambioActual($confi->getDolarCambio());
                                }

                                $asiento_repo = $entityManagerInterface->getRepository(Asiento::class);
                                $total = 0;

                                $precio = $this->reservacion->getRuta()->getPrecio();

                                foreach ($precios as $value) {

                                    if ($asiento = $asiento_repo->find(['id' => $value['id_pagina']])) {
                                        $precio_calculado = ReservacionService::precio_calcular($confi->getCompraPorciento(), $precio ?? $value['precio']);
                                        $asiento->setPrecio($precio_calculado);
                                        $total += $precio_calculado;
                                    }
                                }
                                $this->reservacion->setPrecioReal($total)->setPrecio($total)->setPrecioDolar($total);
                                $entityManagerInterface->flush();

                                return $this->redirectToRoute('pagar');
                            }
                        }
                    } catch (\Exception $th) {
                        $errors[] = $th->getMessage();
                    }
                }
            }
        }

        return $this->render('reservacion/asiento.html.twig', [
            'reservacion' => $this->reservacion,
            'errors' => $errors,
            'form' => $form,
            'primer_render' => $primer_render,
        ]);
    }

    #[Route('/asiento-lista/{id}', name: 'asiento-lista')]
    public function getAsientos(EntityManagerInterface $entityManagerInterface,  SalidaReservacion $salida, RemoteDatabaseQueries $sistema, TranslatorInterface $translatorInterface, $regreso = null): Response {

        if ($result = $sistema->getAsientos($salida->getSalidaId())) {

            list($asientos, $senales) = $result;

            $max_h_1 = $max_h_2 = 0;

            $parseAsientos = function (SalidaReservacion $salida, $asientos, $nivel1 = true) use (&$max_h_1, &$max_h_2) {

                $numeros = [];

                return array_map(
                    function ($item) use ($salida) {

                        if ($asientos_elegidos = $salida->getAsientos()) {

                            if (in_array($item['id'], $asientos_elegidos->map(fn(Asiento $item) => $item->getAsientoId())->toArray())) {

                                $item['elegido'] = true;
                            }
                        }
                        return $item;
                    },
                    array_filter(

                        $asientos,

                        function ($item) use (&$max_h_1, &$max_h_2, $nivel1, &$numeros) {

                            if (!is_array($item))
                                die($item);

                            if (\in_array($item['numero'], $numeros)) {

                                return false;
                            }

                            $numeros[] = $item['numero'];

                            if ($nivel1 && !$item['nivel2'] && $max_h_1 < $item['coordenadaY']) {

                                $max_h_1 = $item['coordenadaY'];
                            } elseif (!$nivel1 && $item['nivel2'] && $max_h_2 < $item['coordenadaY']) {

                                $max_h_2 = $item['coordenadaY'];
                            }

                            return $nivel1 ? !$item['nivel2'] : $item['nivel2'];
                        }
                    )
                );
            };

            $asientos_nivel_1 = $parseAsientos($salida, $asientos);

            $asientos_nivel_2 = $parseAsientos($salida, $asientos, false);

            $senales_nivel_1 = array_filter($senales, fn($item) => !$item['nivel2']);

            $senales_nivel_2 = array_filter($senales, fn($item) => $item['nivel2']);
        } else {
            $error = $translatorInterface->trans('No se pudo establecer la conexión con el sistema de reserva. Por favor inténtelo más tarde');
        }


        if (!empty($ocupados = $salida->getAsientos()->filter(function (Asiento $asiento) use ($asientos) {

            if ($item = \array_filter($asientos, function ($a) use ($asiento) {

                return $a['id'] == $asiento->getAsientoId();
            })) {

                return ReservacionService::validarAsientoSistemaOcupado(\array_values($item)[0]);
            }

            return false;
        })->toArray())) {
            $asientos_ocupados_numero = [];
            foreach ($ocupados as $value) {
                $asientos_ocupados_numero[] = $value->getNumero();
                $entityManagerInterface->remove($value);
            }
            $entityManagerInterface->flush();

            $asientos_ocupados_numero =  sprintf($translatorInterface->trans('Desafortunadamente han sido ocupados los asientos con números') . ' : %s.', \implode(', ', $asientos_ocupados_numero));
        }

        return $this->render('reservacion/_asiento_lista.html.twig', [
            'asientos_nivel_1' => $asientos_nivel_1 ?? [],
            'senales_nivel_1' => $senales_nivel_1 ?? [],
            'asientos_nivel_2' => $asientos_nivel_2 ?? [],
            'senales_nivel_2' => $senales_nivel_2 ?? [],
            'error' => $error ?? $asientos_ocupados_numero ?? null,
            'regreso' => $regreso,
            'max_h_1' => $max_h_1 * 1.32 + 160,
            'max_h_2' => $max_h_2 * 1.32 + 160,
            'reservacion' => $this->reservacion
        ]);
    }

    #[Route('/pagar', name: 'pagar')]
    public function pagar(LockReservacion $lockReservacion, ServerSentEvent $serverSentEvent, Request $request, EntityManagerInterface $entityManagerInterface, TranslatorInterface $translatorInterface, $primer_render = null): Response {

        if (!$cliente = $this->reservacion->getCliente()) {
            $this->reservacion->setCliente($cliente = new ClienteReservacion($entityManagerInterface->getRepository(Countries::class)->findOneBy(['iso3' => 'GTM'])));
        }

        $form = $this->createForm(PagoDatosType::class, $this->reservacion, [
            'action' => $this->generateUrl('pagar', ['reservacion' => $this->reservacion->getId()]),
            'reservacion' => $this->reservacion,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($this->reservacion->getTransaccionId()) {
                return new RedirectResponse($this->generateUrl('confirmacion'));
            }

            // Anti doble pago: si ya hay un flujo 3DS en curso (< 15 min), no iniciar otro
            // cobro. El LockReservacion solo cubre el request de pagar(); el flujo completo
            // (DDC -> challenge -> validation) toma minutos y la marca lo cubre.
            $pagoEnCurso = $request->getSession()->get('pago_en_curso');
            if ($pagoEnCurso && $pagoEnCurso > time() - 900) {
                return $serverSentEvent->errorPago(
                    mensaje: 'Hay un pago en línea en proceso para esta reservación. Espere unos minutos a que finalice antes de iniciar otro.',
                    conservarPagoEnCurso: true
                );
            }

            if (!$lockReservacion->acquire()) {

                return $serverSentEvent->errorPago(mensaje: 'Aún se está procesando una compra de usted anterior. Por favor espere que finalice para comprar otro boleto.');
            }

            try {
                if ($request->request->has('pago_datos') && $pago_datos = array_filter($request->request->all()['pago_datos'], fn($k) => 'cliente' != $k && 'notaClienteSalida' != $k && 'notaClienteRegreso' != $k, \ARRAY_FILTER_USE_KEY)) {

                    $request->getSession()->set('pago_datos', $pago_datos);

                    $this->reservacion->setMoneda($pago_datos['moneda'])->setTarjeta($entityManagerInterface->find(Tarjeta::class, $pago_datos['tarjeta']));

                    $this->reservacion->setCliente($cliente);

                    $data = $request->request->all()['pago_datos'];
                    $salida = $this->reservacion->getSalida();
                    $salida->setClienteNota($data['notaClienteSalida']);
                    if ($regreso = $this->reservacion->getRegreso()) {
                        $regreso->setClienteNota($data['notaClienteRegreso']);
                    }

                    $entityManagerInterface->flush();

                    try {

                        $response = $this->reservacionService->crearBoleto();

                        if (true === $response) {
                            // Boleto creado correctamente -> continuar con el flujo 3DS.
                            // Marca el pago en curso para bloquear reintentos hasta que concluya.
                            $request->getSession()->set('pago_en_curso', time());
                            return $this->forward('App\Controller\ReservacionController::payerAuthenticationSetupService');
                        }

                        if (\is_array($response)) {

                            if (isset($response['error'])) {
                                return $serverSentEvent->errorPago(detalle: $response['error']);
                            } else if (isset($response['asientos_ocupados_numeros'])) {

                                $error = $translatorInterface->trans('Desafortunadamente, los asientos siguientes acaban de ser ocupados.') . ': ' . \implode(', ', $response['asientos_ocupados_numeros']);

                                $this->addFlash(
                                    'error_asientos_ocupados',
                                    $error
                                );
                                return $this->redirectToRoute('asientos');
                            }

                            error_log(\sprintf(
                                '[CyberSource | Visa] pagar: crearBoleto devolvió array sin error ni asientos ocupados -> %s',
                                \json_encode($response)
                            ));
                            return $serverSentEvent->errorPago(detalle: \json_encode($response));
                        }

                        // false -> el sistema remoto no devolvió array; el boleto NO se creó: cortar el pago
                        error_log('[CyberSource | Visa] pagar: crearBoleto devolvió false (sin boleto). No se procesa el pago.');
                        return $serverSentEvent->errorPago(detalle: $translatorInterface->trans('No fue posible crear el boleto; no se procesa el pago.'));
                    } catch (\Exception $e) {

                        return $serverSentEvent->errorPago(detalle: $e->getMessage());
                    }
                }
            } finally {
                $lockReservacion->release();
            }
        } elseif ($form->isSubmitted()) {
            $error = $translatorInterface->trans('Información incorrecta o no proporcionada. Por favor revise que datos son inválidos.');
        }
        return $this->render('reservacion/pagar.html.twig', [
            'form' => $form,
            'primer_render' => $primer_render,
            'reservacion' => $this->reservacion,
            'error' => $error ?? null,

        ]);
    }

    #[Route('/payer_authentication_setup_service', name: 'payer_authentication_setup_service')]
    public function payerAuthenticationSetupService(ServerSentEvent $serverSentEvent, Request $request, CybersourceApi $cybersourceApi, TranslatorInterface $translatorInterface, EntityManagerInterface $entityManagerInterface) {

        try {
            $response = $cybersourceApi->payerAuthenticationSetupService();
        } catch (\Throwable $th) {
            error_log(\sprintf(
                '[CyberSource | Visa] payerAuthenticationSetupService: EXCEPCION -> %s',
                $th->getMessage()
            ));
            try {
                $error = new ErrorFdn();
                $error->setError($th)->setReservacion($this->reservacion);
                $entityManagerInterface->persist($error);
                $entityManagerInterface->flush();
            } catch (\Throwable $th2) {
                //throw $th2;
            }
            return $serverSentEvent->errorPago(detalle: $th->getMessage());
        }

        if (is_array($response)) {

            if (isset($response['consumerAuthenticationInformation'], $response['consumerAuthenticationInformation']['referenceId'])) {

                $request->getSession()->set('referenceId', $response['consumerAuthenticationInformation']['referenceId']);

                error_log(\sprintf(
                    '[CyberSource | Visa] payerAuthenticationSetupService: status=%s referenceId=%s deviceDataCollectionUrl=%s',
                    $response['status'] ?? CybersourceApi::AUTHENTICATION_SUCCESSFUL,
                    $response['consumerAuthenticationInformation']['referenceId'],
                    $response['consumerAuthenticationInformation']['deviceDataCollectionUrl'] ?? 'N/A'
                ));

                return $serverSentEvent->procesandoPago('data_collection_iframe', [
                    'status' => $response['status'] ?? CybersourceApi::AUTHENTICATION_SUCCESSFUL,
                    'accessToken' => $response['consumerAuthenticationInformation']['accessToken'],
                    'deviceDataCollectionUrl' => $response['consumerAuthenticationInformation']['deviceDataCollectionUrl'],
                    'cybersource_trusted_origins' => $this->getParameter('cybersource_trusted_origins'),
                ], 'reservacion/_iframe_device_data_collection.stream.html.twig');
            }

            try {
                $error = new ErrorFdn();
                $error->setError($response)->setReservacion($this->reservacion);
                $entityManagerInterface->persist($error);
                $entityManagerInterface->flush();
            } catch (\Throwable $th) {
                //throw $th;
            }
            error_log(\sprintf(
                '[CyberSource | Visa] payerAuthenticationSetupService: respuesta sin referenceId -> %s',
                \json_encode($response)
            ));
            return $serverSentEvent->errorPago(detalle: $response);
        }

        $resumenRespuesta = false === $response
            ? 'boleto ya con transaccionId (setup corto-circuitado)'
            : (\is_scalar($response) ? (string) $response : 'valor vacio');
        error_log(\sprintf(
            '[CyberSource | Visa] payerAuthenticationSetupService: respuesta NO es array -> %s',
            $resumenRespuesta
        ));

        return $serverSentEvent->errorPago(detalle: $translatorInterface->trans('Los datos de la tarjeta son incorrectos.'));
    }

    #[Route('/payer-authentication-check-enrollment/{session_id_challenge_response}', name: 'payer_authentication_check_enrollment')]
    public function payerAuthenticationCheckEnrollmentService(LockReservacion $lockReservacion, RemoteDatabaseQueries $remoteDatabaseQueries, EntityManagerInterface $entityManagerInterface, ServerSentEvent $serverSentEvent, Request $request, CybersourceApi $cybersourceApi, $session_id_challenge_response = null) {

        if (!$lockReservacion->acquire()) {

            return $serverSentEvent->errorPago(mensaje: 'Aún se está procesando una compra de usted anterior. Por favor espere que finalice para comprar otro boleto.');
        }

        try {
            // El completado del DDC (data_collection_iframe_controller.js) llega con
            // iframe_collection=complete; la respuesta del ACS vía returnUrl NO lo trae.
            // Antes de la migración el DDC llegaba sin session_id_challenge_response y caía en la
            // API; tras KA-09648 la ruta exige el sid, así que el discriminador es iframe_collection.
            $esCompletadoDDC = $request->query->has('iframe_collection') || $request->request->has('iframe_collection');

            if ($session_id_challenge_response && !$esCompletadoDDC) {

                $sessionCookiePresente = $request->cookies->has($request->getSession()->getName());

                error_log(\sprintf(
                    '[CyberSource | Visa] Challenge response recibido: session_id_challenge_response=%s vs session actual=%s (cookie de sesión presente: %s)',
                    $session_id_challenge_response,
                    $request->getSession()->getId(),
                    $sessionCookiePresente ? 'si' : 'no'
                ));

                if ($session_id_challenge_response !== $request->getSession()->getId() && $sessionCookiePresente) {
                    error_log('[CyberSource | Visa] Challenge response RECHAZADO: session no coincide y la cookie de sesión fue enviada.');
                    return $serverSentEvent->errorPago(detalle: 'Invalid challenge response session.');
                }

                if ($session_id_challenge_response !== $request->getSession()->getId()) {
                    error_log('[CyberSource | Visa] Challenge response: session no coincide pero NO llegó cookie (iframe cross-site del ACS). Se continúa por correlación con session_id_challenge_response del returnUrl.');
                }

                error_log(\sprintf(
                    '[CyberSource | Visa] Challenge response POST: Content-Type=%s campos=%s',
                    $request->headers->get('Content-Type') ?? 'GET sin body',
                    \json_encode(\array_keys($request->request->all()))
                ));

                $publicacion = $serverSentEvent->stream(['authentication_check_enrollment_challenge_response', $session_id_challenge_response], $request->request->all());
                error_log(\sprintf(
                    '[CyberSource | Visa] challenge_response publicado en Mercure (false=OK, string=error): %s',
                    false === $publicacion ? 'OK' : (string) $publicacion
                ));

                return new Response(null, Response::HTTP_NO_CONTENT, ['procesando-pago' => true]);
            }

            if ($esCompletadoDDC) {
                error_log(\sprintf(
                    '[CyberSource | Visa] DDC completado (iframe_collection=complete, session=%s) -> ejecutando payerAuthenticationCheckEnrollmentService',
                    $request->getSession()->getId()
                ));
            }

            if (
                \is_array($response = $cybersourceApi->payerAuthenticationCheckEnrollmentService(
                    $request->getSession()->get('referenceId'),
                    $this->urlPagoAbsoluta($request, 'payer_authentication_check_enrollment', ['session_id_challenge_response' => $request->getSession()->getId()])
                ))
            ) {
                if (isset($response['status'])) {

                    if (CybersourceApi::PENDING_AUTHENTICATION == $response['status']) {

                        $pareq = $response['consumerAuthenticationInformation']['pareq'] ?? null;
                        $window = $pareq ? \json_decode(\base64_decode($pareq)) : null;

                        if (\is_object($window) && isset($window->challengeWindowSize)) {
                            // Guía Payer Auth REST (CyberSource, p.23): el challengeWindowSize
                            // codifica ancho x alto -> {01: 250x400, 02: 390x400, 03: 500x600,
                            // 04: 600x400, 05: full}. Aquí el destructuring es [alto, ancho].
                            [$height, $width] = match ($window->challengeWindowSize) {
                                '01' => [400, 250],
                                '02' => [400, 390],
                                '03' => [600, 500],
                                '04' => [400, 600],
                                default => ['100%', '100%'],
                            };
                        } else {
                            $height = $width = '100%';
                            error_log(\sprintf(
                                '[CyberSource | Visa] payerAuthenticationCheckEnrollmentService: PENDING sin pareq/challengeWindowSize -> %s',
                                \json_encode($response['consumerAuthenticationInformation'] ?? [])
                            ));
                        }

                        $request->getSession()->set('authenticationTransactionId', $response['consumerAuthenticationInformation']['authenticationTransactionId'] ?? null);

                        error_log(\sprintf(
                            '[CyberSource | Visa] payerAuthenticationCheckEnrollmentService: PENDING challenge -> stepUpUrl=%s authenticationTransactionId=%s windowSize=%s (%sx%s)',
                            $response['consumerAuthenticationInformation']['stepUpUrl'] ?? 'N/A',
                            $response['consumerAuthenticationInformation']['authenticationTransactionId'] ?? 'N/A',
                            \is_object($window) && isset($window->challengeWindowSize) ? $window->challengeWindowSize : 'N/A',
                            \is_string($height) ? $height : (string) $height,
                            \is_string($width) ? $width : (string) $width
                        ));

                        return $serverSentEvent->procesandoPago('authentication_check_enrollment', [
                            'height' => $height,
                            'width' => $width,
                            'stepUpUrl' => $response['consumerAuthenticationInformation']['stepUpUrl'],
                            'accessToken' => $response['consumerAuthenticationInformation']['accessToken'],
                        ], 'reservacion/_iframe_authentication_check_enrollment.stream.html.twig');
                    }

if (CybersourceApi::AUTHORIZED == $response['status'] || CybersourceApi::AUTHENTICATION_SUCCESSFUL == $response['status']) {

                        $authInfo = $response['consumerAuthenticationInformation'] ?? [];
                        $authStatus = $authInfo['authenticationStatus'] ?? null;
                        $eci = $authInfo['eciRaw'] ?? ($authInfo['eci'] ?? null);
                        $cardEnrolled = $authInfo['cardEnrolled'] ?? 'U';

                        // El criptograma 3DS llega por marca en campos distintos de la API de pagos
                        // (/pts/v2/payments): cavv (Visa), token (CAVV genérico de CyberSource),
                        // ucafAuthenticationData + paresStatus=Y (Mastercard/UCAF). La autenticación
                        // se evalúa sin atarse a los campos de una marca concreta.
                        [$autenticado, $evidencia] = $this->autenticacionValida($authInfo);

                        error_log(\sprintf(
                            '[CyberSource | Visa] payerAuthenticationCheckEnrollmentService: %s -> id=%s | authStatus=%s evidencia=%s eci=%s cardEnrolled=%s',
                            $response['status'],
                            $response['id'] ?? 'N/A',
                            $authStatus ?? 'N/A',
                            $evidencia ?? 'SIN_CRIPTROGRAMA',
                            $eci ?? 'N/A',
                            $cardEnrolled
                        ));

                        if (!$autenticado) {
                            // El cobro YA fue capturado con el mismo request (capture: true):
                            // revertirlo de inmediato para que el cliente no quede cobrado sin boleto.
                            $this->revertirCobroSiAutorizado($cybersourceApi, $entityManagerInterface, $response, 'payerAuthenticationCheckEnrollmentService');
                            error_log(\sprintf(
                                '[CyberSource | Visa] payerAuthenticationCheckEnrollmentService: status=%s SIN autenticación real (authStatus=%s evidencia=%s cardEnrolled=%s) -> rechazado (motivo 475)',
                                $response['status'],
                                $authStatus ?? 'N/A',
                                $evidencia ?? 'SIN_CRIPTROGRAMA',
                                $cardEnrolled
                            ));
                            try {
                                $error = new ErrorFdn();
                                $error->setError($response)->setReservacion($this->reservacion);
                                $entityManagerInterface->persist($error);
                                $entityManagerInterface->flush();
                            } catch (\Throwable $th) {
                                //throw $th;
                            }
                            return $serverSentEvent->errorPago(detalle: 'Tarjetahabiente inscrito en Payer Authentication. Autentique al tarjetahabiente antes de continuar.');
                        }

                        if (isset($response['id'])) {

                            $this->reservacion->setTransaccionId($response['id']);
                            $this->reservacion->setStatus(Reservacion::COMPLETADA)->setPasoCompletado(4);
                            try {
                                $remoteDatabaseQueries->deleteAsientoTemp($this->reservacion->getId());
                            } catch (\Throwable $th) {
                            }

                            $entityManagerInterface->flush();
                        }

                        // Pago concluido con éxito: terminar la marca anti doble pago.
                        $request->getSession()->remove('pago_en_curso');

                        $destinoPago = $request->getSession()->get('transferencia_redirect', 'facturar');
                        error_log(\sprintf(
                            '[CyberSource | Visa] post-pago -> forward a "%s" (boleto %s)',
                            $destinoPago,
                            $this->reservacion->getId()
                        ));

                        return $this->forward('App\Controller\ReservacionController::' . $destinoPago);
                    }

                    try {
                        $error = new ErrorFdn();
                        $error->setError($response)->setReservacion($this->reservacion);
                        $entityManagerInterface->persist($error);
                        $entityManagerInterface->flush();
                    } catch (\Throwable $th) {
                        //throw $th;
                    }
                }
            }
        } catch (\Throwable $th) {
            error_log(\sprintf(
                '[CyberSource | Visa] payerAuthenticationCheckEnrollmentService: EXCEPCION -> %s',
                $th->getMessage()
            ));
            try {
                $error = new ErrorFdn();
                $error->setError($th)->setReservacion($this->reservacion);
                $entityManagerInterface->persist($error);
                $entityManagerInterface->flush();
            } catch (\Throwable $th2) {
                //throw $th2;
            }
            return $serverSentEvent->errorPago(detalle: $th->getMessage());
        } finally {
            $lockReservacion->release();
        }
        error_log(\sprintf(
            '[CyberSource | Visa] payerAuthenticationCheckEnrollmentService: fallo -> %s',
            \is_array($response) ? \json_encode($response) : (\is_scalar($response) ? (string) $response : 'respuesta vacia')
        ));
        return $serverSentEvent->errorPago(detalle: $response);
    }

    #[Route('/payer_authentication_validation_service', name: 'payer_authentication_validation_service')]
    public function payerAuthenticationValidationService(LockReservacion $lockReservacion, RemoteDatabaseQueries $remoteDatabaseQueries,  EntityManagerInterface $entityManagerInterface, ServerSentEvent $serverSentEvent, Request $request, CybersourceApi $cybersourceApi) {

        if (!$lockReservacion->acquire()) {

            return $serverSentEvent->errorPago(mensaje: 'Aún se está procesando una compra de usted anterior. Por favor espere que finalice para comprar otro boleto.');
        }
        try {

            error_log(\sprintf(
                '[CyberSource | Visa] payerAuthenticationValidationService: request recibido (authenticationTransactionId=%s, Content-Type=%s)',
                $request->getSession()->get('authenticationTransactionId') ?? 'AUSENTE',
                $request->headers->get('Content-Type') ?? 'N/A'
            ));

            $response = $cybersourceApi->payerAuthenticationValidationService($request->getSession()->get('authenticationTransactionId'));

            if (\is_array($response) && isset($response['status'])) {

                error_log(\sprintf(
                    '[CyberSource | Visa] payerAuthenticationValidationService: status=%s reasonCode=%s reason=%s message=%s',
                    $response['status'],
                    $response['reasonCode'] ?? 'N/A',
                    $response['errorInformation']['reason'] ?? 'N/A',
                    $response['errorInformation']['message'] ?? 'N/A'
                ));

                if (CybersourceApi::AUTHENTICATION_FAILED == $response['status'] || (isset($response['reasonCode']) && \in_array((string) $response['reasonCode'], ['475', '476', '478'], true))) {

                    return $serverSentEvent->errorPago(detalle: $response);
                } else if (CybersourceApi::AUTHORIZED == $response['status'] || CybersourceApi::AUTHENTICATION_SUCCESSFUL == $response['status']) {

                    $authInfo = $response['consumerAuthenticationInformation'] ?? [];
                    $authStatus = $authInfo['authenticationStatus'] ?? null;
                    $eci = $authInfo['eciRaw'] ?? ($authInfo['eci'] ?? null);
                    $cardEnrolled = $authInfo['cardEnrolled'] ?? 'U';

                    // Mismo criterio multi-marca que en check-enrollment: el criptograma
                    // puede llegar como cavv/token/ucaf según el emisor.
                    [$autenticado, $evidencia] = $this->autenticacionValida($authInfo);

                    error_log(\sprintf(
                        '[CyberSource | Visa] payerAuthenticationValidationService: %s -> id=%s | authStatus=%s evidencia=%s eci=%s cardEnrolled=%s',
                        $response['status'],
                        $response['id'] ?? 'N/A',
                        $authStatus ?? 'N/A',
                        $evidencia ?? 'SIN_CRIPTROGRAMA',
                        $eci ?? 'N/A',
                        $cardEnrolled
                    ));

                    if (!$autenticado) {
                        // El cobro YA fue capturado con el mismo request (capture: true):
                        // revertirlo de inmediato para que el cliente no quede cobrado sin boleto.
                        $this->revertirCobroSiAutorizado($cybersourceApi, $entityManagerInterface, $response, 'payerAuthenticationValidationService');
                        error_log(\sprintf(
                            '[CyberSource | Visa] payerAuthenticationValidationService: status=%s SIN autenticación real (authStatus=%s evidencia=%s cardEnrolled=%s) -> rechazado (motivo 475)',
                            $response['status'],
                            $authStatus ?? 'N/A',
                            $evidencia ?? 'SIN_CRIPTROGRAMA',
                            $cardEnrolled
                        ));
                        try {
                            $error = new ErrorFdn();
                            $error->setError($response)->setReservacion($this->reservacion);
                            $entityManagerInterface->persist($error);
                            $entityManagerInterface->flush();
                        } catch (\Throwable $th) {
                            //throw $th;
                        }
                        return $serverSentEvent->errorPago(detalle: 'Tarjetahabiente inscrito en Payer Authentication. Autentique al tarjetahabiente antes de continuar.');
                    }

                    if (isset($response['id'])) {

                        $this->reservacion->setTransaccionId($response['id']);
                        $this->reservacion->setStatus(Reservacion::COMPLETADA)->setPasoCompletado(4);
                        try {
                            $remoteDatabaseQueries->deleteAsientoTemp($this->reservacion->getId());
                        } catch (\Throwable $th) {
                        }

                        $entityManagerInterface->flush();
                    }

                    $destinoPago = $request->getSession()->get('transferencia_redirect', 'facturar');
                        error_log(\sprintf(
                            '[CyberSource | Visa] post-pago -> forward a "%s" (boleto %s)',
                            $destinoPago,
                            $this->reservacion->getId()
                        ));

                        return $this->forward('App\Controller\ReservacionController::' . $destinoPago);
                }
            }

            if (\is_array($response)) {
                try {
                    $error = new ErrorFdn();
                    $error->setError($response)->setReservacion($this->reservacion);
                    $entityManagerInterface->persist($error);
                    $entityManagerInterface->flush();
                } catch (\Throwable $th) {
                    //throw $th;
                }
            }

            error_log(\sprintf(
                '[CyberSource | Visa] payerAuthenticationValidationService: fallo -> %s',
                \is_array($response) ? \json_encode($response) : (\is_scalar($response) ? (string) $response : 'respuesta vacia')
            ));

            return $serverSentEvent->errorPago(detalle: $response);
        } catch (\Throwable $th) {
            error_log(\sprintf(
                '[CyberSource | Visa] payerAuthenticationValidationService: EXCEPCION -> %s',
                $th->getMessage()
            ));
            return $serverSentEvent->errorPago(detalle: $th->getMessage());
        } finally {
            $lockReservacion->release();
        }
    }

    /**
     * returnUrl para el ACS del banco. Detrás de Caddy/reverse-proxy Symfony ve
     * scheme=http (sin trusted_proxies) y la URL absoluta de generateUrl sale como
     * http://...: el POST de vuelta del ACS puede morir en la conexión o ser
     * rechazado -> el challenge queda sin feedback (carga infinita). Se construye
     * la URL absoluta HTTPS con el host público de forma determinista.
     */
    private function urlPagoAbsoluta(Request $request, string $ruta, array $parametros = []): string
    {
        $scheme = ('https' === $request->headers->get('X-Forwarded-Proto') || 'https' === $request->getScheme()) ? 'https' : 'http';
        $host = $request->headers->get('X-Forwarded-Host', $request->getHost());

        return $scheme . '://' . $host . $this->generateUrl($ruta, $parametros);
    }

    /**
     * Determina si la respuesta trae una autenticación 3DS válida sin atarse a
     * los campos de una marca concreta. En la API de pagos (/pts/v2/payments) el
     * criptograma llega como cavv (Visa), token (CAVV genérico de CyberSource,
     * incluye UCAF) o ucafAuthenticationData + paresStatus=Y (Mastercard). El
     * fallback cardEnrolled=N cubre flujos sin desafío (no inscrita).
     *
     * @return array{0: bool, 1: ?string} [autenticado, evidencia reconocida]
     */
    private function autenticacionValida(array $authInfo): array
    {
        $cavv = $authInfo['cavv'] ?? null;
        $token = $authInfo['token'] ?? null;
        $ucaf = $authInfo['ucafAuthenticationData'] ?? null;
        $paresOk = 'Y' === ($authInfo['paresStatus'] ?? null);
        $authStatus = $authInfo['authenticationStatus'] ?? null;
        $cardEnrolled = $authInfo['cardEnrolled'] ?? 'U';

        if ($cavv) {
            return [true, 'cavv'];
        }
        if ($token) {
            return [true, 'token'];
        }
        if ($ucaf && $paresOk) {
            return [true, 'ucaf+pares'];
        }
        if ('N' === $cardEnrolled && isset($authStatus) && \in_array($authStatus, ['SUCCESSFUL', 'ATTEMPTED'], true)) {
            return [true, 'no_inscrita'];
        }

        return [false, null];
    }

    /**
     * Revierte un cobro que YA fue capturado (AUTHORIZED/AUTHENTICATION_SUCCESSFUL
     * con capture: true en el mismo request) pero que la aplicación rechaza
     * localmente, para que el cliente jamás quede cobrado sin boleto. Registra el
     * resultado en status_cybersources y en el log.
     */
    private function revertirCobroSiAutorizado(CybersourceApi $cybersourceApi, EntityManagerInterface $entityManagerInterface, array $response, string $contexto): void
    {
        $paymentId = $response['id'] ?? null;
        if (!$paymentId) {
            error_log(\sprintf(
                '[CyberSource | Visa] %s: cobro autorizado sin payment id -> no se puede revertir.',
                $contexto
            ));
            return;
        }

        $void = $cybersourceApi->voidPayment((string) $paymentId);
        $estadoVoid = \is_array($void) ? ($void['status'] ?? null) : null;
        $ok = \in_array($estadoVoid, ['VOIDED', 'PENDING'], true);

        error_log(\sprintf(
            '[CyberSource | Visa] %s: rechazo post-captura -> void %s -> %s',
            $contexto,
            $paymentId,
            $ok ? (string) $estadoVoid : (\is_array($void) ? \json_encode($void) : (\is_scalar($void) ? (string) $void : 'FALLO'))
        ));

        try {
            $this->reservacion->setStatusCybersources(
                (string) ($this->reservacion->getStatusCybersources() ?? '') . ' | VOID:' . ($ok ? (string) $estadoVoid : 'FALLO')
            );
            $entityManagerInterface->flush();
        } catch (\Throwable $th) {
            error_log(\sprintf(
                '[CyberSource | Visa] %s: no se pudo registrar el void en status_cybersources: %s',
                $contexto,
                $th->getMessage()
            ));
        }
    }

    #[Route('/buscar-nit/{nit}', name: 'buscar_sat_nit')]
    public function nit(SatRestService $satRestService, $nit): Response {

        try {
            $response = $satRestService->consultarNitReceptor(\strtoupper(\preg_replace("/[^A-Za-z0-9 ]/", '', $nit)));
            return new JsonResponse($response, isset($response['error']) ? 500 : 200);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/facturar', name: 'facturar')]
    public function facturar(ServerSentEvent $serverSentEvent): Response {

        try {

            $resultado = $this->reservacionService->emitirFactura();

            if (\is_array($resultado)) {

                error_log(\sprintf(
                    '[Factura] emitirFactura (boleto %s) devolvió: %s',
                    $this->reservacion->getId(),
                    \json_encode($resultado)
                ));
            }

            return new RedirectResponse($this->generateUrl('confirmacion'));
        } catch (\Exception $e) {

            // El cobro YA se autorizó antes de facturar (check-enrollment/validación): un fallo
            // de factura (FEL/SAT, DTE, PDF) NO puede devolver 403 — el cliente cobrado vería
            // "error de pago", reintentaría y se duplicaría el cargo (KA-09648). Se loguea el
            // detalle y se continúa a confirmacion, que emite el boleto-PDF y el correo aunque
            // falte el DTE (comportamiento pre-migración: "se creaba el boleto pdf sin los
            // datos del dte"). El admin reintenta la factura con el log como evidencia.
            error_log(\sprintf(
                '[Factura] emitirFactura (boleto %s) lanzó excepción: %s — se continúa a confirmacion sin factura',
                $this->reservacion->getId(),
                $e->getMessage()
            ));

            return new RedirectResponse($this->generateUrl('confirmacion'));
        }
    }

    #[Route('/confirmacion', name: 'confirmacion')]
    public function confirmacion(Mails $mails, EntityManagerInterface $entityManagerInterface, Request $request): Response {
        try {

            if (!$this->reservacion->getCliente()) {
                return $this->redirectToRoute('inicio');
            }
            if (!$this->reservacion->isEmailEnviado() || $request->get('enviar')) {

                if (!$this->reservacion->getFacturaPdfPath()) {

                    $pdf = $this->reservacionService->getFacturaPdf($this->reservacion);

                    $this->reservacion->getFactura()?->setPdf($pdf);

                    $entityManagerInterface->flush();
                }

                $mails->reservacionEmail($this->reservacion);

                $this->reservacion->setEmailEnviado(true);

                $entityManagerInterface->flush();
            } else {

                $no_enviar = true;
            }
        } catch (\Exception $e) {

            $this->reservacion->setEmailEnviado(false);

            $entityManagerInterface->flush();

            $confirmacion_error = $e->getMessage();
        }

        return $this->render('reservacion/confirmacion.html.twig', [
            'reservacion' => $this->reservacion,
            'confirmacion_error' =>  $confirmacion_error ?? null,
            'no_enviar' =>  $no_enviar ?? null,
        ]);
    }

    #[Route('/pdf/{id}', name: 'pdf')]
    public function pdf(): Response {
        if (!$path = $this->reservacion->getFacturaPdfPath()) {
            return new Response('no pdf');
        }
        $response = new Response(file_get_contents($this->reservacion->getFacturaPdfPath()));

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->reservacion->getFactura()->getPdf()
        );
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    #[Route('/reiniciar', name: 'reiniciar')]
    public function reiniciar(LockReservacion $lockReservacion, Request $request): Response {

        $lockReservacion->release();

        $request->getSession()->remove('pago_en_curso');

        $this->reservacionService->reiniciar();

        return new RedirectResponse($this->generateUrl('inicio'));
    }

    #[Route('/pais', name: 'pais')]
    public function pais(FormFactoryInterface $formFactoryInterface): Response {
        return $this->render('reservacion/_pais.html.twig', ['form' => $formFactoryInterface->createNamed('cliente_reservacion', PaisAutocompleteType::class, $this->reservacion->getCliente()?->getPais())]);
    }

    #[Route('/provincias/{pais}', name: 'provincias')]
    public function provincias(?Countries $pais = null): Response {
        if (!$this->reservacion) {
            $this->reservacion = new Reservacion();
            $this->reservacion->setCliente(new ClienteReservacion());
        }

        return $this->render('reservacion/_provincias.html.twig', [
            'form' => $this->createForm(PagoDatosType::class, $this->reservacion, [
                'reservacion' => $this->reservacion,
            ])->get('cliente')->add('provincia', ProvinciaAutocompleteType::class, ['label' => false, 'pais' => $pais?->getId(), 'data' => null]),
        ]);
    }

    #[Route('/ciudad/{provincia}', name: 'ciudad')]
    public function municipios(?States $provincia = null): Response {
        // $this->reservacion->getCliente()->setProvincia($provincia);

        if (!$this->reservacion) {
            $this->reservacion = new Reservacion();
            $this->reservacion->setCliente(new ClienteReservacion());
        }


        return $this->render('reservacion/_municipios.html.twig', [
            'form' => $this->createForm(PagoDatosType::class, $this->reservacion, [
                'reservacion' => $this->reservacion,
            ])->get('cliente')->remove('ciudad')->add('ciudad', CiudadAutocompleteType::class, ['label' => false, 'provincia' => $provincia?->getId(), 'data' => null]),
        ]);
    }



    #[Route('/transfer/{rese}', name: 'transferencia')]
    public function transferencia(Request $request, EntityManagerInterface $entityManagerInterface, TranslatorInterface $translatorInterface, Reservacion $rese = null): Response {

        if (!$uuid = $request->getSession()->get('uuid')) {
            $request->getSession()->set('uuid', $uuid = (string) Uuid::v1());
        }

        if ($request->getSession()->has('transferencia_redirect', 'transferencia')) {

            $request->getSession()->remove('transferencia_redirect');

            if (!$request->getSession()->has('error_transferencia')) {
                return $this->render('reservacion/_transferencia.html.twig', [
                    'completada' => true,
                ]);
            } else {
                $request->getSession()->remove('error_transferencia');
            }
        }

        if ($rese && $rese?->getId()) {

            $this->reservacion = $rese;

            $request->getSession()->set('reservacion', $this->reservacion->getId());
        }

        if (!$cliente = $this->reservacion->getCliente()) {
            $cliente = new ClienteReservacion($entityManagerInterface->getRepository(Countries::class)->findOneBy(['iso3' => 'GTM']));
            $this->reservacion->setCliente($cliente);
        }

        $form = $this->createForm(PagoDatosType::class, $this->reservacion, [
            'action' => $this->generateUrl('transferencia'),
            'reservacion' => $this->reservacion,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($request->request->has('pago_datos') && $pago_datos = array_filter($request->request->all()['pago_datos'], fn($k) => 'cliente' != $k, \ARRAY_FILTER_USE_KEY)) {

                $request->getSession()->set('pago_datos', $pago_datos);

                $this->reservacion->setMoneda($pago_datos['moneda'])->setTarjeta($entityManagerInterface->find(Tarjeta::class, $pago_datos['tarjeta']));

                $this->reservacion->setCliente($cliente);

                $entityManagerInterface->flush();

                $request->getSession()->set('transferencia_redirect', 'transferencia');

                return $this->forward('App\Controller\ReservacionController::payerAuthenticationSetupService');
            }
        } else if ($form->isSubmitted()) {
            $error = $translatorInterface->trans('Información incorrecta o no proporcionada. Por favor revise que datos son inválidos.');
        }


        return $this->render('transferencia.html.twig', [
            'form' => $form,
            'reservacion' => $this->reservacion,
            'error' => $error ?? null,
            'cybersource_session_id' => ($this->credenciales[$this->reservacion?->getEmpresaFactura()?->getSlug()]['CYBERSOURCE_MERCHANT_ID'] ?? '') . $uuid,
            'org_id' => $this->getParameter('org_id'),
        ]);
    }
}
