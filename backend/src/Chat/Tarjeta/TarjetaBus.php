<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Croquis\CroquisBus;
use App\Entity\Bus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** Bus: código, empresa, clase, asientos y su croquis. */
final class TarjetaBus implements Tarjeta
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $auth,
        private readonly CroquisBus $croquis,
    ) {}

    public function tipo(): string
    {
        return "Bus";
    }

    public function puedeVer(): bool
    {
        return $this->auth->isGranted("read", Bus::class);
    }

    public function resolver(array $ids): array
    {
        $datos = [];
        /** @var Bus $bus */
        foreach ($this->em->getRepository(Bus::class)->findBy(["id" => $ids]) as $bus) {
            $croquis = $this->croquis->leer($bus)["elementos"];
            $empresa = $bus->getEmpresa();
            $datos[(int) $bus->getId()] = [
                "titulo" => sprintf("Bus %s", $bus->getCodigo()),
                "codigo" => $bus->getCodigo(),
                "empresa" => $empresa ? ($empresa->getAlias() ?? $empresa->getNombre()) : null,
                "clase" => $bus->getClase()?->getNombre(),
                "asientos" => count(array_filter($croquis, static fn(array $e) => $e["tipo"] === "asiento")),
                "croquis" => $croquis,
            ];
        }

        return $datos;
    }
}
