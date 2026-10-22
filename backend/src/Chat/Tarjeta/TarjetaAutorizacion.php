<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Acceso\Autorizacion;
use App\Autorizacion\ConsultaAutorizaciones;
use App\Autorizacion\VistaAutorizacion;
use App\Entity\SolicitudAutorizacion;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Autorización (ADR-040): qué se pide, por qué regla y en qué estado está
 * ahora. La ven quien la pidió y quien aprueba donde cae.
 */
final class TarjetaAutorizacion implements Tarjeta
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
        private readonly ConsultaAutorizaciones $consulta,
        private readonly ClockInterface $reloj,
    ) {}

    public function tipo(): string
    {
        return "SolicitudAutorizacion";
    }

    /** Cada una se filtra al resolverla (la suya, o donde aprueba). */
    public function puedeVer(): bool
    {
        return $this->autorizacion->actual() !== null;
    }

    public function resolver(array $ids): array
    {
        $yo = $this->autorizacion->actual();
        if ($yo === null) {
            return [];
        }
        $ahora = $this->reloj->now();
        $datos = [];
        /** @var SolicitudAutorizacion $s */
        foreach ($this->em->getRepository(SolicitudAutorizacion::class)->findBy(["id" => $ids]) as $s) {
            if (!$this->consulta->puedeVer($s, $yo)) {
                continue;
            }
            $datos[(int) $s->getId()] = [
                "titulo" => sprintf("Autorización #%d · %s", $s->getId(), $s->estadoEn($ahora)->etiqueta()),
                "descripcion" => VistaAutorizacion::titulo($s),
                "estado" => $s->estadoEn($ahora)->value,
            ];
        }

        return $datos;
    }
}
