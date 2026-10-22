<?php

declare(strict_types=1);

namespace App\Acceso\Administracion;

use App\Acceso\Ambito;
use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Catalogo\Catalogo;
use App\Acceso\Delegacion;
use App\Acceso\Limite;
use App\Acceso\PermisosUsuario;
use App\Acceso\Ubicacion;
use App\Bitacora\TipoRegistro;
use App\Chat\AvisosChat;
use App\Entity\Asignacion;
use App\Entity\Empresa;
use App\Entity\Enum\OperacionRol;
use App\Entity\Enum\OperacionUsuario;
use App\Entity\Estacion;
use App\Entity\Role;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Administración de accesos (ADR-039): roles, asignaciones y activación de
 * usuarios, con las reglas de `Delegacion` (nadie otorga lo que no tiene
 * donde no lo tiene, nadie toca sus propias asignaciones). Cada cambio queda
 * en la bitácora del usuario o del rol y avisa por Mercure a los afectados,
 * que refrescan lo que pueden hacer.
 */
final class AdministracionAccesos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
        private readonly Catalogo $catalogo,
        private readonly BitacoraAcceso $bitacora,
        private readonly AvisosChat $avisos,
    ) {}

    // ---- Roles ------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        $this->exigir(Capacidad::IAM_VER);
        // Usuarios activos con una asignación vigente de cada rol, en una consulta.
        $filas = $this->em->createQueryBuilder()
            ->select('IDENTITY(a.rol) AS rol', 'COUNT(DISTINCT u.id) AS usuarios')
            ->from(Asignacion::class, 'a')
            ->join('a.usuario', 'u')
            ->where('u.activo = true')
            ->andWhere('a.desde IS NULL OR a.desde <= :hoy')
            ->andWhere('a.hasta IS NULL OR a.hasta >= :hoy')
            ->setParameter('hoy', new \DateTimeImmutable('today'), \Doctrine\DBAL\Types\Types::DATE_IMMUTABLE)
            ->groupBy('a.rol')
            ->getQuery()
            ->getScalarResult();
        $asignados = array_column($filas, 'usuarios', 'rol');

        return array_map(
            fn(Role $r) => $this->vistaRol($r, (int) ($asignados[(int) $r->getId()] ?? 0)),
            $this->em->getRepository(Role::class)->findBy([], ['nombre' => 'ASC']),
        );
    }

    /**
     * `{ nombre, descripcion?, capacidades: [codigo], limites?: { codigo: n } }`.
     *
     * @param array<string, mixed> $datos
     *
     * @return array<string, mixed>
     */
    public function crearRol(array $datos): array
    {
        $this->exigir(Capacidad::IAM_ROLES);
        [$nombre, $descripcion, $capacidades, $limites] = $this->datosRol($datos, null);
        $motivo = Delegacion::rechazoEditarRol($this->actor(), [], $capacidades, [], $limites, [], $this->nombre(...));
        if ($motivo !== null) {
            throw AccesoRechazado::permiso($motivo);
        }
        $rol = (new Role())->setNombre($nombre)->setDescripcion($descripcion)->setCapacidades($capacidades)->setLimites($limites);

        $this->em->wrapInTransaction(function () use ($rol) {
            $this->em->persist($rol);
            $this->em->flush();
            $this->bitacora->anotar(TipoRegistro::ROL, (int) $rol->getId(), OperacionRol::CREADO, $this->usuarioActual(), [
                'nombre' => $rol->getNombre(),
                'capacidades' => $rol->getCapacidades(),
                'limites' => $rol->getLimites() ?: null,
            ]);
        });

        return $this->vistaRol($rol, 0);
    }

    /**
     * @param array<string, mixed> $datos como en `crearRol`
     *
     * @return array<string, mixed>
     */
    public function editarRol(int $id, array $datos): array
    {
        $this->exigir(Capacidad::IAM_ROLES);
        $rol = $this->rol($id);
        if ($rol->isSistema()) {
            throw new AccesoRechazado('El rol de sistema tiene todas las capacidades y no se edita.', 'rol_sistema');
        }
        [$nombre, $descripcion, $capacidades, $limites] = $this->datosRol($datos, $rol);
        $vigentes = $this->asignacionesVigentes($rol);
        $motivo = Delegacion::rechazoEditarRol(
            $this->actor(),
            $rol->getCapacidades(),
            $capacidades,
            $rol->getLimites(),
            $limites,
            array_map(self::ambito(...), $vigentes),
            $this->nombre(...),
        );
        if ($motivo !== null) {
            throw AccesoRechazado::permiso($motivo);
        }
        $antes = ['capacidades' => $rol->getCapacidades(), 'limites' => $rol->getLimites()];

        $this->em->wrapInTransaction(function () use ($rol, $nombre, $descripcion, $capacidades, $limites, $antes) {
            $rol->setNombre($nombre)->setDescripcion($descripcion)->setCapacidades($capacidades)->setLimites($limites);
            $this->em->flush();
            $this->bitacora->anotar(TipoRegistro::ROL, (int) $rol->getId(), OperacionRol::MODIFICADO, $this->usuarioActual(), [
                'nombre' => $nombre,
                'agregadas' => array_values(array_diff($capacidades, $antes['capacidades'])) ?: null,
                'quitadas' => array_values(array_diff($antes['capacidades'], $capacidades)) ?: null,
                'limites' => $limites !== $antes['limites'] ? ['antes' => $antes['limites'], 'ahora' => $limites] : null,
            ]);
        });
        $this->avisar(array_map(static fn(Asignacion $a) => (int) $a->getUsuario()->getId(), $vigentes));

        return $this->vistaRol($rol, count(array_unique(array_map(static fn(Asignacion $a) => $a->getUsuario()->getId(), $vigentes))));
    }

    public function eliminarRol(int $id): void
    {
        $this->exigir(Capacidad::IAM_ROLES);
        $rol = $this->rol($id);
        if ($rol->isSistema() || $rol->esBase()) {
            throw new AccesoRechazado('El rol de sistema y el rol base no se eliminan.', 'rol_protegido');
        }
        $asignaciones = $this->em->getRepository(Asignacion::class)->count(['rol' => $rol]);
        if ($asignaciones > 0) {
            throw new AccesoRechazado(sprintf('El rol tiene %d asignaciones: revóquelas primero.', $asignaciones), 'rol_asignado', 409);
        }

        $this->em->wrapInTransaction(function () use ($rol) {
            $this->bitacora->anotar(TipoRegistro::ROL, (int) $rol->getId(), OperacionRol::ELIMINADO, $this->usuarioActual(), [
                'nombre' => $rol->getNombre(),
                'capacidades' => $rol->getCapacidades(),
            ]);
            $this->em->remove($rol);
            $this->em->flush();
        });
    }

    // ---- Usuarios y asignaciones -----------------------------------------

    /**
     * Lo que tiene un usuario: sus asignaciones (vigentes o no), lo que puede
     * hacer en efecto (con el rol que se lo da) y qué puede cambiar quien mira.
     *
     * @return array<string, mixed>
     */
    public function accesos(int $usuarioId): array
    {
        $this->exigir(Capacidad::IAM_VER);
        $usuario = $this->usuario($usuarioId);
        $actor = $this->actor();
        $actorId = (int) $this->usuarioActual()?->getId();
        $hoy = new \DateTimeImmutable('today');
        $nombres = $this->nombresLugares();
        $permisos = $this->autorizacion->de($usuario);

        $asignaciones = $this->em->getRepository(Asignacion::class)->findBy(['usuario' => $usuario], ['id' => 'ASC']);
        $origenes = [];
        foreach ($permisos->concesiones() as $c) {
            foreach ($c->sistema ? $this->catalogo->codigos() : $c->capacidades as $codigo) {
                $origenes[$codigo][] = $c->rolNombre;
            }
        }
        $definiciones = $this->catalogo->definiciones();

        return [
            'usuario' => [
                'id' => (int) $usuario->getId(),
                'username' => $usuario->getUsername(),
                'nombre' => $usuario->getFullName(),
                'activo' => $usuario->isActivo(),
                'agencia' => $usuario->getAgencia() === null ? null : ['id' => (int) $usuario->getAgencia()->getId(), 'nombre' => $usuario->getAgencia()->getNombre()],
                'estacion' => $usuario->getEstacion() === null ? null : ['id' => (int) $usuario->getEstacion()->getId(), 'nombre' => $usuario->getEstacion()->getNombre()],
            ],
            'asignaciones' => array_map(fn(Asignacion $a) => $this->vistaAsignacion($a, $hoy, $nombres, $actor, $actorId), $asignaciones),
            'efectivo' => array_values(array_map(
                static fn(string $codigo, $alcance) => [
                    'codigo' => $codigo,
                    'nombre' => $definiciones[$codigo]->nombre ?? $codigo,
                    'area' => $definiciones[$codigo]->area ?? 'Otros',
                    'ambitos' => $alcance->toArray(),
                    'roles' => array_values(array_unique($origenes[$codigo] ?? [])),
                ],
                array_keys($permisos->capacidades()),
                $permisos->capacidades(),
            )),
            'puede' => [
                'asignar' => $actor->tiene(Capacidad::IAM_ASIGNAR->value) && $actorId !== (int) $usuario->getId(),
                'activar' => $this->rechazoSobre($usuario) === null && $actor->tiene('usuario.editar') && $actorId !== (int) $usuario->getId(),
            ],
        ];
    }

    /**
     * `{ rol, empresas: [id]|null, estaciones: [id]|null, desde?, hasta?, motivo? }`
     * (null = todas). A un usuario de agencia el alcance no se le elige.
     *
     * @param array<string, mixed> $datos
     *
     * @return array<string, mixed> los accesos del usuario
     */
    public function otorgar(int $usuarioId, array $datos): array
    {
        $usuario = $this->usuario($usuarioId);
        $asignacion = new Asignacion($usuario, $this->rolAsignable($datos['rol'] ?? null));
        $this->llenar($asignacion, $datos);
        $this->exigirOtorgar($asignacion);

        $this->em->wrapInTransaction(function () use ($asignacion) {
            $asignacion->setOtorgadaPor($this->usuarioActualGestionado());
            $this->em->persist($asignacion);
            $this->em->flush();
            $this->bitacora->anotar(TipoRegistro::USUARIO, (int) $asignacion->getUsuario()->getId(), OperacionUsuario::ASIGNACION_OTORGADA, $this->usuarioActual(), $this->detalle($asignacion));
        });
        $this->avisar([$usuarioId]);

        return $this->accesos($usuarioId);
    }

    /**
     * @param array<string, mixed> $datos como en `otorgar`
     *
     * @return array<string, mixed>
     */
    public function modificar(int $asignacionId, array $datos): array
    {
        $asignacion = $this->asignacion($asignacionId);
        // Quien la cambia tiene que poder otorgar la de antes (la deshace) y la de después.
        $this->exigirOtorgar($asignacion);
        $antes = $this->detalle($asignacion);
        try {
            $asignacion->setRol($this->rolAsignable($datos['rol'] ?? $asignacion->getRol()->getId()));
            $this->llenar($asignacion, $datos);
            $this->exigirOtorgar($asignacion);
        } catch (AccesoRechazado $e) {
            // No queda nada a medias en la unidad de trabajo.
            $this->em->refresh($asignacion);
            throw $e;
        }
        $usuarioId = (int) $asignacion->getUsuario()->getId();

        $this->em->wrapInTransaction(function () use ($asignacion, $antes, $usuarioId) {
            $asignacion->marcarModificada();
            $this->em->flush();
            $this->bitacora->anotar(TipoRegistro::USUARIO, $usuarioId, OperacionUsuario::ASIGNACION_MODIFICADA, $this->usuarioActual(), [
                'antes' => $antes,
                'ahora' => $this->detalle($asignacion),
            ]);
        });
        $this->avisar([$usuarioId]);

        return $this->accesos($usuarioId);
    }

    /** @return array<string, mixed> los accesos del usuario */
    public function revocar(int $asignacionId): array
    {
        $asignacion = $this->asignacion($asignacionId);
        $this->exigirOtorgar($asignacion);
        $usuarioId = (int) $asignacion->getUsuario()->getId();

        $this->em->wrapInTransaction(function () use ($asignacion, $usuarioId) {
            $this->bitacora->anotar(TipoRegistro::USUARIO, $usuarioId, OperacionUsuario::ASIGNACION_REVOCADA, $this->usuarioActual(), $this->detalle($asignacion));
            $this->em->remove($asignacion);
            $this->em->flush();
        });
        $this->avisar([$usuarioId]);

        return $this->accesos($usuarioId);
    }

    /** Activar o desactivar: quien domina al usuario, con `usuario.editar`, y no a sí mismo. */
    public function activar(int $usuarioId, bool $activo): void
    {
        $usuario = $this->usuario($usuarioId);
        if ((int) $this->usuarioActual()?->getId() === $usuarioId) {
            throw new AccesoRechazado('No puede activar ni desactivar su propio usuario.', 'propio', 403);
        }
        $this->exigir('usuario.editar');
        $this->exigirDominio($usuario);
        if ($usuario->isActivo() === $activo) {
            return;
        }

        $this->em->wrapInTransaction(function () use ($usuario, $activo) {
            $usuario->setActivo($activo);
            if (!$activo) {
                // Sus sesiones dejan de servir.
                foreach ($usuario->getApiTokens() as $token) {
                    $token->setActivo(false);
                }
            }
            $this->em->flush();
            $this->bitacora->anotar(TipoRegistro::USUARIO, (int) $usuario->getId(), $activo ? OperacionUsuario::ACTIVADO : OperacionUsuario::DESACTIVADO, $this->usuarioActual());
        });
        $this->autorizacion->olvidar($usuarioId);
        $this->avisar([$usuarioId]);
    }

    /**
     * Sobre otro usuario solo actúa quien podría otorgarle cada una de sus
     * asignaciones vigentes (contraseña, datos, agencia, borrarlo).
     *
     * @throws AccesoRechazado
     */
    public function exigirDominio(Usuario $usuario): void
    {
        $motivo = $this->rechazoSobre($usuario);
        if ($motivo !== null) {
            throw AccesoRechazado::permiso($motivo);
        }
    }

    public function rechazoSobre(Usuario $usuario): ?string
    {
        return Delegacion::rechazoSobreUsuario(
            $this->actor(),
            (int) $this->usuarioActual()?->getId(),
            (int) $usuario->getId(),
            $this->autorizacion->de($usuario)->concesiones(),
            $this->nombre(...),
        );
    }

    /** Anota en la bitácora del usuario un cambio hecho por otro servicio (contraseña). */
    public function anotar(Usuario $usuario, OperacionUsuario $operacion): void
    {
        $this->bitacora->anotar(TipoRegistro::USUARIO, (int) $usuario->getId(), $operacion, $this->usuarioActual());
    }

    // ---- Consultas --------------------------------------------------------

    /**
     * ¿Quién puede `capacidad` en esa empresa y estación (o en alguna)? Con
     * la asignación que se lo da.
     *
     * @return list<array<string, mixed>>
     */
    public function quienPuede(string $capacidad, ?int $empresa, ?int $estacion): array
    {
        $this->exigir(Capacidad::IAM_VER);
        if (!$this->catalogo->existe($capacidad)) {
            throw AccesoRechazado::noExiste('Esa capacidad no existe.');
        }
        $donde = null;
        if ($empresa !== null || $estacion !== null) {
            $donde = $empresa !== null ? Ubicacion::empresa($empresa) : Ubicacion::ninguna();
            $donde = $estacion !== null ? $donde->conEstacion($estacion) : $donde;
        }
        $filas = [];
        foreach ($this->autorizacion->deTodos() as ['usuario' => $u, 'permisos' => $p]) {
            $origen = $p->origen($capacidad, $donde);
            if ($origen === null) {
                continue;
            }
            $filas[] = [
                'usuario' => ['id' => (int) $u->getId(), 'username' => $u->getUsername(), 'nombre' => $u->getFullName(), 'agencia' => $u->getAgencia()?->getNombre()],
                'rol' => $origen->rolNombre,
                'asignacion' => $origen->asignacion,
                'ambito' => $origen->ambito->toArray(),
            ];
        }
        usort($filas, static fn(array $a, array $b) => strcmp((string) $a['usuario']['username'], (string) $b['usuario']['username']));

        return $filas;
    }

    /**
     * Usuarios activos que no pueden hacer nada: sin asignaciones vigentes o
     * con alcances vacíos (los que la migración no pudo inferir).
     *
     * @return list<array<string, mixed>>
     */
    public function sinAlcance(): array
    {
        $this->exigir(Capacidad::IAM_VER);
        $conAlgo = [];
        foreach ($this->autorizacion->deTodos() as $id => ['permisos' => $p]) {
            if ($p->capacidades() !== []) {
                $conAlgo[$id] = true;
            }
        }
        $filas = [];
        foreach ($this->em->getRepository(Usuario::class)->findBy(['activo' => true], ['username' => 'ASC']) as $u) {
            if (!isset($conAlgo[(int) $u->getId()])) {
                $filas[] = [
                    'id' => (int) $u->getId(),
                    'username' => $u->getUsername(),
                    'nombre' => $u->getFullName(),
                    'agencia' => $u->getAgencia()?->getNombre(),
                    'estacion' => $u->getEstacion()?->getNombre(),
                    'asignaciones' => $u->getAsignaciones()->count(),
                ];
            }
        }

        return $filas;
    }

    // ---- Internos ---------------------------------------------------------

    /**
     * @param array<string, mixed> $datos
     *
     * @return array{0: string, 1: ?string, 2: list<string>, 3: array<string, int>}
     */
    private function datosRol(array $datos, ?Role $rol): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? $rol?->getNombre() ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 255) {
            throw new AccesoRechazado('El rol necesita un nombre (hasta 255 caracteres).', 'nombre_invalido');
        }
        $otro = $this->em->getRepository(Role::class)->findOneBy(['nombre' => $nombre]);
        if ($otro !== null && $otro !== $rol) {
            throw new AccesoRechazado('Ya hay un rol con ese nombre.', 'nombre_repetido', 409);
        }
        $descripcion = trim((string) ($datos['descripcion'] ?? $rol?->getDescripcion() ?? ''));
        $capacidades = array_values(array_unique(array_map('strval', (array) ($datos['capacidades'] ?? $rol?->getCapacidades() ?? []))));
        $desconocidas = array_diff($capacidades, $this->catalogo->codigos());
        if ($desconocidas !== []) {
            throw new AccesoRechazado(sprintf('Capacidades que no existen: %s.', implode(', ', $desconocidas)), 'capacidad_inexistente');
        }
        if ($rol?->esBase() && $capacidades !== []) {
            throw new AccesoRechazado('El rol base lo tienen todos: no lleva capacidades (sirve para menús y administración).', 'rol_base');
        }
        $limites = [];
        foreach ((array) ($datos['limites'] ?? $rol?->getLimites() ?? []) as $codigo => $valor) {
            $limite = Limite::tryFrom((string) $codigo) ?? throw new AccesoRechazado("El límite «{$codigo}» no existe.", 'limite_inexistente');
            if ($valor === null || $valor === '') {
                continue;
            }
            if (!is_numeric($valor) || (int) $valor < 0 || (int) $valor > 100000) {
                throw new AccesoRechazado(sprintf('«%s» debe ser un número de 0 a 100000.', $limite->nombre()), 'limite_invalido');
            }
            if (!in_array($limite->capacidad()->value, $capacidades, true)) {
                throw new AccesoRechazado(sprintf('«%s» solo tiene sentido con «%s».', $limite->nombre(), $limite->capacidad()->nombre()), 'limite_sin_capacidad');
            }
            $limites[$limite->value] = (int) $valor;
        }

        return [$nombre, $descripcion === '' ? null : mb_substr($descripcion, 0, 255), $capacidades, $limites];
    }

    /** @param array<string, mixed> $datos */
    private function llenar(Asignacion $a, array $datos): void
    {
        $agencia = $a->getUsuario()->getAgencia() !== null;
        $empresas = array_key_exists('empresas', $datos) ? $datos['empresas'] : $a->getEmpresas();
        $estaciones = array_key_exists('estaciones', $datos) ? $datos['estaciones'] : $a->getEstaciones();
        $a->setEmpresas($agencia ? null : $this->ids($empresas, Empresa::class, 'empresas'))
            ->setEstaciones($agencia ? null : $this->ids($estaciones, Estacion::class, 'estaciones'))
            ->setDesde(array_key_exists('desde', $datos) ? self::fecha($datos['desde'], 'desde') : $a->getDesde())
            ->setHasta(array_key_exists('hasta', $datos) ? self::fecha($datos['hasta'], 'hasta') : $a->getHasta())
            ->setMotivo(array_key_exists('motivo', $datos) ? (string) $datos['motivo'] : $a->getMotivo());
        if ($a->getDesde() !== null && $a->getHasta() !== null && $a->getHasta() < $a->getDesde()) {
            throw new AccesoRechazado('La vigencia termina antes de empezar.', 'vigencia_invalida');
        }
        if (!$agencia && ($a->getEmpresas() === [] || $a->getEstaciones() === [])) {
            throw new AccesoRechazado('Elija al menos una empresa y una estación (o todas).', 'alcance_vacio');
        }
    }

    private function exigirOtorgar(Asignacion $a): void
    {
        $this->exigir(Capacidad::IAM_ASIGNAR);
        $motivo = Delegacion::rechazoOtorgar(
            $this->actor(),
            (int) $this->usuarioActual()?->getId(),
            (int) $a->getUsuario()->getId(),
            $a->getRol()->getCapacidades(),
            $a->getRol()->isSistema(),
            self::ambito($a),
            $this->nombre(...),
        );
        if ($motivo !== null) {
            throw AccesoRechazado::permiso($motivo);
        }
    }

    /** El ámbito donde rige: el suyo, o la agencia del usuario con sus empresas. */
    private static function ambito(Asignacion $a): Ambito
    {
        $agencia = $a->getUsuario()->getAgencia();

        return $agencia !== null
            ? new Ambito($agencia->empresaIds(), null, (int) $agencia->getId())
            : new Ambito($a->getEmpresas(), $a->getEstaciones());
    }

    /** @return list<Asignacion> */
    private function asignacionesVigentes(Role $rol): array
    {
        $hoy = new \DateTimeImmutable('today');

        return array_values(array_filter(
            $this->em->getRepository(Asignacion::class)->findBy(['rol' => $rol]),
            static fn(Asignacion $a) => $a->vigente($hoy),
        ));
    }

    private function rolAsignable(mixed $id): Role
    {
        $rol = is_numeric($id) ? $this->em->find(Role::class, (int) $id) : null;
        if ($rol === null) {
            throw new AccesoRechazado('Elija un rol que exista.', 'rol_inexistente');
        }
        if ($rol->esBase()) {
            throw new AccesoRechazado('El rol base lo tienen todos: no se asigna.', 'rol_base');
        }

        return $rol;
    }

    /**
     * @param class-string $clase
     *
     * @return list<int>|null
     */
    private function ids(mixed $valor, string $clase, string $campo): ?array
    {
        if ($valor === null) {
            return null;
        }
        if (!is_array($valor)) {
            throw new AccesoRechazado("«{$campo}» debe ser una lista de ids o null (todas).", 'alcance_invalido');
        }
        $ids = array_values(array_unique(array_map('intval', $valor)));
        $existen = $ids === [] ? [] : array_map(
            static fn(object $e) => (int) $e->getId(),
            $this->em->getRepository($clase)->findBy(['id' => $ids]),
        );
        $faltan = array_diff($ids, $existen);
        if ($faltan !== []) {
            throw new AccesoRechazado(sprintf('No existen %s con id %s.', $campo, implode(', ', $faltan)), 'alcance_invalido');
        }

        return $ids;
    }

    private static function fecha(mixed $valor, string $campo): ?\DateTimeImmutable
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $valor);
        if ($fecha === false) {
            throw new AccesoRechazado("«{$campo}» debe ser una fecha AAAA-MM-DD.", 'vigencia_invalida');
        }

        return $fecha;
    }

    /**
     * @param array{empresas: array<int, string>, estaciones: array<int, string>} $nombres
     *
     * @return array<string, mixed>
     */
    private function vistaAsignacion(Asignacion $a, \DateTimeImmutable $hoy, array $nombres, PermisosUsuario $actor, int $actorId): array
    {
        $rol = $a->getRol();
        $lista = static fn(?array $ids, array $porId) => $ids === null ? null : array_map(static fn(int $id) => ['id' => $id, 'nombre' => $porId[$id] ?? "#$id"], $ids);
        $agencia = $a->getUsuario()->getAgencia();
        $puede = $actorId !== (int) $a->getUsuario()->getId() && Delegacion::rechazoOtorgar(
            $actor,
            $actorId,
            (int) $a->getUsuario()->getId(),
            $rol->getCapacidades(),
            $rol->isSistema(),
            self::ambito($a),
            $this->nombre(...),
        ) === null;

        return [
            'id' => (int) $a->getId(),
            'rol' => ['id' => (int) $rol->getId(), 'nombre' => $rol->getNombre(), 'descripcion' => $rol->getDescripcion(), 'sistema' => $rol->isSistema()],
            // Un usuario de agencia: el alcance es su agencia.
            'agencia' => $agencia?->getNombre(),
            'empresas' => $lista($a->getEmpresas(), $nombres['empresas']),
            'estaciones' => $lista($a->getEstaciones(), $nombres['estaciones']),
            'desde' => $a->getDesde()?->format('Y-m-d'),
            'hasta' => $a->getHasta()?->format('Y-m-d'),
            'motivo' => $a->getMotivo(),
            'otorgadaPor' => $a->getOtorgadaPor()?->getUsername(),
            'creada' => $a->getCreada()->format(DATE_ATOM),
            'modificada' => $a->getModificada()->format(DATE_ATOM),
            'vigente' => $a->vigente($hoy),
            'puedeEditar' => $puede,
        ];
    }

    /** @return array<string, mixed> */
    private function vistaRol(Role $r, int $usuarios): array
    {
        $definiciones = $this->catalogo->definiciones();

        return [
            'id' => (int) $r->getId(),
            'nombre' => $r->getNombre(),
            'descripcion' => $r->getDescripcion(),
            'sistema' => $r->isSistema(),
            'base' => $r->esBase(),
            // Las que ya no están en el catálogo no se muestran (no conceden nada).
            'capacidades' => array_values(array_filter($r->getCapacidades(), static fn(string $c) => isset($definiciones[$c]))),
            'limites' => (object) $r->getLimites(),
            'usuarios' => $usuarios,
        ];
    }

    /** @return array<string, mixed> */
    private function detalle(Asignacion $a): array
    {
        return [
            'asignacion' => $a->getId(),
            'rol' => $a->getRol()->getNombre(),
            'empresas' => $a->getEmpresas() ?? 'todas',
            'estaciones' => $a->getEstaciones() ?? 'todas',
            'desde' => $a->getDesde()?->format('Y-m-d'),
            'hasta' => $a->getHasta()?->format('Y-m-d'),
            'motivo' => $a->getMotivo(),
        ];
    }

    /** @return array{empresas: array<int, string>, estaciones: array<int, string>} */
    private function nombresLugares(): array
    {
        $empresas = [];
        foreach ($this->em->getRepository(Empresa::class)->findAll() as $e) {
            $empresas[(int) $e->getId()] = (string) ($e->getNombreCorto() ?? $e->getNombre());
        }
        $estaciones = [];
        foreach ($this->em->getRepository(Estacion::class)->findAll() as $e) {
            $estaciones[(int) $e->getId()] = (string) $e->getNombre();
        }

        return ['empresas' => $empresas, 'estaciones' => $estaciones];
    }

    private function nombre(string $codigo): string
    {
        return $this->catalogo->definiciones()[$codigo]->nombre ?? $codigo;
    }

    private function exigir(string|Capacidad $capacidad): void
    {
        if (!$this->autorizacion->puede($capacidad)) {
            throw AccesoRechazado::permiso(Autorizacion::mensaje($capacidad));
        }
    }

    private function actor(): PermisosUsuario
    {
        return $this->autorizacion->permisos();
    }

    private function usuarioActual(): ?Usuario
    {
        return $this->autorizacion->actual();
    }

    private function usuarioActualGestionado(): ?Usuario
    {
        $u = $this->usuarioActual();

        return $u === null ? null : $this->em->find(Usuario::class, $u->getId());
    }

    private function usuario(int $id): Usuario
    {
        return $this->em->find(Usuario::class, $id) ?? throw AccesoRechazado::noExiste('El usuario no existe.');
    }

    private function rol(int $id): Role
    {
        return $this->em->find(Role::class, $id) ?? throw AccesoRechazado::noExiste('El rol no existe.');
    }

    private function asignacion(int $id): Asignacion
    {
        return $this->em->find(Asignacion::class, $id) ?? throw AccesoRechazado::noExiste('La asignación no existe.');
    }

    /** @param list<int> $usuarios */
    private function avisar(array $usuarios): void
    {
        $usuarios = array_values(array_unique($usuarios));
        foreach ($usuarios as $id) {
            $this->autorizacion->olvidar($id);
        }
        $this->avisos->avisar($usuarios, 'acceso', []);
    }
}
