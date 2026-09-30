<?php

namespace App\Controller\Admin;

use App\Entity\Archivo;
use App\Entity\ClienteReservacion;
use App\Entity\Configuracion;
use App\Entity\Contacto;
use App\Entity\Departamento;
use App\Entity\Empresa;
use App\Entity\Estacion;
use App\Entity\Reservacion;
use App\Entity\RutaReservacion;
use App\Entity\Servicio;
use App\Entity\Slider;
use App\Entity\Tarjeta;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController {
    #[Route('/admin', name: 'admin')]
    public function index(): Response {
        // return parent::index();

        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(ReservacionCrudController::class)->generateUrl());
        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(OneOfYourCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard {
        return Dashboard::new()
            ->setTitle('Transportes Fuente del Norte');
    }

    public function configureMenuItems(): iterable {
        // yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Reservaciones', 'fas fa-list', Reservacion::class);

        yield MenuItem::linkToCrud('Clientes', 'fas fa-list', ClienteReservacion::class);

        yield MenuItem::linkToCrud('Estaciones', 'fas fa-list', Estacion::class);

        yield MenuItem::linkToCrud('Rutas', 'fas fa-list', RutaReservacion::class);

        yield MenuItem::linkToCrud('Empresa', 'fas fa-list', Empresa::class);

        yield MenuItem::linkToCrud('Servicio', 'fas fa-list', Servicio::class);

        yield MenuItem::linkToCrud('Departamento', 'fas fa-list', Departamento::class);

        yield MenuItem::linkToCrud('Contactos', 'fas fa-list', Contacto::class);

        yield MenuItem::linkToCrud('Tarjetas de crédito', 'fas fa-list', Tarjeta::class);

        yield MenuItem::linkToCrud('Slider', 'fas fa-list', Slider::class);

        yield MenuItem::linkToCrud('Imágenes', 'fas fa-list', Archivo::class);

        yield MenuItem::linkToCrud('Configuración', 'fas fa-list', Configuracion::class);
    }

    public function configureCrud(): Crud {
        return Crud::new()->setDefaultSort(['id' => 'desc']);
    }

    // public function configureUserMenu(UserInterface $user): UserMenu
    // {
    //     return parent::configureUserMenu($user)->addMenuItems([
    //         // MenuItem::linkToRoute('My Profile', 'fa fa-id-card', '...', ['...' => '...']),
    //         // MenuItem::linkToRoute('Settings', 'fa fa-user-cog', '...', ['...' => '...']),
    //         MenuItem::section(),
    //         MenuItem::linkToLogout('Logout', 'fa fa-sign-out'),
    //     ]);
    // }
}
