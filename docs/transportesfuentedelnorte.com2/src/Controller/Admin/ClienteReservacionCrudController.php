<?php

namespace App\Controller\Admin;

use App\Entity\ClienteReservacion;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ClienteReservacionCrudController extends AbstractCrudController {
    public static function getEntityFqcn(): string {
        return ClienteReservacion::class;
    }

    public function configureFields(string $pageName): iterable {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('nombreCompleto', 'Nombre')->hideOnForm(),
            TextField::new('nombre', 'Nombre')->onlyOnForms(),
            TextField::new('apellido', 'Nombre')->onlyOnForms(),
            'email',
            'telefono',
            TextField::new('direccion', 'Dirección'),
            'nit'
        ];
    }

    public function configureCrud(Crud $crud): Crud {
        return $crud
            // the labels used to refer to this entity in titles, buttons, etc.
            ->setEntityLabelInSingular('Clientes')
            ->setEntityLabelInPlural('Clientes');
    }
}
