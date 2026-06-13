<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Permiso;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PermisoFixtures extends Fixture
{
    public const REF_PREFIX = 'permiso-';

    public function load(ObjectManager $manager): void
    {
        $permisos = [
            'Gestion Usuarios' => ['usuario.listar', 'usuario.crear', 'usuario.editar', 'usuario.eliminar', 'usuario.ver'],
            'Gestion Roles'    => ['rol.listar', 'rol.crear', 'rol.editar', 'rol.eliminar', 'rol.ver'],
            'Gestion Permisos' => ['permiso.listar', 'permiso.crear', 'permiso.editar', 'permiso.eliminar', 'permiso.ver'],
            'Gestion Acciones' => ['action.listar', 'action.crear', 'action.editar', 'action.eliminar', 'action.ver'],
            'Gestion Boletos'  => ['boleto.listar', 'boleto.crear', 'boleto.editar', 'boleto.eliminar', 'boleto.anular', 'boleto.ver'],
            'Gestion Rutas'    => ['ruta.listar', 'ruta.crear', 'ruta.editar', 'ruta.eliminar', 'ruta.ver'],
        ];

        foreach ($permisos as $nombre => $actionCodes) {
            $permiso = new Permiso();
            $permiso->setNombre($nombre);
            $permiso->setNota("Permiso para {$nombre}");

            foreach ($actionCodes as $code) {
                $action = $this->getReference(ActionFixtures::REF_PREFIX . $code, \App\Entity\Action::class);
                $permiso->addAction($action);
            }

            $manager->persist($permiso);
            $this->addReference(self::REF_PREFIX . $nombre, $permiso);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ActionFixtures::class,
        ];
    }
}
