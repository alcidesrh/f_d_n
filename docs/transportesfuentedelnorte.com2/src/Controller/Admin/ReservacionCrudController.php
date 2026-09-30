<?php

namespace App\Controller\Admin;

use App\Entity\Factura;
use App\Entity\Reservacion;
use App\Services\Mails;
use App\Services\ReservacionService;
use App\Services\SatRestService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class ReservacionCrudController extends AbstractCrudController {

    public function __construct(private AdminUrlGenerator $adminUrlGenerator) {
    }

    public static function getEntityFqcn(): string {
        return Reservacion::class;
    }

    public function configureFields(string $pageName): iterable {
        return [
            IdField::new('id')->hideOnForm(),
            DateTimeField::new('createdAt', 'Creada')->setFormat('dd/MM/yyyy h:mm a')->addCssClass('text-capitalize'),
            DateTimeField::new('updatedAt', 'Actualizada')->setFormat('dd/MM/yyyy h:mm a')->addCssClass('text-capitalize'),
            DateTimeField::new('salida.getSalidaFechaConHora', 'Salida')->setFormat('dd/MM/yyyy h:mm a')->addCssClass('text-capitalize'),
            TextField::new('getEmpresaAdmin', 'Empresa')->renderAsHtml(),
            AssociationField::new('cliente'),
            TextField::new('pais'),
            'cliente.email',
            'status',
            TextField::new('rutaForAdmin', 'Ruta'),
            NumberField::new('getAsientosCantidad', 'Asientos'),
            TextField::new('getPrecioConMoneda', 'Precio')->addCssClass('text-nowrap'),
            NumberField::new('paso_completado', 'Paso'),
            'email_enviado',
            'boleto_ticket_id',
            'transaccion_id',
            UrlField::new('factura_pdf')->setProperty('getFacturaPdfPath')->setLabel('Factura (pdf)'),
            BooleanField::new('ida_vuelta')->addCssClass('opacity-1')->setDisabled(true)->addWebpackEncoreEntries('backend')
        ];
    }

    public function configureCrud(Crud $crud): Crud {
        return $crud
            // the labels used to refer to this entity in titles, buttons, etc.
            ->setEntityLabelInSingular('Reservaciones')
            ->setEntityLabelInPlural('Reservaciones')
            ->setDefaultSort(['paso_completado' => 'DESC', 'createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions {
        $enviar_email = Action::new('enviar_email', 'Enviar Email', 'fa fa-file-invoice')
            ->linkToCrudAction('enviarEmail');

        // $enviar_email_creando_factura = Action::new('enviar_email_creando_factura', 'Enviar Email Creando Factura', 'fa fa-file-invoice')
        //     ->linkToCrudAction('enviarEmailCreandoFactura');

        $enviar_email_sin_factura = Action::new('enviar_email_sin_factura', 'Enviar Email Sin Factura', 'fa fa-file-invoice')
            ->linkToCrudAction('enviarEmailSinFactura');

        $anular_factura = Action::new('anular_factura', 'Anular factura', 'fa fa-file-invoice')
            ->linkToCrudAction('anularFactura');
        $crear_factura = Action::new('crear_factura', 'Crear factura', 'fa fa-file-invoice')
            ->linkToCrudAction('crearFactura');

        return $actions
            ->add(Crud::PAGE_INDEX, $enviar_email)
            ->add(Crud::PAGE_INDEX, $enviar_email_sin_factura)
            ->add(Crud::PAGE_INDEX, $anular_factura)
            ->add(Crud::PAGE_INDEX, $crear_factura);
    }

    public function enviarEmail(AdminContext $context, Mails $mails, ReservacionService $reservacionService, EntityManagerInterface $entityManagerInterface) {

        $reservacion = $context->getEntity()->getInstance();

        if (!$factura = $reservacion->getFactura()) {
            $factura = new Factura();
            $entityManagerInterface->persist($factura);
            $reservacion->setFactura($factura);
            $entityManagerInterface->flush();
        }

        // $client = $reservacion->getCliente();
        // $reservacion->getFactura()->setPdf($reservacionService->getFacturaPdf($reservacion));
        // $entityManagerInterface->flush();

        if ($result = $reservacionService->siNoExisteCrearFactura($reservacion)) {
            if (isset($result['error'])) {
                die($result['error']);
            }
            $reservacion->getFactura()->setPdf($reservacionService->getFacturaPdf($reservacion));

            $this->sendEmail($reservacion, $mails);

            $reservacion->setEmailEnviado(true);

            $entityManagerInterface->flush();

            $url = $this->adminUrlGenerator
                ->setController(ReservacionCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl();

            return $this->redirect($url);
        }

        return new Response('No se envio mail.');
    }

    public function enviarEmailSinFactura(AdminContext $context, Mails $mails, ReservacionService $reservacionService, EntityManagerInterface $entityManagerInterface) {

        $reservacion = $context->getEntity()->getInstance();

        if (!$factura = $reservacion->getFactura()) {
            $factura = new Factura();
            $entityManagerInterface->persist($factura);
            $reservacion->setFactura($factura);
            $entityManagerInterface->flush();
        }

        $reservacion->getFactura()->setPdf($reservacionService->getFacturaPdf($reservacion));

        $this->sendEmail($reservacion, $mails);

        $reservacion->setEmailEnviado(true);

        $entityManagerInterface->flush();

        $url = $this->adminUrlGenerator
            ->setController(ReservacionCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);

        return new Response('No se envio mail.');
    }

    public function enviarEmailCreandoFactura(AdminContext $context, Mails $mails, ReservacionService $reservacionService, EntityManagerInterface $entityManagerInterface) {

        $reservacion = $context->getEntity()->getInstance();

        if (!$factura = $reservacion->getFactura()) {
            $factura = new Factura();
            $entityManagerInterface->persist($factura);
            $reservacion->setFactura($factura);
            $entityManagerInterface->flush();
        }

        $client = $reservacion->getCliente();
        $reservacion->getFactura()->setPdf($reservacionService->getFacturaPdf($reservacion));
        $entityManagerInterface->flush();

        if ($result = $reservacionService->siNoExisteCrearFactura($reservacion)) {
            if (isset($result['error'])) {
                die($result['error']);
            }
            $reservacion->getFactura()->setPdf($reservacionService->getFacturaPdf($reservacion));

            $this->sendEmail($reservacion, $mails);

            $reservacion->setEmailEnviado(true);

            $entityManagerInterface->flush();

            $url = $this->adminUrlGenerator
                ->setController(ReservacionCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl();

            return $this->redirect($url);
        }

        return new Response('No se envio mail.');
    }

    public function configureFilters(Filters $filters): Filters {
        return $filters
            ->add('paso_completado');
        // ->add('price')
        // ->add('published');
    }

    public function anularFactura(AdminContext $context, SatRestService $satRestService, EntityManagerInterface $entityManagerInterface) {

        $reservacion = $context->getEntity()->getInstance();

        if ($result = $satRestService->anular($reservacion)) {
            if (is_array($result) && isset($result['error'])) {
                $reservacion->setStatus(Reservacion::ANULADA_ERROR_SAT);
                $entityManagerInterface->flush();
                throw new \Exception($result['text'], 1);
                return false;
            }

            $reservacion->setStatus(Reservacion::ANULADA_SAT);
            $entityManagerInterface->flush();
            $url = $this->adminUrlGenerator
                ->setController(ReservacionCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl();
            return $this->redirect($url);
        }
        $reservacion->setStatus(Reservacion::ANULADA_ERROR_SAT);
        throw new \Exception("Error al anular. Por favor intentenlo de nuevo", 1);
        return false;
    }

    public function crearFactura(AdminContext $context, ReservacionService $reservacionService, EntityManagerInterface $entityManagerInterface) {

        $reservacion = $context->getEntity()->getInstance();

        if (!$factura = $reservacion->getFactura()) {
            $factura = new Factura();
            $entityManagerInterface->persist($factura);
            $reservacion->setFactura($factura);
            $entityManagerInterface->flush();
        }
        $result = $reservacionService->emitirFactura($reservacion);
        if (!$result || isset($result['error'])) {
            throw new \Exception("Error al emitir la nueva factura: " . $result['error'] ?? 'Por favor intentenlo de nuevo', 1);
            return false;
        }

        $url = $this->adminUrlGenerator
            ->setController(ReservacionCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }
    public function sendEmail(Reservacion $reservacion, Mails $mails) {
        try {
            $mails->reservacionEmail($reservacion);
        } catch (\Exception $th) {
            die($th->getMessage());
        }
        return true;
    }
}
