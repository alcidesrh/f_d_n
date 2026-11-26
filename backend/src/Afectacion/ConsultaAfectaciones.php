<?php

declare(strict_types=1);

namespace App\Afectacion;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Dimension;
use App\Acceso\EstacionTrabajo;
use App\Acceso\PermisosUsuario;
use App\Entity\Afectacion;
use App\Entity\Bus;
use App\Entity\Empresa;
use App\Entity\Enum\EstadoAfectacion;
use App\Entity\Estacion;
use App\Entity\TipoAfectacion;
use App\Entity\Usuario;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;

/**
 * Lectura de las afectaciones (ADR-070). Cada usuario ve las que reportó y
 * las de los tipos que puede reportar, atender, recibir escaladas o
 * supervisar, donde cae cada una (`ReglasAfectacion::capacidadesQueVen`).
 * Como cada tipo pide sus propias capacidades, el filtro se arma por grupos
 * de tipos con las mismas, en una sola consulta.
 */
final class ConsultaAfectaciones
{
    public const POR_PAGINA = 30;
    public const VISTAS = ["abiertas", "resueltas", "todas"];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
        private readonly Destinatarios $destinatarios,
        private readonly EstacionTrabajo $estacionTrabajo,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{filas: list<array<string, mixed>>, total: int, pagina: int, porPagina: int} */
    public function listar(Usuario $yo, string $vista, ?int $tipo, int $pagina): array
    {
        $pagina = max(1, $pagina);
        $qb = $this->em->createQueryBuilder()->from(Afectacion::class, "a")->join("a.tipo", "t");
        $this->visibles($qb, $yo);
        if ($vista === "abiertas") {
            $qb->andWhere("a.estado <> :resuelta")->setParameter("resuelta", EstadoAfectacion::RESUELTA);
        } elseif ($vista === "resueltas") {
            $qb->andWhere("a.estado = :resuelta")->setParameter("resuelta", EstadoAfectacion::RESUELTA);
        }
        if ($tipo !== null) {
            $qb->andWhere("a.tipo = :tipo")->setParameter("tipo", $tipo);
        }
        $total = (int) (clone $qb)->select("COUNT(a.id)")->getQuery()->getSingleScalarResult();

        $qb->select("a", "t", "e", "s", "b", "r", "rp")
            ->addSelect("CASE WHEN t.prioridad = 'urgente' THEN 0 WHEN t.prioridad = 'grave' THEN 1 ELSE 2 END AS HIDDEN peso")
            ->addSelect("CASE WHEN a.estado = 'resuelta' THEN 1 ELSE 0 END AS HIDDEN cerrada")
            ->leftJoin("a.empresa", "e")
            ->leftJoin("a.estacion", "s")
            ->leftJoin("a.bus", "b")
            ->leftJoin("a.reportadaPor", "r")
            ->leftJoin("a.resueltaPor", "rp")
            ->setFirstResult(($pagina - 1) * self::POR_PAGINA)
            ->setMaxResults(self::POR_PAGINA);
        if ($vista === "resueltas") {
            $qb->orderBy("a.resueltaEn", "DESC");
        } else {
            // Lo abierto primero, lo urgente primero y lo más viejo primero.
            $qb->orderBy("cerrada", "ASC")->addOrderBy("peso", "ASC")->addOrderBy("a.creadaEn", "ASC");
        }
        $ahora = $this->ahora();
        $filas = array_map(fn(Afectacion $a) => $this->fila($a, $yo, $ahora), $qb->getQuery()->getResult());

        return ["filas" => $filas, "total" => $total, "pagina" => $pagina, "porPagina" => self::POR_PAGINA];
    }

    /** @return array<string, mixed> */
    public function una(int $id, Usuario $yo): array
    {
        $a = $this->em->find(Afectacion::class, $id);
        if ($a === null || !$this->puedeVer($a, $yo)) {
            throw new AfectacionRechazada("La afectación no existe.", "afectacion_no_existe", 404);
        }

        return $this->fila($a, $yo, $this->ahora());
    }

    public function puedeVer(Afectacion $a, Usuario $yo): bool
    {
        return $a->getReportadaPor()?->getId() === $yo->getId()
            || $this->destinatarios->alcanza($this->autorizacion->de($yo), $a, ReglasAfectacion::capacidadesQueVen($a->getTipo()));
    }

    public function puedeAtender(Afectacion $a, Usuario $yo): bool
    {
        return $this->destinatarios->alcanza($this->autorizacion->de($yo), $a, [$a->getTipo()->getTarget()]);
    }

    /** @return array<string, mixed> */
    public function fila(Afectacion $a, Usuario $yo, \DateTimeImmutable $ahora): array
    {
        $t = $a->getTipo();
        $abierta = $a->getEstado()->abierta();
        $atiende = $abierta && $this->puedeAtender($a, $yo);
        $proximo = ReglasCentinela::proximoAviso($a->getEstado(), $a->getCreadaEn(), $a->getEscaladaEn(), $a->getUltimoAvisoEn(), $t->getIntervaloMinutos(), $t->getEscalarTrasMinutos(), $ahora);
        $usuario = static fn(?Usuario $u) => $u === null ? null : ["id" => (int) $u->getId(), "nombre" => trim((string) $u->getFullName()) ?: $u->getUsername()];
        $fecha = static fn(?\DateTimeImmutable $f) => $f?->format(DATE_ATOM);

        return [
            "id" => (int) $a->getId(),
            "tipo" => ["id" => (int) $t->getId(), "codigo" => $t->getCodigo(), "nombre" => $t->getNombre(), "prioridad" => $t->getPrioridad()->value, "sujeto" => $t->getSujeto()->value],
            "estado" => $a->getEstado()->value,
            "descripcion" => $a->getDescripcion(),
            "lugar" => ReglasAfectacion::lugar($a),
            "empresa" => $a->getEmpresa() === null ? null : ["id" => (int) $a->getEmpresa()->getId(), "nombre" => (string) $a->getEmpresa()->getNombreCorto()],
            "estacion" => $a->getEstacion() === null ? null : ["id" => (int) $a->getEstacion()->getId(), "nombre" => (string) $a->getEstacion()->getNombre()],
            "bus" => $a->getBus() === null ? null : ["id" => (int) $a->getBus()->getId(), "codigo" => $a->getBus()->getCodigo()],
            "reportadaPor" => $usuario($a->getReportadaPor()),
            "automatica" => $a->getReportadaPor() === null,
            "creadaEn" => $fecha($a->getCreadaEn()),
            "enviadaEn" => $fecha($a->getEnviadaEn()),
            "escaladaEn" => $fecha($a->getEscaladaEn()),
            "ultimoAvisoEn" => $fecha($a->getUltimoAvisoEn()),
            "avisos" => $a->getAvisos(),
            "proximoAviso" => $fecha($proximo),
            "resueltaEn" => $fecha($a->getResueltaEn()),
            "resueltaPor" => $usuario($a->getResueltaPor()),
            "resolucion" => $a->getResolucion(),
            "puede" => ["editar" => $atiende, "resolver" => $atiende],
        ];
    }

    /**
     * Lo que este usuario puede reportar: los tipos activos cuyo `owner`
     * tiene en algún lado, con las empresas y estaciones de ese alcance
     * (null = todas) y su estación de trabajo para proponerla.
     *
     * @return array{tipos: list<array<string, mixed>>, estacion: array{id: int, nombre: string}|null}
     */
    public function paraReportar(Usuario $yo): array
    {
        $permisos = $this->autorizacion->de($yo);
        $tipos = [];
        /** @var TipoAfectacion $t */
        foreach ($this->em->getRepository(TipoAfectacion::class)->findBy(["activo" => true], ["nombre" => "ASC"]) as $t) {
            $alcance = $permisos->alcance($t->getOwner());
            if ($alcance->vacio()) {
                continue;
            }
            $tipos[] = [
                "id" => (int) $t->getId(),
                "codigo" => $t->getCodigo(),
                "nombre" => $t->getNombre(),
                "descripcion" => $t->getDescripcion(),
                "sujeto" => $t->getSujeto()->value,
                "prioridad" => $t->getPrioridad()->value,
                "empresas" => $alcance->empresas(),
                "estaciones" => $alcance->estaciones(),
            ];
        }
        usort($tipos, static fn(array $a, array $b) => [self::peso($a["prioridad"]), $a["nombre"]] <=> [self::peso($b["prioridad"]), $b["nombre"]]);
        $estacion = $this->estacionTrabajo->actual($yo);

        return ["tipos" => $tipos, "estacion" => $estacion === null ? null : ["id" => (int) $estacion->getId(), "nombre" => (string) $estacion->getNombre()]];
    }

    /** @return list<array{id: int, nombre: string}> las empresas, para elegir dónde cae */
    public function empresas(): array
    {
        return array_map(
            static fn(Empresa $e) => ["id" => (int) $e->getId(), "nombre" => (string) $e->getNombreCorto()],
            $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"]),
        );
    }

    /** @return list<array{id: int, nombre: string}> las estaciones activas */
    public function estaciones(): array
    {
        return array_map(
            static fn(Estacion $e) => ["id" => (int) $e->getId(), "nombre" => (string) $e->getNombre()],
            $this->em->getRepository(Estacion::class)->findBy(["activo" => true], ["nombre" => "ASC"]),
        );
    }

    /** @return list<array{id: int, codigo: string, matricula: ?string, empresaId: ?int}> los buses, para la avería de uno */
    public function buses(): array
    {
        return array_map(
            static fn(Bus $b) => ["id" => (int) $b->getId(), "codigo" => $b->getCodigo(), "matricula" => $b->getMatricula(), "empresaId" => $b->getEmpresa()?->getId()],
            $this->em->getRepository(Bus::class)->findBy([], ["codigo" => "ASC"]),
        );
    }

    /** ¿Supervisa (ve el historial de todas las de su alcance)? */
    public function supervisa(Usuario $yo): bool
    {
        return $this->autorizacion->de($yo)->tiene(Capacidad::AFECTACION_SUPERVISAR->value);
    }

    /**
     * Las que reportó, o las de cada grupo de tipos con las mismas
     * capacidades donde alguna de ellas lo alcanza.
     */
    private function visibles(QueryBuilder $qb, Usuario $yo): void
    {
        $permisos = $this->autorizacion->de($yo);
        $partes = ["a.reportadaPor = :yo"];
        $qb->setParameter("yo", $yo->getId());
        $grupos = [];
        /** @var TipoAfectacion $t */
        foreach ($this->em->getRepository(TipoAfectacion::class)->findAll() as $t) {
            $capacidades = ReglasAfectacion::capacidadesQueVen($t);
            sort($capacidades);
            $clave = implode("|", $capacidades);
            $grupos[$clave]["capacidades"] = $capacidades;
            $grupos[$clave]["tipos"][] = (int) $t->getId();
        }
        $g = 0;
        foreach ($grupos as ["capacidades" => $capacidades, "tipos" => $tipos]) {
            $condicion = self::condicionGrupo($permisos, $capacidades, $this->destinatarios->dimensiones($capacidades), "v{$g}");
            if ($condicion !== null) {
                [$sql, $parametros] = $condicion;
                $partes[] = $sql === null ? "a.tipo IN (:vt{$g})" : "(a.tipo IN (:vt{$g}) AND {$sql})";
                $qb->setParameter("vt{$g}", $tipos, ArrayParameterType::INTEGER);
                foreach ($parametros as $nombre => $valor) {
                    $qb->setParameter($nombre, $valor, ArrayParameterType::INTEGER);
                }
            }
            $g++;
        }
        $qb->andWhere("(" . implode(" OR ", $partes) . ")");
    }

    /**
     * Dónde lo alcanza alguna de las capacidades de un grupo de tipos, en
     * DQL: null si ninguna (no ve el grupo), `[null, []]` si alguna lo
     * alcanza en todo, o `[condición, parámetros]`.
     *
     * @param list<string>                $capacidades
     * @param array<string, list<string>> $dimensiones
     *
     * @return array{0: ?string, 1: array<string, list<int>>}|null
     */
    public static function condicionGrupo(PermisosUsuario $permisos, array $capacidades, array $dimensiones, string $prefijo): ?array
    {
        $condiciones = [];
        $parametros = [];
        foreach ($capacidades as $j => $c) {
            $alcance = $permisos->alcance($c);
            if ($alcance->vacio()) {
                continue;
            }
            $porEmpresa = in_array(Dimension::EMPRESA->value, $dimensiones[$c] ?? [], true);
            $porEstacion = in_array(Dimension::ESTACION->value, $dimensiones[$c] ?? [], true);
            if (!$porEmpresa && !$porEstacion) {
                return [null, []];
            }
            if (!$porEmpresa) {
                $estaciones = $alcance->estaciones();
                if ($estaciones === null) {
                    return [null, []];
                }
                $condiciones[] = "a.estacion IN (:{$prefijo}_{$j}_s)";
                $parametros["{$prefijo}_{$j}_s"] = $estaciones;
                continue;
            }
            [$sql, $suyos] = $alcance->condicion("a.empresa", $porEstacion ? "a.estacion" : null, false, "{$prefijo}_{$j}");
            if ($sql === "1 = 1") {
                return [null, []];
            }
            $condiciones[] = $sql;
            $parametros += $suyos;
        }

        return $condiciones === [] ? null : ["(" . implode(" OR ", $condiciones) . ")", $parametros];
    }

    private static function peso(string $prioridad): int
    {
        return match ($prioridad) {
            "urgente" => 0,
            "grave" => 1,
            default => 2,
        };
    }

    private function ahora(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->reloj->now());
    }
}
