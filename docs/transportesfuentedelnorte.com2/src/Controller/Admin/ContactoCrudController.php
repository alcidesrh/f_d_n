<?php

namespace App\Controller\Admin;

use App\Entity\Contacto;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

class ContactoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Contacto::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            Field::new('id')->hideOnForm(),
            DateTimeField::new('createdAt', 'Creada')->hideOnForm(),
            'nombre',
            'email',
            'texto'
        ];
    }
}
