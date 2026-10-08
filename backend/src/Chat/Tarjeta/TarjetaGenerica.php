<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Cualquier entidad de `App\Entity`: su etiqueta y un enlace al formulario
 * genérico. Permiso: `{entidad}.read` (EntityVoter).
 */
final class TarjetaGenerica
{
    private const NAMESPACE = "App\\Entity\\";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $auth,
    ) {}

    public function existe(string $tipo): bool
    {
        $class = self::NAMESPACE . $tipo;

        return preg_match('/^[A-Z]\w*$/', $tipo) === 1
            && class_exists($class)
            && !$this->em->getMetadataFactory()->isTransient($class)
            && !str_starts_with($tipo, "Chat");
    }

    public function puedeVer(string $tipo): bool
    {
        return $this->auth->isGranted("read", self::NAMESPACE . $tipo);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolver(string $tipo, array $ids): array
    {
        $datos = [];
        foreach ($this->em->getRepository(self::NAMESPACE . $tipo)->findBy(["id" => $ids]) as $registro) {
            $datos[$registro->getId()] = ["titulo" => self::etiqueta($registro)];
        }

        return $datos;
    }

    private static function etiqueta(object $registro): string
    {
        if (method_exists($registro, "getLabel")) {
            return (string) $registro->getLabel();
        }

        return $registro instanceof \Stringable ? (string) $registro : "#" . $registro->getId();
    }
}
