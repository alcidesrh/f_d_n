<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Acceso\Catalogo\CatalogoRecursos;
use App\Acceso\PermisosUsuario;
use App\Entity\VueRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Qué pantallas del frontend puede abrir un usuario (ADR-049): las rutas
 * sincronizadas (`VueRoute`) con lo que exige cada una, buscadas por nombre
 * o por path. Lo usan los menús (`UserMenus`) y la administración
 * (`App\Administracion\PanelAdministracion`) para no mandar lo que el
 * usuario no puede abrir. Es presentación: lo que la pantalla hace, la API
 * lo exige igual.
 *
 * Lo que no se conoce no se esconde (el frontend lo pinta inerte o lo
 * abre, y su router decide): una ruta que no está en el catálogo, un path
 * que no es de ninguna ruta (p. ej. `/pagina`, la página pública) o una
 * dirección externa.
 *
 * Las rutas se cargan una vez por petición (`ResetInterface`).
 */
final class Pantallas implements ResetInterface
{
    /** @var array<string, Pantalla>|null por nombre de ruta */
    private ?array $porNombre = null;

    /** @var list<array{pantalla: Pantalla, patron: PatronRuta}>|null de la más específica a la menos */
    private ?array $patrones = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CatalogoRecursos $recursos,
    ) {}

    /** La ruta ya cargada (el destino de un ítem de menú), sin parámetros. */
    public function abre(PermisosUsuario $permisos, VueRoute $ruta): bool
    {
        return $this->admite($permisos, Pantalla::de($ruta), []);
    }

    /** @param array<string, string> $parametros */
    public function abreRuta(PermisosUsuario $permisos, string $nombre, array $parametros = []): bool
    {
        $pantalla = $this->porNombre()[$nombre] ?? null;

        return $pantalla === null || $this->admite($permisos, $pantalla, $parametros);
    }

    /** Un path interno (`/lista/bus?x=1`) o una dirección externa (`https://…`, siempre). */
    public function abrePath(PermisosUsuario $permisos, string $path): bool
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return true;
        }
        $encontrada = $this->porPath($path);

        return $encontrada === null || $this->admite($permisos, ...$encontrada);
    }

    /**
     * La pantalla de un path interno y sus parámetros; null si no es de
     * ninguna ruta.
     *
     * @return array{0: Pantalla, 1: array<string, string>}|null
     */
    public function porPath(string $path): ?array
    {
        $path = PatronRuta::sinConsulta($path);
        foreach ($this->patrones() as ['pantalla' => $pantalla, 'patron' => $patron]) {
            $parametros = $patron->comparar($path);
            if ($parametros !== null) {
                return [$pantalla, $parametros];
            }
        }

        return null;
    }

    public function reset(): void
    {
        $this->porNombre = null;
        $this->patrones = null;
    }

    /** @param array<string, string> $parametros */
    private function admite(PermisosUsuario $permisos, Pantalla $pantalla, array $parametros): bool
    {
        return $pantalla->admite($permisos, $this->recursos, $parametros);
    }

    /** @return array<string, Pantalla> */
    private function porNombre(): array
    {
        if ($this->porNombre !== null) {
            return $this->porNombre;
        }
        /** @var list<VueRoute> $rutas */
        $rutas = $this->em->getRepository(VueRoute::class)->findAll();
        $porId = [];
        foreach ($rutas as $ruta) {
            $porId[(int) $ruta->getId()] = $ruta;
        }
        $this->porNombre = [];
        foreach ($rutas as $ruta) {
            $nombre = (string) $ruta->getVueRouteName();
            if ($nombre !== '') {
                $this->porNombre[$nombre] = Pantalla::de($ruta, self::pathCompleto($ruta, $porId));
            }
        }

        return $this->porNombre;
    }

    /** @return list<array{pantalla: Pantalla, patron: PatronRuta}> */
    private function patrones(): array
    {
        if ($this->patrones !== null) {
            return $this->patrones;
        }
        $patrones = [];
        foreach ($this->porNombre() as $pantalla) {
            if ($pantalla->path !== null && $pantalla->path !== '') {
                $patron = PatronRuta::de($pantalla->path);
                if (!$patron->comodin) {
                    $patrones[] = ['pantalla' => $pantalla, 'patron' => $patron];
                }
            }
        }
        usort($patrones, static fn(array $a, array $b) => $b['patron']->puntaje <=> $a['patron']->puntaje);

        return $this->patrones = $patrones;
    }

    /**
     * El path de una ruta hija es relativo al de su padre (salvo que empiece
     * con «/»), como en vue-router.
     *
     * @param array<int, VueRoute> $porId
     */
    private static function pathCompleto(VueRoute $ruta, array $porId, int $profundidad = 0): ?string
    {
        $path = $ruta->getPath();
        $padre = $ruta->getVueRoute();
        if ($path === null || str_starts_with($path, '/') || $padre === null || $profundidad > 20) {
            return $path;
        }
        $base = self::pathCompleto($porId[(int) $padre->getId()] ?? $padre, $porId, $profundidad + 1) ?? '';

        return rtrim($base, '/') . '/' . $path;
    }
}
