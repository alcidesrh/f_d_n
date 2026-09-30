<?php

namespace App\Controller\Admin;

use App\Entity\Empresa;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EmpresaCrudController extends AbstractCrudController {
    public static function getEntityFqcn(): string {
        return Empresa::class;
    }


    public function configureFields(string $pageName): iterable {
        return [
            IdField::new('id')->hideOnForm(),
            'nombre',
            'nombre_comercial',
            'alias',
            'nit',
            TextField::new('direccion', 'Dirección'),
            BooleanField::new('activa'),
        ];
    }
}
