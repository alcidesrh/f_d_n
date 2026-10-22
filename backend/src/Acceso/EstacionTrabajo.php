<?php

declare(strict_types=1);

namespace App\Acceso;

use App\Entity\Estacion;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Estación de trabajo (ADR-039): `Usuario.estacion`, donde opera ahora. La
 * fija el administrador y el usuario la cambia entre las estaciones de sus
 * asignaciones (con "todas", cualquiera activa). Un usuario de agencia no
 * tiene: opera desde su agencia.
 */
final class EstacionTrabajo
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
    ) {}

    /** @return list<int>|null las que puede elegir; null = cualquiera */
    public function permitidas(Usuario $usuario): ?array
    {
        if ($usuario->getAgencia() !== null) {
            return [];
        }
        $general = $this->autorizacion->de($usuario)->general();

        return $general->vacio() ? [] : $general->estaciones();
    }

    /** La suya, si está dentro de su alcance; si no tiene y su alcance es de una sola, esa. */
    public function actual(Usuario $usuario): ?Estacion
    {
        $estacion = $usuario->getEstacion();
        $permitidas = $this->permitidas($usuario);
        if ($estacion !== null && ($permitidas === null || in_array((int) $estacion->getId(), $permitidas, true))) {
            return $estacion;
        }

        return $permitidas !== null && count($permitidas) === 1 ? $this->em->find(Estacion::class, $permitidas[0]) : null;
    }

    /**
     * Si su estación no está en su alcance y el alcance tiene una sola, la
     * deja fijada (los servicios leen `Usuario.estacion`).
     */
    public function asegurar(Usuario $usuario): void
    {
        $actual = $this->actual($usuario);
        if ($actual !== null && $actual !== $usuario->getEstacion()) {
            $this->em->find(Usuario::class, $usuario->getId())?->setEstacion($actual);
            $this->em->flush();
        }
    }

    /** @return list<array{id: int, nombre: string, departamento: string|null}> */
    public function opciones(Usuario $usuario): array
    {
        $permitidas = $this->permitidas($usuario);
        if ($permitidas === []) {
            return [];
        }
        $criterio = ['activo' => true];
        if ($permitidas !== null) {
            $criterio['id'] = $permitidas;
        }

        return array_map(
            static fn(Estacion $e) => ['id' => (int) $e->getId(), 'nombre' => (string) $e->getNombre(), 'departamento' => $e->getDepartamento()],
            $this->em->getRepository(Estacion::class)->findBy($criterio, ['nombre' => 'ASC']),
        );
    }

    /** @throws \DomainException si no es una de las suyas */
    public function elegir(Usuario $usuario, int $estacionId): Estacion
    {
        $permitidas = $this->permitidas($usuario);
        $estacion = $this->em->find(Estacion::class, $estacionId);
        if ($estacion === null || !$estacion->isActivo() || ($permitidas !== null && !in_array($estacionId, $permitidas, true))) {
            throw new \DomainException('Esa estación no está entre las de sus asignaciones.');
        }
        $this->em->find(Usuario::class, $usuario->getId())?->setEstacion($estacion);
        $this->em->flush();

        return $estacion;
    }
}
