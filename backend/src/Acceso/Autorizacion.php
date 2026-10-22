<?php

declare(strict_types=1);

namespace App\Acceso;

use App\Acceso\Catalogo\Catalogo;
use App\Entity\Asignacion;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Punto único de decisión de accesos (ADR-039): qué capacidades tiene un
 * usuario y dónde, desde sus asignaciones vigentes. Se carga una vez por
 * petición y usuario (una consulta) y se olvida al terminar
 * (`ResetInterface`): en el worker de FrankenPHP un cambio rige desde la
 * petición siguiente, sin caché que invalidar.
 *
 * Un usuario de agencia ejerce cada asignación sobre su agencia y las
 * empresas que esta vende, no sobre el alcance guardado. Un usuario
 * desactivado no tiene nada.
 */
final class Autorizacion implements ResetInterface
{
    /** @var array<int, PermisosUsuario> */
    private array $cache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Catalogo $catalogo,
        private readonly Security $security,
    ) {}

    public function de(Usuario $usuario): PermisosUsuario
    {
        return $this->cache[(int) $usuario->getId()] ??= $this->cargar($usuario);
    }

    public function actual(): ?Usuario
    {
        $usuario = $this->security->getUser();

        return $usuario instanceof Usuario ? $usuario : null;
    }

    /** Del usuario con sesión; sin sesión, nada. */
    public function permisos(): PermisosUsuario
    {
        $usuario = $this->actual();

        return $usuario === null ? PermisosUsuario::ninguno() : $this->de($usuario);
    }

    public function puede(string|Capacidad $capacidad, ?Ubicacion $ubicacion = null): bool
    {
        return $this->permisos()->puede(self::codigo($capacidad), $ubicacion);
    }

    public function alcance(string|Capacidad $capacidad): Alcance
    {
        return $this->permisos()->alcance(self::codigo($capacidad));
    }

    /** @throws AccessDeniedException */
    public function exigir(string|Capacidad $capacidad, ?Ubicacion $ubicacion = null, ?string $mensaje = null): void
    {
        if (!$this->puede($capacidad, $ubicacion)) {
            throw new AccessDeniedException($mensaje ?? self::mensaje($capacidad, $ubicacion));
        }
    }

    public static function mensaje(string|Capacidad $capacidad, ?Ubicacion $ubicacion = null): string
    {
        $nombre = $capacidad instanceof Capacidad ? $capacidad->nombre() : (Capacidad::tryFrom($capacidad)?->nombre() ?? $capacidad);

        return $ubicacion === null
            ? sprintf('No tiene permiso para: %s.', lcfirst($nombre))
            : sprintf('Fuera de su alcance: no puede «%s» en esa empresa o estación.', lcfirst($nombre));
    }

    /** Olvida lo calculado (tras cambiar asignaciones o roles en la misma petición). */
    public function olvidar(?int $usuarioId = null): void
    {
        if ($usuarioId === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$usuarioId]);
        }
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    /**
     * Los de todos los usuarios activos con alguna asignación, en una
     * consulta (para "¿quién puede…?").
     *
     * @return array<int, array{usuario: Usuario, permisos: PermisosUsuario}>
     */
    public function deTodos(): array
    {
        /** @var list<Asignacion> $asignaciones */
        $asignaciones = $this->em->createQueryBuilder()
            ->select('a', 'r', 'u', 'ag', 'ae')
            ->from(Asignacion::class, 'a')
            ->join('a.rol', 'r')
            ->join('a.usuario', 'u')
            ->leftJoin('u.agencia', 'ag')
            ->leftJoin('ag.empresas', 'ae')
            ->where('u.activo = true')
            ->orderBy('a.id')
            ->getQuery()
            ->getResult();
        $porUsuario = [];
        foreach ($asignaciones as $a) {
            $porUsuario[(int) $a->getUsuario()->getId()][] = $a;
        }
        $resultado = [];
        foreach ($porUsuario as $id => $lista) {
            $usuario = $lista[0]->getUsuario();
            $resultado[$id] = ['usuario' => $usuario, 'permisos' => $this->cache[$id] ??= $this->construir($usuario, $lista)];
        }

        return $resultado;
    }

    private function cargar(Usuario $usuario): PermisosUsuario
    {
        if (!$usuario->isActivo() || $usuario->getId() === null) {
            return PermisosUsuario::ninguno();
        }
        /** @var list<Asignacion> $asignaciones */
        $asignaciones = $this->em->createQueryBuilder()
            ->select('a', 'r')
            ->from(Asignacion::class, 'a')
            ->join('a.rol', 'r')
            ->where('a.usuario = :usuario')
            ->setParameter('usuario', $usuario->getId())
            ->orderBy('a.id')
            ->getQuery()
            ->getResult();

        return $this->construir($usuario, $asignaciones);
    }

    /**
     * Las vigentes hoy, resueltas a su ámbito (sin el rol base, que no
     * concede nada).
     *
     * @param list<Asignacion> $asignaciones
     */
    private function construir(Usuario $usuario, array $asignaciones): PermisosUsuario
    {
        $hoy = new \DateTimeImmutable('today');
        $agencia = $usuario->getAgencia();
        $concesiones = [];
        foreach ($asignaciones as $a) {
            $rol = $a->getRol();
            if (!$a->vigente($hoy) || $rol->esBase()) {
                continue;
            }
            $concesiones[] = new Concesion(
                (int) $a->getId(),
                (int) $rol->getId(),
                (string) $rol->getNombre(),
                $rol->isSistema(),
                $rol->getCapacidades(),
                $rol->getLimites(),
                $agencia !== null
                    ? new Ambito($agencia->empresaIds(), null, (int) $agencia->getId())
                    : new Ambito($a->getEmpresas(), $a->getEstaciones()),
            );
        }

        return new PermisosUsuario($concesiones, $this->catalogo->codigos());
    }

    private static function codigo(string|Capacidad $capacidad): string
    {
        return $capacidad instanceof Capacidad ? $capacidad->value : $capacidad;
    }
}
