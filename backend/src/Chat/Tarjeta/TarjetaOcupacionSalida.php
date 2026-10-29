<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Acceso\Ubicador;
use App\Chat\Predeterminado\Contenidos;
use App\Chat\Predeterminado\OcupacionAlIniciar;
use App\Entity\Salida;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Ocupación de una salida por vendedor (ADR-045, "Salida iniciada"): la
 * tabla del PDF, calculada al leer con lo vendido hasta ahora. El id es el de
 * la salida. Llega solo en los avisos del sistema. Permiso `salida.ver`.
 */
final class TarjetaOcupacionSalida implements Tarjeta
{
    public const TIPO = "OcupacionSalida";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $auth,
        private readonly Contenidos $contenidos,
    ) {}

    public function tipo(): string
    {
        return self::TIPO;
    }

    public function puedeVer(): bool
    {
        return $this->auth->isGranted("salida.ver");
    }

    public function resolver(array $ids): array
    {
        $datos = [];
        foreach ($this->em->getRepository(Salida::class)->findBy(["id" => $ids]) as $salida) {
            if (!$this->auth->isGranted("salida.ver", Ubicador::salida($salida))) {
                continue;
            }
            $o = $this->contenidos->ocupacion($salida);
            $ficha = Contenidos::ficha($salida);
            $datos[(int) $salida->getId()] = [
                "titulo" => $ficha["ruta"],
                "salida" => $salida->getFecha()?->format(\DateTimeInterface::ATOM),
                "bus" => $ficha["bus"],
                "empresa" => $ficha["empresa"],
                "asientos" => $o->asientos,
                "ocupados" => $o->ocupados,
                "conIntermedias" => $o->conIntermedias,
                "libres" => $o->libres(),
                "porCanal" => $o->porCanal,
                "columnas" => OcupacionAlIniciar::COLUMNAS,
                "filas" => $o->filas,
                "totales" => $o->totales,
            ];
        }

        return $datos;
    }
}
