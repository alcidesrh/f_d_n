<?php

declare(strict_types=1);

namespace App\Acceso\Legado;

use App\Acceso\Administracion\BitacoraAcceso;
use App\Acceso\Ambito;
use App\Acceso\Catalogo\Catalogo;
use App\Acceso\Concesion;
use App\Acceso\PermisosUsuario;
use App\Bitacora\TipoRegistro;
use App\Entity\Enum\OperacionRol;
use App\Entity\Enum\OperacionUsuario;
use App\Migration\RolesUsuarioLegado;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Deja los roles del legado en puestos (ADR-052), sin cambiar lo que puede
 * nadie: fusiona los que son el mismo, quita las asignaciones migradas que
 * sobran (`ConsolidacionRoles`) y borra los roles sin puesto que quedaron sin
 * uso. Lo otorgado a mano y lo temporal no se quita. Idempotente.
 *
 * Corre dentro de la transacción de quien llama. Compara lo que puede cada
 * usuario antes y después, y lanza una excepción si cambió algo.
 */
final class ConsolidadorRoles
{
    /** @var array<string, string> tabla de audiencia => su columna */
    private const AUDIENCIAS = [
        'menu_role' => 'menu_id',
        'list_configuration_role' => 'list_configuration_id',
        'seccion_administracion_role' => 'seccion_administracion_id',
        'enlace_administracion_role' => 'enlace_administracion_id',
    ];
    private const MOTIVO = 'Consolidación de roles (ADR-052)';

    public function __construct(
        private readonly Connection $db,
        private readonly Catalogo $catalogo,
        private readonly BitacoraAcceso $bitacora,
    ) {}

    /**
     * @return array{fusionados: int, quitadas: int, eliminados: int, conservados: list<string>, sinFusionar: list<string>, estaciones: list<int>}
     *
     * @throws \RuntimeException si a alguien le cambiaría lo que puede
     */
    public function consolidar(): array
    {
        $antes = $this->permisos();
        $resultado = ['fusionados' => 0, 'quitadas' => 0, 'eliminados' => 0, 'conservados' => [], 'sinFusionar' => [], 'estaciones' => []];

        foreach (array_unique(EquivalenciasLegado::FUSIONES) as $destino) {
            $this->fusionar($destino, array_keys(EquivalenciasLegado::FUSIONES, $destino, true), $resultado);
        }
        $resultado['quitadas'] = $this->quitarSobrantes();
        foreach ([EquivalenciasLegado::SIN_EFECTO, ...EquivalenciasLegado::ABSORBIDOS] as $nombre) {
            $this->eliminarSinUso($nombre, $resultado);
        }

        $despues = $this->permisos();
        foreach ($antes as $usuario => $permisos) {
            $ahora = $despues[$usuario] ?? PermisosUsuario::ninguno();
            $cambios = ConsolidacionRoles::diferencias($permisos, $ahora);
            if ($permisos->general()->empresas() !== $ahora->general()->empresas()) {
                $cambios[] = 'empresas visibles';
            }
            if ($cambios !== []) {
                throw new \RuntimeException(sprintf('La consolidación le cambiaría al usuario %d: %s.', $usuario, implode(', ', $cambios)));
            }
            if ($permisos->general()->estaciones() !== $ahora->general()->estaciones()) {
                $resultado['estaciones'][] = $usuario;
            }
        }

        return $resultado;
    }

    /**
     * @param list<string>                                                $origenes
     * @param array{fusionados: int, sinFusionar: list<string>, ...}      $resultado
     */
    private function fusionar(string $destino, array $origenes, array &$resultado): void
    {
        $roles = $this->roles([$destino, ...$origenes]);
        $origenes = array_values(array_filter($origenes, static fn(string $o) => isset($roles[$o])));
        if ($origenes === []) {
            return;
        }
        $iguales = array_unique(array_map(self::huella(...), $roles));
        if (count($iguales) > 1) {
            // Alguien editó uno: ya no son el mismo puesto y juntarlos cambiaría lo que pueden.
            $resultado['sinFusionar'][] = $destino;

            return;
        }
        if (!isset($roles[$destino])) {
            $this->db->update('role', ['nombre' => $destino], ['id' => $roles[$origenes[0]]['id']]);
            $roles[$destino] = $roles[$origenes[0]];
            array_shift($origenes);
        }
        foreach ($origenes as $origen) {
            $parametros = ['destino' => $roles[$destino]['id'], 'origen' => $roles[$origen]['id']];
            $this->db->executeStatement('UPDATE asignacion SET rol_id = :destino WHERE rol_id = :origen', $parametros);
            foreach (self::AUDIENCIAS as $tabla => $columna) {
                $this->db->executeStatement("INSERT INTO $tabla ($columna, role_id) SELECT $columna, :destino FROM $tabla WHERE role_id = :origen ON CONFLICT DO NOTHING", $parametros);
            }
            $this->eliminar($origen, $roles[$origen]);
            $resultado['fusionados']++;
        }
    }

    private function quitarSobrantes(): int
    {
        $porUsuario = [];
        foreach ($this->filas('WHERE a.desde IS NULL AND a.hasta IS NULL') as $f) {
            $porUsuario[(int) $f['usuario_id']][] = $f;
        }
        $audiencias = $this->audiencias();
        $quitadas = 0;
        foreach ($porUsuario as $usuario => $filas) {
            $tocables = array_values(array_map(
                static fn(array $f) => (int) $f['id'],
                array_filter($filas, static fn(array $f) => $f['otorgada_por_id'] === null && in_array($f['motivo'], [RolesUsuarioLegado::MOTIVO, RolesUsuarioLegado::MOTIVO_MODELO_ANTERIOR], true)),
            ));
            $sobran = ConsolidacionRoles::sobrantes(array_map(self::concesion(...), $filas), $tocables, $audiencias);
            foreach ($filas as $f) {
                if (in_array((int) $f['id'], $sobran, true)) {
                    $this->bitacora->anotar(TipoRegistro::USUARIO, $usuario, OperacionUsuario::ASIGNACION_REVOCADA, null, [
                        'rol' => $f['nombre'],
                        'empresas' => self::lista($f['empresas']) ?? 'todas',
                        'estaciones' => self::lista($f['estaciones']) ?? 'todas',
                        'motivo' => self::MOTIVO,
                    ]);
                }
            }
            if ($sobran !== []) {
                $quitadas += $this->db->executeStatement('DELETE FROM asignacion WHERE id IN (:ids)', ['ids' => $sobran], ['ids' => ArrayParameterType::INTEGER]);
            }
        }

        return $quitadas;
    }

    /**
     * Con asignaciones o en una audiencia se conserva: borrarlo dejaría a
     * alguien sin lo suyo, o abriría a todos una audiencia de un solo rol.
     *
     * @param array{eliminados: int, conservados: list<string>, ...} $resultado
     */
    private function eliminarSinUso(string $nombre, array &$resultado): void
    {
        $rol = $this->roles([$nombre])[$nombre] ?? null;
        if ($rol === null) {
            return;
        }
        $usos = (int) $this->db->fetchOne('SELECT COUNT(*) FROM asignacion WHERE rol_id = :id', ['id' => $rol['id']]);
        foreach (self::AUDIENCIAS as $tabla => $_) {
            $usos += (int) $this->db->fetchOne("SELECT COUNT(*) FROM $tabla WHERE role_id = :id", ['id' => $rol['id']]);
        }
        if ($usos > 0) {
            $resultado['conservados'][] = $nombre;

            return;
        }
        $this->eliminar($nombre, $rol);
        $resultado['eliminados']++;
    }

    /** @param array{id: int, capacidades: list<string>, limites: array<string, int>} $rol */
    private function eliminar(string $nombre, array $rol): void
    {
        $this->bitacora->anotar(TipoRegistro::ROL, $rol['id'], OperacionRol::ELIMINADO, null, [
            'nombre' => $nombre,
            'capacidades' => $rol['capacidades'],
            'motivo' => self::MOTIVO,
        ]);
        $this->db->delete('role', ['id' => $rol['id']]);
    }

    /**
     * @param list<string> $nombres
     *
     * @return array<string, array{id: int, capacidades: list<string>, limites: array<string, int>}>
     */
    private function roles(array $nombres): array
    {
        $roles = [];
        $filas = $this->db->fetchAllAssociative('SELECT id, nombre, capacidades, limites FROM role WHERE nombre IN (:nombres)', ['nombres' => $nombres], ['nombres' => ArrayParameterType::STRING]);
        foreach ($filas as $f) {
            $roles[(string) $f['nombre']] = [
                'id' => (int) $f['id'],
                'capacidades' => json_decode((string) $f['capacidades'], true, flags: JSON_THROW_ON_ERROR),
                'limites' => json_decode((string) $f['limites'], true, flags: JSON_THROW_ON_ERROR),
            ];
        }

        return $roles;
    }

    /** @param array{capacidades: list<string>, limites: array<string, int>} $rol */
    private static function huella(array $rol): string
    {
        $capacidades = $rol['capacidades'];
        $limites = $rol['limites'];
        sort($capacidades);
        ksort($limites);

        return json_encode([$capacidades, $limites], JSON_THROW_ON_ERROR);
    }

    /** @return list<list<int>> */
    private function audiencias(): array
    {
        $audiencias = [];
        foreach (self::AUDIENCIAS as $tabla => $columna) {
            foreach ($this->db->fetchAllAssociative("SELECT $columna AS id, role_id FROM $tabla") as $f) {
                $audiencias["$tabla:{$f['id']}"][] = (int) $f['role_id'];
            }
        }

        return array_values($audiencias);
    }

    /** @return array<int, PermisosUsuario> todas las asignaciones de cada usuario, vigentes o no */
    private function permisos(): array
    {
        $porUsuario = [];
        foreach ($this->filas() as $f) {
            $porUsuario[(int) $f['usuario_id']][] = self::concesion($f);
        }

        return array_map(fn(array $c) => new PermisosUsuario($c, $this->catalogo->codigos()), $porUsuario);
    }

    /** @return list<array<string, mixed>> */
    private function filas(string $condicion = ''): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT a.id, a.usuario_id, a.rol_id, a.empresas, a.estaciones, a.motivo, a.otorgada_por_id, r.nombre, r.sistema, r.capacidades, r.limites
             FROM asignacion a JOIN role r ON r.id = a.rol_id $condicion ORDER BY a.id",
        );
    }

    /** @param array<string, mixed> $f */
    private static function concesion(array $f): Concesion
    {
        return new Concesion(
            (int) $f['id'],
            (int) $f['rol_id'],
            (string) $f['nombre'],
            (bool) $f['sistema'],
            json_decode((string) $f['capacidades'], true, flags: JSON_THROW_ON_ERROR),
            json_decode((string) $f['limites'], true, flags: JSON_THROW_ON_ERROR),
            new Ambito(self::lista($f['empresas']), self::lista($f['estaciones'])),
        );
    }

    /** @return list<int>|null */
    private static function lista(mixed $json): ?array
    {
        return $json === null ? null : json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
    }
}
