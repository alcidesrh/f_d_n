<?php

declare(strict_types=1);

namespace App\Acceso;

use App\Acceso\Catalogo\CatalogoRecursos;
use App\Entity\BoletoVenta;
use App\Entity\Empresa;
use App\Entity\Salida;
use App\Entity\Usuario;

/**
 * Dónde cae un registro (ADR-039). `de()` resuelve las entidades de la API
 * genérica por la propiedad que declara su `#[Recurso(empresa: …)]`; los
 * estáticos son los del dominio, que cada servicio combina con la estación
 * donde opera.
 */
final class Ubicador
{
    public function __construct(
        private readonly CatalogoRecursos $recursos,
    ) {}

    public function de(object $registro): Ubicacion
    {
        $ruta = $this->recursos->de($registro)?->empresa;
        if ($ruta === null) {
            return Ubicacion::ninguna();
        }
        if ($ruta === '@id') {
            $id = method_exists($registro, 'getId') ? $registro->getId() : null;

            return Ubicacion::empresa(is_int($id) ? $id : null);
        }
        $valor = $registro;
        foreach (explode('.', $ruta) as $propiedad) {
            $getter = 'get' . ucfirst($propiedad);
            $valor = is_object($valor) && method_exists($valor, $getter) ? $valor->$getter() : null;
        }

        return Ubicacion::empresa($valor instanceof Empresa ? $valor->getId() : null);
    }

    /** Una salida: su empresa. */
    public static function salida(?Salida $salida): Ubicacion
    {
        return Ubicacion::empresa($salida?->getEmpresa()?->getId());
    }

    /**
     * Una venta: la empresa de su salida, la estación donde se vendió y su
     * agencia (un usuario de agencia solo opera las de la suya).
     */
    public static function venta(BoletoVenta $venta): Ubicacion
    {
        $boleto = $venta->getAsientos()->first() ?: null;

        return Ubicacion::empresa($boleto?->getSalida()?->getEmpresa()?->getId())
            ->conEstacion($venta->getEstacion()?->getId())
            ->conAgencia($venta->getAgencia()?->getId());
    }

    /**
     * Una operación de la empresa hecha desde donde está el usuario: su
     * estación de trabajo o, si es de agencia, su agencia.
     */
    public static function operacion(?int $empresa, Usuario $usuario, ?int $estacion = null): Ubicacion
    {
        $agencia = $usuario->getAgencia();
        $ubicacion = Ubicacion::empresa($empresa);

        return $agencia !== null
            ? $ubicacion->conAgencia($agencia->getId())
            : $ubicacion->conEstacion($estacion ?? $usuario->getEstacion()?->getId());
    }
}
