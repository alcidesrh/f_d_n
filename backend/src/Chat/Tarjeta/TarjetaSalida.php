<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Entity\Salida;
use App\Salida\DetalleSalida;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Salida: el mismo detalle que "Ver" en `/salidas` (croquis con ocupación,
 * resumen por clase y canal). Es el reporte de salida que las estaciones
 * hacían a mano, calculado desde la venta. Permiso `salida.ver`.
 */
final class TarjetaSalida implements Tarjeta
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $auth,
        private readonly DetalleSalida $detalle,
    ) {}

    public function tipo(): string
    {
        return "Salida";
    }

    public function puedeVer(): bool
    {
        return $this->auth->isGranted("salida.ver");
    }

    public function resolver(array $ids): array
    {
        $datos = [];
        foreach ($this->em->getRepository(Salida::class)->findBy(["id" => $ids]) as $salida) {
            $d = $this->detalle->de($salida);
            $datos[(int) $salida->getId()] = [
                "titulo" => $d["ruta"]["nombre"] ?: sprintf("%s → %s", $d["ruta"]["origen"]["nombre"] ?? "?", $d["ruta"]["destino"]["nombre"] ?? "?"),
                ...$d,
            ];
        }

        return $datos;
    }
}
