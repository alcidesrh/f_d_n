<?php

declare(strict_types=1);

namespace App\Administracion;

use App\Acceso\Autorizacion;
use App\Acceso\PermisosUsuario;
use App\Entity\EnlaceAdministracion;
use App\Entity\Role;
use App\Entity\SeccionAdministracion;
use App\Entity\Usuario;
use App\Navigation\Audiencia;
use App\Navigation\Pantallas;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cajas y enlaces de `/administracion`: lo que ve cada usuario y su
 * configuración (crear, editar, ocultar, ordenar, mover, eliminar y a qué
 * roles se le muestra cada cosa). Cada operación de configuración responde
 * la configuración completa: el frontend reemplaza su copia y nunca queda
 * desfasado.
 */
final class PanelAdministracion
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
        private readonly Pantallas $pantallas,
    ) {}

    /**
     * Lo que ve el usuario (ADR-049): las cajas activas de su audiencia con
     * sus enlaces activos de su audiencia que llevan a algo que puede abrir;
     * el "Agregar nuevo", solo si también puede abrirlo. Una caja sin
     * enlaces a la vista no se envía.
     *
     * @return list<array<string, mixed>>
     */
    public function paraUsuario(Usuario $usuario): array
    {
        $permisos = $this->autorizacion->de($usuario);

        $resultado = [];
        foreach ($this->conAudiencia($this->secciones()) as $seccion) {
            if (!$seccion->isActiva() || !Audiencia::incluye($permisos, $seccion->getRoles())) {
                continue;
            }
            $enlaces = array_values(array_filter(
                [...$seccion->getEnlaces()],
                fn(EnlaceAdministracion $e) => $e->isActivo() && Audiencia::incluye($permisos, $e->getRoles()) && $this->abre($permisos, $e->getAbrir()),
            ));
            if ($enlaces === []) {
                continue;
            }
            $vista = self::seccion($seccion, $enlaces, false);
            foreach ($enlaces as $i => $enlace) {
                if ($enlace->getAgregar() !== null && !$this->abre($permisos, $enlace->getAgregar())) {
                    $vista["enlaces"][$i]["agregar"] = null;
                }
            }
            $resultado[] = $vista;
        }

        return $resultado;
    }

    /** @return list<array<string, mixed>> todo, con su acceso */
    public function configuracion(): array
    {
        return array_map(static fn(SeccionAdministracion $s) => self::seccion($s, [...$s->getEnlaces()], true), $this->conAudiencia($this->secciones()));
    }

    public function crearSeccion(array $datos): array
    {
        $seccion = new SeccionAdministracion(Datos::texto($datos, "titulo", "el título"), Datos::icono($datos), Datos::color($datos));
        $seccion->setPosicion(count($this->secciones()));
        $this->aplicarSeccion($seccion, $datos);
        $this->em->persist($seccion);
        $this->em->flush();

        return $this->configuracion();
    }

    public function guardarSeccion(int $id, array $datos): array
    {
        $seccion = $this->seccion_($id);
        $seccion->describir(Datos::texto($datos, "titulo", "el título"), Datos::icono($datos), Datos::color($datos));
        $this->aplicarSeccion($seccion, $datos);
        $this->em->flush();

        return $this->configuracion();
    }

    public function eliminarSeccion(int $id): array
    {
        $this->em->remove($this->seccion_($id));
        $this->em->flush();
        $this->renumerar();

        return $this->configuracion();
    }

    public function crearEnlace(int $seccionId, array $datos): array
    {
        $seccion = $this->seccion_($seccionId);
        $enlace = new EnlaceAdministracion($seccion, Datos::texto($datos, "etiqueta", "el texto"), Datos::icono($datos), $this->abrir($datos));
        $enlace->setPosicion($seccion->getEnlaces()->count());
        $seccion->getEnlaces()->add($enlace);
        $this->aplicarEnlace($enlace, $datos);
        $this->em->persist($enlace);
        $this->em->flush();

        return $this->configuracion();
    }

    public function guardarEnlace(int $id, array $datos): array
    {
        $enlace = $this->enlace_($id);
        $enlace->setAbrir($this->abrir($datos));
        $this->aplicarEnlace($enlace, $datos);
        $this->em->flush();

        return $this->configuracion();
    }

    public function eliminarEnlace(int $id): array
    {
        $enlace = $this->enlace_($id);
        $enlace->getSeccion()->getEnlaces()->removeElement($enlace);
        $this->em->remove($enlace);
        $this->em->flush();
        $this->renumerar();

        return $this->configuracion();
    }

    /** `{ secciones: [{ id, enlaces: [id] }] }`: orden de las cajas y de sus enlaces (y en qué caja va cada uno). */
    public function ordenar(array $datos): array
    {
        $secciones = [];
        $enlaces = [];
        foreach ($this->secciones() as $s) {
            $secciones[$s->getId()] = $s;
            foreach ($s->getEnlaces() as $e) {
                $enlaces[$e->getId()] = $e;
            }
        }
        $orden = Datos::orden($datos, array_keys($secciones), array_keys($enlaces));

        foreach ($orden as $i => $item) {
            $seccion = $secciones[$item["id"]];
            $seccion->setPosicion($i);
            foreach ($item["enlaces"] as $j => $enlaceId) {
                $enlace = $enlaces[$enlaceId];
                if ($enlace->getSeccion() !== $seccion) {
                    $enlace->getSeccion()->getEnlaces()->removeElement($enlace);
                    $enlace->setSeccion($seccion);
                    $seccion->getEnlaces()->add($enlace);
                }
                $enlace->setPosicion($j);
            }
        }
        $this->em->flush();
        $this->em->clear();

        return $this->configuracion();
    }

    // ── Internos ────────────────────────────────────────────────────────────

    /** @return list<SeccionAdministracion> */
    private function secciones(): array
    {
        return $this->em->createQueryBuilder()
            ->select("s", "e")
            ->from(SeccionAdministracion::class, "s")
            ->leftJoin("s.enlaces", "e")
            ->orderBy("s.posicion")
            ->addOrderBy("s.id")
            ->addOrderBy("e.posicion")
            ->addOrderBy("e.id")
            ->getQuery()
            ->getResult();
    }

    /**
     * Los roles de las cajas y sus enlaces en dos consultas, no una por cada
     * caja y cada enlace.
     *
     * @param list<SeccionAdministracion> $secciones
     *
     * @return list<SeccionAdministracion>
     */
    private function conAudiencia(array $secciones): array
    {
        $enlaces = array_merge([], ...array_map(static fn(SeccionAdministracion $s) => [...$s->getEnlaces()], $secciones));
        foreach ([[SeccionAdministracion::class, $secciones], [EnlaceAdministracion::class, $enlaces]] as [$clase, $registros]) {
            if ($registros === []) {
                continue;
            }
            $this->em->createQueryBuilder()
                ->select("x", "y")
                ->from($clase, "x")
                ->leftJoin("x.roles", "y")
                ->where("x IN (:registros)")
                ->setParameter("registros", $registros)
                ->getQuery()
                ->getResult();
        }

        return $secciones;
    }

    /** Lleva a algo que el usuario puede abrir (ADR-049). */
    private function abre(PermisosUsuario $permisos, Destino $destino): bool
    {
        return $destino->ruta !== null
            ? $this->pantallas->abreRuta($permisos, $destino->ruta, $destino->parametros)
            : $this->pantallas->abrePath($permisos, (string) $destino->path);
    }

    private function seccion_(int $id): SeccionAdministracion
    {
        return $this->em->find(SeccionAdministracion::class, $id) ?? throw new AdministracionRechazada("La caja ya no existe.", "seccion_inexistente", 404);
    }

    private function enlace_(int $id): EnlaceAdministracion
    {
        return $this->em->find(EnlaceAdministracion::class, $id) ?? throw new AdministracionRechazada("El enlace ya no existe.", "enlace_inexistente", 404);
    }

    private function abrir(array $datos): Destino
    {
        return Destino::desde($datos["abrir"] ?? null) ?? throw new AdministracionRechazada("Elegí una ruta o escribí un path para el enlace.", "destino_requerido");
    }

    private function aplicarSeccion(SeccionAdministracion $seccion, array $datos): void
    {
        $seccion->setActiva(Datos::booleano($datos, "activa", $seccion->isActiva()));
        $this->aplicarAudiencia($seccion, $datos);
    }

    private function aplicarEnlace(EnlaceAdministracion $enlace, array $datos): void
    {
        $enlace->describir(Datos::texto($datos, "etiqueta", "el texto"), Datos::icono($datos), Datos::claves($datos));
        $enlace->setAgregar(Destino::desde($datos["agregar"] ?? null));
        $enlace->setActivo(Datos::booleano($datos, "activo", $enlace->isActivo()));
        $this->aplicarAudiencia($enlace, $datos);
    }

    private function aplicarAudiencia(SeccionAdministracion|EnlaceAdministracion $registro, array $datos): void
    {
        $ids = Datos::audiencia($datos);
        if ($ids === null) {
            return;
        }
        $roles = $ids ? $this->em->getRepository(Role::class)->findBy(["id" => $ids]) : [];
        if (count($roles) !== count($ids)) {
            throw new AdministracionRechazada("Alguno de los roles elegidos ya no existe.", "audiencia_invalida");
        }
        $registro->mostrarA($roles);
    }

    /** Posiciones seguidas (0, 1, 2…) tras eliminar. */
    private function renumerar(): void
    {
        foreach ($this->secciones() as $i => $s) {
            $s->setPosicion($i);
            foreach ([...$s->getEnlaces()] as $j => $e) {
                $e->setPosicion($j);
            }
        }
        $this->em->flush();
    }

    /**
     * @param list<EnlaceAdministracion> $enlaces
     * @return array<string, mixed>
     */
    private static function seccion(SeccionAdministracion $s, array $enlaces, bool $conAudiencia): array
    {
        return [
            "id" => $s->getId(),
            "titulo" => $s->getTitulo(),
            "icono" => $s->getIcono(),
            "color" => $s->getColor(),
            "activa" => $s->isActiva(),
        ] + ($conAudiencia ? ["audiencia" => self::audiencia($s)] : []) + [
            "enlaces" => array_map(static fn(EnlaceAdministracion $e) => [
                "id" => $e->getId(),
                "etiqueta" => $e->getEtiqueta(),
                "icono" => $e->getIcono(),
                "claves" => $e->getClaves(),
                "abrir" => $e->getAbrir()->toArray(),
                "agregar" => $e->getAgregar()?->toArray(),
                "activo" => $e->isActivo(),
            ] + ($conAudiencia ? ["audiencia" => self::audiencia($e)] : []), $enlaces),
        ];
    }

    /** @return list<array{id: int, label: string}> los roles a los que se muestra; vacía, a todos */
    private static function audiencia(SeccionAdministracion|EnlaceAdministracion $r): array
    {
        return array_values(array_map(static fn(Role $x) => ["id" => (int) $x->getId(), "label" => (string) $x->getNombre()], [...$r->getRoles()]));
    }
}
