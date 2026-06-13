<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Action;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ActionFixtures extends Fixture
{
    public const REF_PREFIX = 'action-';

    public function load(ObjectManager $manager): void
    {
        $actions = [
            ['codigo' => 'usuario.listar',  'recurso' => 'Usuario',  'operacion' => 'list',   'grupo' => 'Usuarios', 'nombre' => 'Listar usuarios'],
            ['codigo' => 'usuario.crear',   'recurso' => 'Usuario',  'operacion' => 'create', 'grupo' => 'Usuarios', 'nombre' => 'Crear usuarios'],
            ['codigo' => 'usuario.editar',  'recurso' => 'Usuario',  'operacion' => 'update', 'grupo' => 'Usuarios', 'nombre' => 'Editar usuarios'],
            ['codigo' => 'usuario.eliminar','recurso' => 'Usuario',  'operacion' => 'delete', 'grupo' => 'Usuarios', 'nombre' => 'Eliminar usuarios'],
            ['codigo' => 'usuario.ver',     'recurso' => 'Usuario',  'operacion' => 'read',   'grupo' => 'Usuarios', 'nombre' => 'Ver usuario'],
            ['codigo' => 'rol.listar',  'recurso' => 'Role',  'operacion' => 'list',   'grupo' => 'Roles', 'nombre' => 'Listar roles'],
            ['codigo' => 'rol.crear',   'recurso' => 'Role',  'operacion' => 'create', 'grupo' => 'Roles', 'nombre' => 'Crear roles'],
            ['codigo' => 'rol.editar',  'recurso' => 'Role',  'operacion' => 'update', 'grupo' => 'Roles', 'nombre' => 'Editar roles'],
            ['codigo' => 'rol.eliminar','recurso' => 'Role',  'operacion' => 'delete', 'grupo' => 'Roles', 'nombre' => 'Eliminar roles'],
            ['codigo' => 'rol.ver',     'recurso' => 'Role',  'operacion' => 'read',   'grupo' => 'Roles', 'nombre' => 'Ver rol'],
            ['codigo' => 'permiso.listar',  'recurso' => 'Permiso',  'operacion' => 'list',   'grupo' => 'Permisos', 'nombre' => 'Listar permisos'],
            ['codigo' => 'permiso.crear',   'recurso' => 'Permiso',  'operacion' => 'create', 'grupo' => 'Permisos', 'nombre' => 'Crear permisos'],
            ['codigo' => 'permiso.editar',  'recurso' => 'Permiso',  'operacion' => 'update', 'grupo' => 'Permisos', 'nombre' => 'Editar permisos'],
            ['codigo' => 'permiso.eliminar','recurso' => 'Permiso',  'operacion' => 'delete', 'grupo' => 'Permisos', 'nombre' => 'Eliminar permisos'],
            ['codigo' => 'permiso.ver',     'recurso' => 'Permiso',  'operacion' => 'read',   'grupo' => 'Permisos', 'nombre' => 'Ver permiso'],
            ['codigo' => 'action.listar',  'recurso' => 'Action',  'operacion' => 'list',   'grupo' => 'Acciones', 'nombre' => 'Listar acciones'],
            ['codigo' => 'action.crear',   'recurso' => 'Action',  'operacion' => 'create', 'grupo' => 'Acciones', 'nombre' => 'Crear acciones'],
            ['codigo' => 'action.editar',  'recurso' => 'Action',  'operacion' => 'update', 'grupo' => 'Acciones', 'nombre' => 'Editar acciones'],
            ['codigo' => 'action.eliminar','recurso' => 'Action',  'operacion' => 'delete', 'grupo' => 'Acciones', 'nombre' => 'Eliminar acciones'],
            ['codigo' => 'action.ver',     'recurso' => 'Action',  'operacion' => 'read',   'grupo' => 'Acciones', 'nombre' => 'Ver accion'],
            ['codigo' => 'boleto.listar',  'recurso' => 'Boleto',  'operacion' => 'list',   'grupo' => 'Boletos', 'nombre' => 'Listar boletos'],
            ['codigo' => 'boleto.crear',   'recurso' => 'Boleto',  'operacion' => 'create', 'grupo' => 'Boletos', 'nombre' => 'Crear boletos'],
            ['codigo' => 'boleto.editar',  'recurso' => 'Boleto',  'operacion' => 'update', 'grupo' => 'Boletos', 'nombre' => 'Editar boletos'],
            ['codigo' => 'boleto.eliminar','recurso' => 'Boleto',  'operacion' => 'delete', 'grupo' => 'Boletos', 'nombre' => 'Eliminar boletos'],
            ['codigo' => 'boleto.anular',  'recurso' => 'Boleto',  'operacion' => 'void',   'grupo' => 'Boletos', 'nombre' => 'Anular boletos'],
            ['codigo' => 'boleto.ver',     'recurso' => 'Boleto',  'operacion' => 'read',   'grupo' => 'Boletos', 'nombre' => 'Ver boleto'],
            ['codigo' => 'ruta.listar',  'recurso' => 'Ruta',  'operacion' => 'list',   'grupo' => 'Rutas', 'nombre' => 'Listar rutas'],
            ['codigo' => 'ruta.crear',   'recurso' => 'Ruta',  'operacion' => 'create', 'grupo' => 'Rutas', 'nombre' => 'Crear rutas'],
            ['codigo' => 'ruta.editar',  'recurso' => 'Ruta',  'operacion' => 'update', 'grupo' => 'Rutas', 'nombre' => 'Editar rutas'],
            ['codigo' => 'ruta.eliminar','recurso' => 'Ruta',  'operacion' => 'delete', 'grupo' => 'Rutas', 'nombre' => 'Eliminar rutas'],
            ['codigo' => 'ruta.ver',     'recurso' => 'Ruta',  'operacion' => 'read',   'grupo' => 'Rutas', 'nombre' => 'Ver ruta'],
        ];

        foreach ($actions as $data) {
            $action = new Action();
            $action->setCodigo($data['codigo']);
            $action->setRecurso($data['recurso']);
            $action->setOperacion($data['operacion']);
            $action->setGrupo($data['grupo']);
            $action->setNombre($data['nombre']);
            $manager->persist($action);
            $this->addReference(self::REF_PREFIX . $data['codigo'], $action);
        }

        $manager->flush();
    }
}
