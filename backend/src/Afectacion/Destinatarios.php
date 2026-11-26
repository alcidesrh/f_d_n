<?php

declare(strict_types=1);

namespace App\Afectacion;

use App\Acceso\Autorizacion;
use App\Acceso\Catalogo\Catalogo;
use App\Acceso\PermisosUsuario;
use App\Entity\Afectacion;
use App\Entity\Usuario;

/**
 * A quién avisa el centinela (ADR-070): los usuarios activos que tienen
 * alguna de las capacidades donde cae la afectación, cada una acotada por
 * sus dimensiones (`ReglasAfectacion::ubicacion`). Los permisos de todos se
 * cargan una vez por corrida (`Autorizacion::deTodos`).
 */
final class Destinatarios
{
    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly Catalogo $catalogo,
    ) {}

    /**
     * @param list<string> $capacidades
     *
     * @return list<Usuario>
     */
    public function de(Afectacion $a, array $capacidades): array
    {
        $dimensiones = $this->dimensiones($capacidades);
        $empresa = $a->getEmpresa()?->getId();
        $estacion = $a->getEstacion()?->getId();
        $usuarios = [];
        foreach ($this->autorizacion->deTodos() as ["usuario" => $u, "permisos" => $p]) {
            if (ReglasAfectacion::alcanza($p, $capacidades, $dimensiones, $empresa, $estacion)) {
                $usuarios[] = $u;
            }
        }

        return $usuarios;
    }

    /** El mismo criterio para un usuario. @param list<string> $capacidades */
    public function alcanza(PermisosUsuario $permisos, Afectacion $a, array $capacidades): bool
    {
        return ReglasAfectacion::alcanza($permisos, $capacidades, $this->dimensiones($capacidades), $a->getEmpresa()?->getId(), $a->getEstacion()?->getId());
    }

    /**
     * @param list<string> $capacidades
     *
     * @return array<string, list<string>> capacidad → sus dimensiones
     */
    public function dimensiones(array $capacidades): array
    {
        $definiciones = $this->catalogo->definiciones();
        $dimensiones = [];
        foreach ($capacidades as $c) {
            $dimensiones[$c] = $definiciones[$c]->dimensiones ?? [];
        }

        return $dimensiones;
    }
}
