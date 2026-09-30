<?php

namespace App\Controller\Admin;

use App\Entity\Tarjeta;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TarjetaCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tarjeta::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            AvatarField::new('imagen')->formatValue(static function ($value, ?Tarjeta $item) {
                return $item?->getImagen()?->getPath();
            })->hideOnForm(),
            AssociationField::new('imagen')->setFormTypeOption('choice_label', 'nombre')->onlyOnForms(),
            TextField::new('nombre'),
            TextField::new('codigo'),
            BooleanField::new('activo'),
            'prioridad',


        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            // the labels used to refer to this entity in titles, buttons, etc.
            ->setEntityLabelInSingular('Tarjetas')
            ->setEntityLabelInPlural('Tarjetas')
            ->setDefaultSort(['activo' => 'DESC', 'prioridad' => 'DESC']);
    }
}
