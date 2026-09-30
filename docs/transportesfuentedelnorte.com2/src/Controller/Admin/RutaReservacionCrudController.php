<?php

namespace App\Controller\Admin;

use App\Entity\RutaReservacion;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class RutaReservacionCrudController extends AbstractCrudController {
    public static function getEntityFqcn(): string {
        return RutaReservacion::class;
    }


    public function configureFields(string $pageName): iterable {
        return [
            AssociationField::new('estacion_salida'),
            AssociationField::new('estacion_llegada'),
            NumberField::new('precio'),
        ];
    }
}
