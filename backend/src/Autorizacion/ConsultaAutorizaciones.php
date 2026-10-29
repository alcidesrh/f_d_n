<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\FiltroAlcance;
use App\Acceso\Ubicacion;
use App\Entity\Enum\EstadoAutorizacion;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;

/**
 * Autorizaciones para leer (ADR-040), por vista:
 *
 * - `por-resolver`: las pendientes que el usuario puede aprobar o rechazar.
 * - `mias`: las que pidió.
 * - `todas`: el historial de las que caen en su alcance de
 *   `autorizacion.aprobar`.
 *
 * Cada fila trae lo que el usuario puede hacer con ella (`acciones`).
 */
final class ConsultaAutorizaciones
{
    public const VISTAS = ["por-resolver", "mias", "todas"];
    public const POR_PAGINA = 25;
    /** Las pendientes por resolver se filtran fila a fila: a lo sumo estas. */
    private const MAX_PENDIENTES = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{items: list<array<string, mixed>>, total: int, pagina: int, porPagina: int} */
    public function listar(Usuario $usuario, string $vista, ?EstadoAutorizacion $estado, int $pagina): array
    {
        $pagina = max(1, $pagina);
        $ahora = $this->reloj->now();
        if ($vista === "por-resolver") {
            $todas = $this->porResolver($usuario);

            return [
                "items" => array_map(fn(SolicitudAutorizacion $s) => $this->fila($s, $usuario, $ahora), array_slice($todas, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA)),
                "total" => count($todas),
                "pagina" => $pagina,
                "porPagina" => self::POR_PAGINA,
            ];
        }

        $qb = $this->base();
        if ($vista === "mias") {
            $qb->andWhere("s.solicitante = :yo")->setParameter("yo", $usuario->getId());
        } else {
            $this->enAlcance($qb, $usuario);
        }
        if ($estado !== null) {
            self::conEstado($qb, $estado, $ahora);
        }
        $total = (int) (clone $qb)->select("COUNT(s.id)")->getQuery()->getSingleScalarResult();
        /** @var list<SolicitudAutorizacion> $filas */
        $filas = $qb->orderBy("s.creadaEn", "DESC")->addOrderBy("s.id", "DESC")
            ->setFirstResult(($pagina - 1) * self::POR_PAGINA)
            ->setMaxResults(self::POR_PAGINA)
            ->getQuery()
            ->getResult();

        return [
            "items" => array_map(fn(SolicitudAutorizacion $s) => $this->fila($s, $usuario, $ahora), $filas),
            "total" => $total,
            "pagina" => $pagina,
            "porPagina" => self::POR_PAGINA,
        ];
    }

    /** Para la cabecera: cuántas espera resolver y cuántos pases aprobados tiene sin usar. @return array{porResolver: int, aprobadas: int, pendientes: int} */
    public function resumen(Usuario $usuario): array
    {
        $ahora = $this->reloj->now();
        $mias = $this->base()
            ->select("s.estado AS estado", "COUNT(s.id) AS n")
            ->andWhere("s.solicitante = :yo AND s.estado IN (:abiertas) AND s.venceEn > :ahora")
            ->setParameter("yo", $usuario->getId())
            ->setParameter("abiertas", [EstadoAutorizacion::PENDIENTE->value, EstadoAutorizacion::APROBADA->value])
            ->setParameter("ahora", $ahora)
            ->groupBy("s.estado")
            ->getQuery()
            ->getArrayResult();
        $n = static fn(EstadoAutorizacion $e) => (int) (array_values(array_filter($mias, static fn(array $f) => ($f["estado"] instanceof EstadoAutorizacion ? $f["estado"] : EstadoAutorizacion::from((string) $f["estado"])) === $e))[0]["n"] ?? 0);

        return [
            "porResolver" => count($this->porResolver($usuario)),
            "aprobadas" => $n(EstadoAutorizacion::APROBADA),
            "pendientes" => $n(EstadoAutorizacion::PENDIENTE),
        ];
    }

    /** @return array<string, mixed> @throws AutorizacionRechazada */
    public function una(int $id, Usuario $usuario): array
    {
        $s = $this->em->find(SolicitudAutorizacion::class, $id) ?? throw new AutorizacionRechazada("La autorización no existe.", "no_encontrado", 404);
        if (!$this->puedeVer($s, $usuario)) {
            throw new AutorizacionRechazada("No puede ver esa autorización.", "permiso", 403);
        }

        return $this->fila($s, $usuario, $this->reloj->now());
    }

    /** Quien la pidió, o quien aprueba donde cae. */
    public function puedeVer(SolicitudAutorizacion $s, Usuario $usuario): bool
    {
        return $s->getSolicitante()->getId() === $usuario->getId()
            || $this->autorizacion->de($usuario)->puede(Capacidad::AUTORIZACION_APROBAR->value, $s->getUbicacion());
    }

    /**
     * Los usuarios activos que pueden resolverla (su bandeja se refresca).
     *
     * @return list<Usuario>
     */
    public function resolvedores(SolicitudAutorizacion $s): array
    {
        return array_column($this->conCercania($s), "usuario");
    }

    /**
     * Los que la pueden resolver más cerca de la operación (a quienes se
     * avisa por el chat): los de esa estación; si no hay, los de esa empresa;
     * si no, los que aprueban en todas. Los demás la ven en su bandeja.
     *
     * @return list<Usuario>
     */
    public function resolvedoresCercanos(SolicitudAutorizacion $s): array
    {
        $todos = $this->conCercania($s);
        if ($todos === []) {
            return [];
        }
        $minima = min(array_column($todos, "cercania"));

        return array_values(array_column(array_filter($todos, static fn(array $r) => $r["cercania"] === $minima), "usuario"));
    }

    /**
     * Quienes aprueban autorizaciones más cerca de una operación en `$donde`
     * (misma regla que `resolvedoresCercanos`), sin `$excepto` (quien la
     * hizo). Para los avisos de umbral diario (ADR-044).
     *
     * @return list<Usuario>
     */
    public function aprobadoresCercanos(Ubicacion $donde, ?int $excepto = null): array
    {
        $todos = [];
        foreach ($this->autorizacion->deTodos() as $id => ["usuario" => $u, "permisos" => $p]) {
            $cercania = $id === $excepto ? null : ReglasAutorizacion::cercaniaA($p, $donde);
            if ($cercania !== null) {
                $todos[] = ["usuario" => $u, "cercania" => $cercania];
            }
        }
        if ($todos === []) {
            return [];
        }
        $minima = min(array_column($todos, "cercania"));

        return array_values(array_column(array_filter($todos, static fn(array $r) => $r["cercania"] === $minima), "usuario"));
    }

    /** @return list<array{usuario: Usuario, cercania: int}> */
    private function conCercania(SolicitudAutorizacion $s): array
    {
        $resultado = [];
        foreach ($this->autorizacion->deTodos() as ["usuario" => $u, "permisos" => $p]) {
            if (ReglasAutorizacion::rechazoParaResolver($p, $u->getId(), $s) === null) {
                $resultado[] = ["usuario" => $u, "cercania" => ReglasAutorizacion::cercania($p, $s) ?? 2];
            }
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    public function fila(SolicitudAutorizacion $s, Usuario $usuario, \DateTimeImmutable $ahora): array
    {
        $pendiente = $s->estadoEn($ahora) === EstadoAutorizacion::PENDIENTE;

        return VistaAutorizacion::fila($s, $ahora, [
            "aprobar" => $pendiente && ReglasAutorizacion::rechazoParaResolver($this->autorizacion->de($usuario), $usuario->getId(), $s) === null,
            "retirar" => $s->estadoEn($ahora)->abierta() && $s->getSolicitante()->getId() === $usuario->getId(),
        ]);
    }

    /** @return list<SolicitudAutorizacion> las pendientes que puede resolver, de la más antigua a la más nueva */
    private function porResolver(Usuario $usuario): array
    {
        $permisos = $this->autorizacion->de($usuario);
        if (!$permisos->tiene(Capacidad::AUTORIZACION_APROBAR->value)) {
            return [];
        }
        $qb = $this->base()
            ->andWhere("s.estado = :pendiente AND s.venceEn > :ahora AND s.solicitante <> :yo")
            ->setParameter("pendiente", EstadoAutorizacion::PENDIENTE->value)
            ->setParameter("ahora", $this->reloj->now())
            ->setParameter("yo", $usuario->getId());
        $this->enAlcance($qb, $usuario);
        /** @var list<SolicitudAutorizacion> $filas */
        $filas = $qb->orderBy("s.creadaEn", "ASC")->setMaxResults(self::MAX_PENDIENTES)->getQuery()->getResult();

        return array_values(array_filter($filas, static fn(SolicitudAutorizacion $s) => ReglasAutorizacion::rechazoParaResolver($permisos, $usuario->getId(), $s) === null));
    }

    private function base(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select("s", "u")
            ->from(SolicitudAutorizacion::class, "s")
            ->join("s.solicitante", "u");
    }

    /** Las de su alcance de `autorizacion.aprobar` (ninguna si no la tiene). */
    private function enAlcance(QueryBuilder $qb, Usuario $usuario): void
    {
        $alcance = $this->autorizacion->de($usuario)->alcance(Capacidad::AUTORIZACION_APROBAR->value);
        if ($alcance->vacio()) {
            $qb->andWhere("1 = 0");

            return;
        }
        FiltroAlcance::aplicar($qb, $alcance, "s.empresa", "s.estacion", compartidos: true, prefijo: "aprueba");
    }

    /** Una abierta vencida cuenta como vencida, no como pendiente o aprobada. */
    private static function conEstado(QueryBuilder $qb, EstadoAutorizacion $estado, \DateTimeImmutable $ahora): void
    {
        match ($estado) {
            EstadoAutorizacion::VENCIDA => $qb->andWhere("s.estado IN (:abiertas) AND s.venceEn <= :ahora")
                ->setParameter("abiertas", [EstadoAutorizacion::PENDIENTE->value, EstadoAutorizacion::APROBADA->value])
                ->setParameter("ahora", $ahora),
            EstadoAutorizacion::PENDIENTE, EstadoAutorizacion::APROBADA => $qb->andWhere("s.estado = :estado AND s.venceEn > :ahora")
                ->setParameter("estado", $estado->value)
                ->setParameter("ahora", $ahora),
            default => $qb->andWhere("s.estado = :estado")->setParameter("estado", $estado->value),
        };
    }
}
