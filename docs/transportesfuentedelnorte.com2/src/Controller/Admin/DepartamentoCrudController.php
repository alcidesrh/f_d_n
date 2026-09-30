<?php

namespace App\Controller\Admin;

use App\Entity\Departamento;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class DepartamentoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Departamento::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['prioridad' => 'DESC']);
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            'nombre',
            BooleanField::new('activo'),
            'prioridad'
        ];
    }
}
