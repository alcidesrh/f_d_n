<?php

declare(strict_types=1);

namespace App\Migration;

use App\Acceso\Legado\ConsolidacionRoles;
use App\Acceso\Legado\EquivalenciasLegado;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Consolida en puestos (ADR-052) las asignaciones que ya puso la migración en
 * una base migrada, sin pasar por el legado: lo que el usuario puede hacer
 * hoy con sus roles migrados (las capacidades de cada rol en la base, menos
 * lo que niega `ROLE_NO_VENDER_VOUCHER`) se cubre con la menor cantidad de
 * puestos, por cada alcance que ya tenía. Las otorgadas a mano no se tocan.
 *
 * Además: deja los puestos con sus capacidades, renombra los que cambian de
 * nombre, pasa las audiencias (menús, administración, listados) de los roles
 * que se retiran a su reemplazo —y al encargado de taquilla las de los que
 * absorbe— y borra los roles retirados que ya nadie tiene.
 *
 * Todo en una transacción. Sin `aplicar`, se deshace al final: sirve para ver
 * el informe antes. Idempotente.
 */
final class ConsolidadorRoles
{
    /** Cambian de nombre conservando el id (y con él menús y configuraciones). */
    public const RENOMBRA = [
        'ROLE_CHECK_BOLETO_WEB' => ConsolidacionRoles::CHEQUEO_BOLETO,
    ];

    /** Tablas de audiencia: tabla => columna del dueño. */
    private const AUDIENCIAS = [
        'menu_role' => 'menu_id',
        'seccion_administracion_role' => 'seccion_administracion_id',
        'enlace_administracion_role' => 'enlace_administracion_id',
        'list_configuration_role' => 'list_configuration_id',
    ];

    public function __construct(private readonly Connection $db) {}

    /**
     * @return array{
     *     antes: int, despues: int, usuarios: int,
     *     puestos: array<string, int>,
     *     capacidades: array<string, array{quita: list<string>, agrega: list<string>}>,
     *     creados: list<string>, renombrados: array<string, string>,
     *     ampliadas: array<int, list<string>>, sinCubrir: array<int, list<string>>, sinCortesia: list<int>,
     *     audiencias: int, eliminados: list<string>, conservados: array<string, int>, cortesiaAMano: int,
     * }
     */
    public function consolidar(bool $aplicar): array
    {
        $informe = [
            'antes' => 0, 'despues' => 0, 'usuarios' => 0, 'puestos' => [], 'capacidades' => [],
            'creados' => [], 'renombrados' => [], 'ampliadas' => [], 'sinCubrir' => [], 'sinCortesia' => [],
            'audiencias' => 0, 'eliminados' => [], 'conservados' => [], 'cortesiaAMano' => 0,
        ];

        $this->db->beginTransaction();
        try {
            // Lo que da cada rol hoy, antes de tocar el catálogo.
            $capacidadesDe = [];
            foreach ($this->db->fetchAllAssociative('SELECT nombre, capacidades::text AS capacidades FROM role') as $r) {
                $capacidadesDe[(string) $r['nombre']] = json_decode((string) $r['capacidades'], true, 512, JSON_THROW_ON_ERROR) ?: [];
            }
            foreach (self::RENOMBRA as $de => $a) {
                if (isset($capacidadesDe[$de]) && !isset($capacidadesDe[$a])) {
                    $capacidadesDe[$a] = $capacidadesDe[$de];
                }
            }

            $this->catalogo($informe);
            $ids = $this->ids();
            $this->asignaciones($capacidadesDe, $ids, $informe);
            $this->cortesiaOtorgadaAMano($ids, $informe);
            $this->audiencias($capacidadesDe, $ids, $informe);
            $this->retirados($informe);

            $aplicar ? $this->db->commit() : $this->db->rollBack();
        } catch (\Throwable $e) {
            if ($this->db->isTransactionActive()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        ksort($informe['puestos']);
        ksort($informe['ampliadas']);
        ksort($informe['sinCubrir']);
        sort($informe['sinCortesia']);

        return $informe;
    }

    /** Puestos con sus capacidades: crea los que faltan, renombra y corrige. */
    private function catalogo(array &$informe): void
    {
        foreach (self::RENOMBRA as $de => $a) {
            $existe = $this->db->fetchOne('SELECT 1 FROM role WHERE nombre = :a', ['a' => $a]) !== false;
            if (!$existe && $this->db->executeStatement('UPDATE role SET nombre = :a WHERE nombre = :de', ['a' => $a, 'de' => $de]) > 0) {
                $informe['renombrados'][$de] = $a;
            }
        }

        foreach (ConsolidacionRoles::PUESTOS as $nombre => [, $capacidades, $descripcion]) {
            $fila = $this->db->fetchAssociative('SELECT id, capacidades::text AS capacidades, descripcion FROM role WHERE nombre = :n', ['n' => $nombre]);
            if ($fila === false) {
                $this->db->executeStatement(
                    "INSERT INTO role (nombre, descripcion, capacidades, limites, sistema) VALUES (:n, :d, CAST(:c AS json), '{}', false)",
                    ['n' => $nombre, 'd' => $descripcion, 'c' => json_encode($capacidades, JSON_THROW_ON_ERROR)],
                );
                $informe['creados'][] = $nombre;
                continue;
            }
            $actuales = json_decode((string) $fila['capacidades'], true, 512, JSON_THROW_ON_ERROR) ?: [];
            $quita = array_values(array_diff($actuales, $capacidades));
            $agrega = array_values(array_diff($capacidades, $actuales));
            if ($quita !== [] || $agrega !== []) {
                $this->db->executeStatement(
                    'UPDATE role SET capacidades = CAST(:c AS json) WHERE id = :id',
                    ['c' => json_encode($capacidades, JSON_THROW_ON_ERROR), 'id' => $fila['id']],
                );
                $informe['capacidades'][$nombre] = ['quita' => $quita, 'agrega' => $agrega];
            }
            if (in_array($nombre, $informe['renombrados'], true)) {
                $this->db->executeStatement('UPDATE role SET descripcion = :d WHERE id = :id', ['d' => $descripcion, 'id' => $fila['id']]);
            }
        }
    }

    /** @return array<string, int> */
    private function ids(): array
    {
        return array_map('intval', $this->db->fetchAllKeyValue('SELECT nombre, id FROM role'));
    }

    /**
     * @param array<string, list<string>> $capacidadesDe
     * @param array<string, int>          $ids
     */
    private function asignaciones(array $capacidadesDe, array $ids, array &$informe): void
    {
        $filas = $this->db->fetchAllAssociative(
            'SELECT a.id, a.usuario_id, r.nombre, a.empresas::text AS empresas, a.estaciones::text AS estaciones, u.agencia_id
             FROM asignacion a JOIN role r ON r.id = a.rol_id JOIN usuario u ON u.id = a.usuario_id
             WHERE a.otorgada_por_id IS NULL AND a.motivo IN (:motivos)
             ORDER BY a.usuario_id, a.id',
            ['motivos' => [RolesUsuarioLegado::MOTIVO, RolesUsuarioLegado::MOTIVO_MODELO_ANTERIOR]],
            ['motivos' => ArrayParameterType::STRING],
        );
        $porUsuario = [];
        foreach ($filas as $f) {
            $porUsuario[(int) $f['usuario_id']][] = $f;
        }

        foreach ($porUsuario as $usuario => $asignaciones) {
            $informe['usuarios']++;
            $informe['antes'] += count($asignaciones);
            $todos = array_values(array_unique(array_column($asignaciones, 'nombre')));
            // Lo renombrado se lee con su nombre nuevo.
            $todos = array_map(static fn(string $n) => self::RENOMBRA[$n] ?? $n, $todos);

            // Por alcance (empresas × estaciones), los de oficina primero.
            $grupos = [];
            foreach ($asignaciones as $a) {
                $empresas = self::conjunto($a['empresas']);
                $estaciones = self::conjunto($a['estaciones']);
                $clave = json_encode([$empresas, $estaciones], JSON_THROW_ON_ERROR);
                $grupos[$clave] ??= ['empresas' => $empresas, 'estaciones' => $estaciones, 'roles' => [], 'ids' => []];
                $grupos[$clave]['roles'][] = self::RENOMBRA[$a['nombre']] ?? $a['nombre'];
                $grupos[$clave]['ids'][] = (int) $a['id'];
            }
            uasort($grupos, static fn(array $a, array $b) => ($a['estaciones'] !== null) <=> ($b['estaciones'] !== null));

            $total = array_values(array_filter($todos, EquivalenciasLegado::total(...)));
            $tieneCortesia = false;
            $oficina = [];
            $nuevas = [];
            foreach ($grupos as $g) {
                $roles = array_values(array_unique($g['roles']));
                if ($total !== []) {
                    $puestos = array_values(array_intersect($roles, $total));
                    $puestos = in_array(EquivalenciasLegado::SISTEMA, $total, true) ? array_values(array_intersect($puestos, [EquivalenciasLegado::SISTEMA])) : $puestos;
                    sort($puestos);
                    $res = ['puestos' => $puestos, 'ampliadas' => [], 'sinCubrir' => []];
                } else {
                    $tipo = $g['empresas'] === null && $g['estaciones'] === null ? null : $g['estaciones'] !== null;
                    $yaTiene = $tipo === true ? ($oficina[json_encode($g['empresas'])] ?? []) : [];
                    $res = ConsolidacionRoles::consolidar($roles, $todos, $tipo, $yaTiene, $capacidadesDe);
                    if ($tipo === false) {
                        $oficina[json_encode($g['empresas'])] = array_merge([], ...array_map(static fn(string $p) => ConsolidacionRoles::capacidades($p) ?? [], $res['puestos']));
                    }
                    foreach ($roles as $r) {
                        $tieneCortesia = $tieneCortesia || in_array('venta.cortesia', $capacidadesDe[$r] ?? [], true);
                    }
                }
                if ($res['ampliadas'] !== []) {
                    $informe['ampliadas'][$usuario] = array_values(array_unique([...($informe['ampliadas'][$usuario] ?? []), ...$res['ampliadas']]));
                }
                if ($res['sinCubrir'] !== []) {
                    $informe['sinCubrir'][$usuario] = array_values(array_unique([...($informe['sinCubrir'][$usuario] ?? []), ...$res['sinCubrir']]));
                }
                $nuevas[] = [$g, $res['puestos']];
            }
            if ($tieneCortesia && $total === [] && array_intersect($todos, array_keys(ConsolidacionRoles::NIEGA)) !== []) {
                $informe['sinCortesia'][] = $usuario;
            }

            foreach ($nuevas as [$g, $puestos]) {
                $actuales = array_values(array_unique($g['roles']));
                sort($actuales);
                $informe['despues'] += count($puestos);
                foreach ($puestos as $p) {
                    $informe['puestos'][$p] = ($informe['puestos'][$p] ?? 0) + 1;
                }
                if ($actuales === $puestos && count($g['ids']) === count($puestos)) {
                    continue;
                }
                $this->db->executeStatement('DELETE FROM asignacion WHERE id IN (:ids)', ['ids' => $g['ids']], ['ids' => ArrayParameterType::INTEGER]);
                foreach ($puestos as $p) {
                    $this->db->executeStatement(
                        'INSERT INTO asignacion (usuario_id, rol_id, empresas, estaciones, motivo, creada, modificada)
                         VALUES (:usuario, :rol, CAST(:empresas AS json), CAST(:estaciones AS json), :motivo, NOW(), NOW())',
                        [
                            'usuario' => $usuario,
                            'rol' => $ids[$p],
                            'empresas' => $g['empresas'] === null ? null : json_encode($g['empresas'], JSON_THROW_ON_ERROR),
                            'estaciones' => $g['estaciones'] === null ? null : json_encode($g['estaciones'], JSON_THROW_ON_ERROR),
                            'motivo' => RolesUsuarioLegado::MOTIVO,
                        ],
                    );
                }
            }
        }
    }

    /**
     * Lo otorgado a mano con un puesto que pierde la cortesía (ahora es un
     * puesto aparte) la conserva: se le otorga la emisión de cortesías con el
     * mismo alcance, vigencia, motivo y quien la otorgó.
     *
     * @param array<string, int> $ids
     */
    private function cortesiaOtorgadaAMano(array $ids, array &$informe): void
    {
        $pierden = array_keys(array_filter($informe['capacidades'], static fn(array $d) => in_array('venta.cortesia', $d['quita'], true)));
        if ($pierden === []) {
            return;
        }
        $informe['cortesiaAMano'] = $this->db->executeStatement(
            'INSERT INTO asignacion (usuario_id, rol_id, empresas, estaciones, desde, hasta, motivo, otorgada_por_id, creada, modificada)
             SELECT a.usuario_id, :emisor, a.empresas, a.estaciones, a.desde, a.hasta, a.motivo, a.otorgada_por_id, NOW(), NOW()
             FROM asignacion a JOIN role r ON r.id = a.rol_id
             WHERE r.nombre IN (:pierden) AND NOT (a.otorgada_por_id IS NULL AND a.motivo IN (:motivos))
               AND NOT EXISTS (
                   SELECT 1 FROM asignacion b WHERE b.usuario_id = a.usuario_id AND b.rol_id = :emisor
                   AND b.empresas::text IS NOT DISTINCT FROM a.empresas::text AND b.estaciones::text IS NOT DISTINCT FROM a.estaciones::text)',
            [
                'emisor' => $ids[ConsolidacionRoles::EMISOR_CORTESIA],
                'pierden' => $pierden,
                'motivos' => [RolesUsuarioLegado::MOTIVO, RolesUsuarioLegado::MOTIVO_MODELO_ANTERIOR],
            ],
            ['pierden' => ArrayParameterType::STRING, 'motivos' => ArrayParameterType::STRING],
        );
    }

    /**
     * Quien nombraba a un rol retirado en una audiencia nombra a su
     * reemplazo; y donde se nombra a uno que absorbe el encargado de
     * taquilla (da todo lo que daba, sin contar la cortesía), también a él.
     *
     * @param array<string, list<string>> $capacidadesDe
     * @param array<string, int>          $ids
     */
    private function audiencias(array $capacidadesDe, array $ids, array &$informe): void
    {
        $encargado = ConsolidacionRoles::capacidades(ConsolidacionRoles::ENCARGADO_TAQUILLA) ?? [];
        $reemplazos = [];
        foreach ($capacidadesDe as $rol => $caps) {
            $nombre = self::RENOMBRA[$rol] ?? $rol;
            $a = [];
            if (($reemplazo = ConsolidacionRoles::RETIRADOS[$rol] ?? null) !== null) {
                $a[] = $reemplazo;
            }
            $esEstacion = EquivalenciasLegado::porEstacion($rol) || ConsolidacionRoles::porEstacion($nombre);
            if ($esEstacion && $caps !== [] && $nombre !== ConsolidacionRoles::ENCARGADO_TAQUILLA
                && array_diff($caps, $encargado, ['venta.cortesia']) === []) {
                $a[] = ConsolidacionRoles::ENCARGADO_TAQUILLA;
            }
            foreach (array_unique($a) as $r) {
                if (isset($ids[$nombre], $ids[$r]) && $r !== $nombre) {
                    $reemplazos[] = [$ids[$nombre], $ids[$r]];
                }
            }
        }

        foreach (self::AUDIENCIAS as $tabla => $dueno) {
            foreach ($reemplazos as [$de, $a]) {
                $sql = $tabla === 'list_configuration_role'
                    // Un rol, en una sola configuración por entidad (ADR-043).
                    ? "INSERT INTO list_configuration_role (list_configuration_id, role_id)
                       SELECT x.list_configuration_id, :a FROM list_configuration_role x JOIN list_configuration c ON c.id = x.list_configuration_id
                       WHERE x.role_id = :de AND NOT EXISTS (
                           SELECT 1 FROM list_configuration_role y JOIN list_configuration d ON d.id = y.list_configuration_id
                           WHERE y.role_id = :a AND d.entity_config_id = c.entity_config_id)"
                    : "INSERT INTO $tabla ($dueno, role_id)
                       SELECT x.$dueno, :a FROM $tabla x
                       WHERE x.role_id = :de AND NOT EXISTS (SELECT 1 FROM $tabla y WHERE y.$dueno = x.$dueno AND y.role_id = :a)";
                $informe['audiencias'] += $this->db->executeStatement($sql, ['de' => $de, 'a' => $a]);
            }
        }
    }

    /** Borra los retirados que ya nadie tiene; los demás quedan para revisarlos. */
    private function retirados(array &$informe): void
    {
        foreach (array_keys(ConsolidacionRoles::RETIRADOS) as $rol) {
            $fila = $this->db->fetchAssociative(
                'SELECT r.id, (SELECT COUNT(*) FROM asignacion a WHERE a.rol_id = r.id) AS n FROM role r WHERE r.nombre = :n',
                ['n' => $rol],
            );
            if ($fila === false) {
                continue;
            }
            if ((int) $fila['n'] > 0) {
                $informe['conservados'][$rol] = (int) $fila['n'];
                continue;
            }
            $this->db->executeStatement('DELETE FROM role WHERE id = :id', ['id' => $fila['id']]);
            $informe['eliminados'][] = $rol;
        }
    }

    /** @return list<int>|null JSON de la base a conjunto ordenado (null = todas) */
    private static function conjunto(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }
        $ids = array_values(array_unique(array_map('intval', json_decode($json, true, 512, JSON_THROW_ON_ERROR) ?? [])));
        sort($ids);

        return $ids;
    }
}
