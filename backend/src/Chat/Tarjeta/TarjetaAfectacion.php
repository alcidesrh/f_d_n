<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Acceso\Autorizacion;
use App\Afectacion\ConsultaAfectaciones;
use App\Afectacion\ReglasAfectacion;
use App\Entity\Afectacion;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Afectación (ADR-070): qué pasó, dónde y en qué estado está ahora. Va en
 * los avisos del centinela; la ven quienes la pueden ver en `/afectaciones`.
 */
final class TarjetaAfectacion implements Tarjeta
{
    public const TIPO = "Afectacion";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
        private readonly ConsultaAfectaciones $consulta,
    ) {}

    public function tipo(): string
    {
        return self::TIPO;
    }

    /** Cada una se filtra al resolverla. */
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
        $datos = [];
        /** @var Afectacion $a */
        foreach ($this->em->getRepository(Afectacion::class)->findBy(["id" => $ids]) as $a) {
            if (!$this->consulta->puedeVer($a, $yo)) {
                continue;
            }
            $datos[(int) $a->getId()] = [
                "titulo" => sprintf("Afectación #%d · %s", $a->getId(), $a->getEstado()->etiqueta()),
                "descripcion" => sprintf("%s · %s · %s", $a->getTipo()->getNombre(), ReglasAfectacion::lugar($a), $a->getTipo()->getPrioridad()->etiqueta()),
                "estado" => $a->getEstado()->value,
                "prioridad" => $a->getTipo()->getPrioridad()->value,
                "url" => "/afectaciones?id=" . $a->getId(),
            ];
        }

        return $datos;
    }
}
