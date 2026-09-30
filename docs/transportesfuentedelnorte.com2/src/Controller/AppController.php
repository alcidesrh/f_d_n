<?php

namespace App\Controller;

use App\Entity\Contacto;
use App\Entity\Reservacion;
use App\Form\ContactoType;
use App\Repository\ConfiguracionRepository;
use App\Repository\DepartamentoRepository;
use App\Repository\ServicioRepository;
use App\Repository\SliderRepository;
use App\Services\Mails;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AppController extends AbstractController {
    public function __construct(private SeoPageInterface $seoPage, private EntityManagerInterface $entityManagerInterface) {
    }

    #[Route('/', name: 'inicio')]
    public function index(ConfiguracionRepository $configuracionRepository, ServicioRepository $servicioRepository, Request
    $request, SliderRepository $sliderRepository, Reservacion $reservacion = null): Response {
        // die('Sitio en construcción. Próximamente servicio de venta de boletos de bus.');
        // $ip = $request->getClientIp();
        // if (!\in_array($request->getClientIp(), [
        //     "162.158.11.142",
        //     "162.158.11.143",
        //     "162.158.11.144"
        // ])) {
        // die(preg_match("/108.162/i", $request->getClientIp()));
        if ($request->getClientIp() != '108.162.212.131' && $configuracionRepository->findOneBy([])->isDesactivarPagina()) {
            return $this->render('desactivado.html.twig');
        }
        // }



        return $this->render('index.html.twig', [
            'paso_completado' => $paso_completado = $reservacion?->getPasoCompletado(),
            'reservacion_action' => ['ruta', 'salida', 'asientos', 'pagar', 'confirmacion'][$paso_completado ?? 0],
            'servicios' => $servicioRepository->findBy(['inicio' => true], ['prioridad' => 'ASC']),
            'slider' => $sliderRepository->findOneBy([]),
        ]);
    }

    #[Route('/politica', name: 'politica')]
    public function politica(): Response {
        $this->seoPage->addMeta('name', 'description', '$post->getAbstract()'); // Title('Salida y destino');

        return $this->render('politica.html.twig');
    }

    #[Route('/servicio/{slug?}', name: 'servicio')]
    public function servicio(ServicioRepository $servicioRepository): Response {
        $this->seoPage->addMeta('name', 'description', '$post->getAbstract()'); // Title('Salida y destino');

        return $this->render('servicio.html.twig', [
            'servicios' => $servicioRepository->findBy([], ['prioridad' => 'ASC']), // $servicio ? [$servicio] : $servicioRepository->findBy([], ['prioridad' => 'desc']),
        ]);
    }

    #[Route('/estacion', name: 'estacion')]
    public function estacion(DepartamentoRepository $departamentoRepository): Response {
        return $this->render('estacion.html.twig', [
            'departamentos' => $departamentoRepository->getEstacionesDepartamento(),
        ]);
    }

    #[Route('/quienes-somos', name: 'historia')]
    public function historia(): Response {
        return $this->render('historia.html.twig');
    }

    #[Route('/contacto', name: 'contacto')]
    public function contacto(): Response {
        return $this->render('contacto.html.twig');
    }

    #[Route('/contacto-form', name: 'contacto-form')]
    public function contactoForm(Request $request, EntityManagerInterface $entityManagerInterface, Mails $mails): Response {
        $contacto = new Contacto();
        $form = $this->createForm(ContactoType::class, $contacto, [
            'action' => $this->generateUrl('contacto'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManagerInterface->persist($contacto);
            $entityManagerInterface->flush();
            $form = $this->createForm(ContactoType::class, new Contacto(), [
                'action' => $this->generateUrl('contacto'),
            ]);
            $guardado = true;
            $msg = <<<END
             Mensaje de: {$contacto->getNombre()}

             Correo: {$contacto->getEmail()}

             Texto del mensaje: {$contacto->getTexto()}
            END;
            // $mails->contacto($msg);
        }

        return $this->render('_contacto_form.html.twig', [
            'form' => $form,
            'guardado' => isset($guardado),
        ]);
    }

    #[Route('/videos', name: 'videos')]
    public function videos(): Response {
        return $this->render('_videos.html.twig');
    }
}
