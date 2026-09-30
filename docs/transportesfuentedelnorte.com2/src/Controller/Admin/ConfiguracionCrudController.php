<?php

namespace App\Controller\Admin;

use App\Entity\Configuracion;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

class ConfiguracionCrudController extends AbstractCrudController {
    public static function getEntityFqcn(): string {
        return Configuracion::class;
    }

    public function configureFields(string $pageName): iterable {
        return [
            'compra_porciento',
            'dolar_cambio',
            BooleanField::new('desactivar_pagina'),
        ];
    }
}
