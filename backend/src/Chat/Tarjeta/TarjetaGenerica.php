<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Acceso\Catalogo\CatalogoRecursos;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Cualquier entidad de `App\Entity`: su etiqueta y un enlace al formulario
 * genérico. Se ve con la capacidad de lectura de su `#[Recurso]` (ADR-039):
 * ninguna si es de lectura libre; una oculta o sin publicar, nadie.
 */
final class TarjetaGenerica
{
    private const NAMESPACE = "App\\Entity\\";
    /** Secretos: ni su etiqueta se comparte. */
    private const RESERVADAS = ["ApiToken", "CredencialFel", "CredencialPago"];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $auth,
        private readonly CatalogoRecursos $recursos,
    ) {}

    public function existe(string $tipo): bool
    {
        $class = self::NAMESPACE . $tipo;

        return preg_match('/^[A-Z]\w*$/', $tipo) === 1
            && class_exists($class)
            && !$this->em->getMetadataFactory()->isTransient($class)
            && !str_starts_with($tipo, "Chat")
            && !in_array($tipo, self::RESERVADAS, true);
    }

    /**
     * Todas las entidades que se pueden adjuntar (las de `existe()`).
     *
     * @return list<string>
     */
    public function tipos(): array
    {
        $tipos = [];
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $m) {
            $tipo = substr($m->getName(), strlen(self::NAMESPACE));
            if (str_starts_with($m->getName(), self::NAMESPACE) && !$m->isMappedSuperclass && !$m->isEmbeddedClass && $this->existe($tipo)) {
                $tipos[] = $tipo;
            }
        }
        sort($tipos);

        return $tipos;
    }

    public function puedeVer(string $tipo): bool
    {
        $recurso = $this->recursos->de(self::NAMESPACE . $tipo);
        if ($recurso === null || $recurso->oculto) {
            return false;
        }

        return $recurso->lecturaLibre || ($recurso->ver !== null && $this->auth->isGranted($recurso->ver));
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolver(string $tipo, array $ids): array
    {
        $datos = [];
        $ver = $this->recursos->de(self::NAMESPACE . $tipo)?->ver;
        foreach ($this->em->getRepository(self::NAMESPACE . $tipo)->findBy(["id" => $ids]) as $registro) {
            // Cada registro, en su empresa.
            if ($ver !== null && !$this->auth->isGranted($ver, $registro)) {
                continue;
            }
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
