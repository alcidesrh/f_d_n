<?php

declare(strict_types=1);

namespace App\Actividad;

use App\Bitacora\TipoRegistro;
use App\Entity\Base\PersonaBase;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Lectura de la bitácora diaria: quiénes tuvieron actividad un día (con su
 * resumen) y la línea de tiempo de un usuario ese día, que junta su
 * actividad (`actividad`) con lo que cambió en la bitácora de negocio
 * (`bitacora`), en orden.
 */
final class ConsultaBitacoraDiaria
{
    /** Tope de entradas de una línea de tiempo (un día muy cargado se corta y lo dice). */
    public const LIMITE = 5000;

    public function __construct(
        private readonly Connection $db,
        private readonly ResumenDiario $resumen,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Los usuarios con actividad ese día y su resumen. Un día ya cerrado se
     * lee del resumen guardado; el de hoy (o uno sin guardar) se calcula.
     *
     * @return array{dia: string, guardado: bool, usuarios: list<array<string, mixed>>}
     */
    public function dia(\DateTimeInterface $dia): array
    {
        $inicio = ResumenDiario::dia($dia);
        $hoy = ResumenDiario::dia($this->reloj->now());
        $guardado = $inicio < $hoy && $this->resumen->hay($inicio);
        if ($guardado) {
            $filas = $this->db->fetchAllAssociative(
                "SELECT * FROM actividad_resumen_diario WHERE dia = :dia ORDER BY usuario_id",
                ["dia" => $inicio->format("Y-m-d")],
            );
            $filas = array_map(static fn(array $f) => [...$f, "operaciones" => json_decode((string) $f["operaciones"], true) ?: []], $filas);
        } else {
            $filas = $this->resumen->calcular($inicio);
        }
        $nombres = $this->nombres(array_map(static fn(array $f) => (int) $f["usuario_id"], $filas));
        $usuarios = array_map(static fn(array $f) => self::resumen($f, $nombres[(int) $f["usuario_id"]] ?? null), $filas);
        usort($usuarios, static fn(array $a, array $b) => strcmp((string) $a["nombre"], (string) $b["nombre"]));

        return ["dia" => $inicio->format("Y-m-d"), "guardado" => $guardado, "usuarios" => $usuarios];
    }

    /**
     * El día de un usuario: su resumen y la línea de tiempo.
     *
     * @return array{dia: string, usuario: array{id: int, username: ?string, nombre: ?string}, resumen: ?array<string, mixed>, entradas: list<array<string, mixed>>, cortada: bool}
     */
    public function deUsuario(int $usuario, \DateTimeInterface $dia): array
    {
        $inicio = ResumenDiario::dia($dia);
        $fin = $inicio->modify("+1 day");
        $nombre = $this->nombres([$usuario])[$usuario] ?? null;
        $resumen = $this->resumen->calcular($inicio, $usuario)[0] ?? null;

        $actividad = $this->db->fetchAllAssociative(
            "SELECT fecha, tipo, accion, metodo, ruta, estado, duracion_ms, host(ip) AS ip, agente, request_id, entidad, registro_id, detalle
               FROM actividad
              WHERE usuario_id = :usuario AND fecha >= :desde AND fecha < :hasta
              ORDER BY fecha, id
              LIMIT " . (self::LIMITE + 1),
            ["usuario" => $usuario, "desde" => $inicio->format("Y-m-d H:i:sP"), "hasta" => $fin->format("Y-m-d H:i:sP")],
        );
        $negocio = $this->db->fetchAllAssociative(
            "SELECT fecha, entidad, registro_id, operacion, detalle, request_id
               FROM bitacora
              WHERE usuario_id = :usuario AND fecha >= :desde AND fecha < :hasta
              ORDER BY fecha, id
              LIMIT " . (self::LIMITE + 1),
            ["usuario" => $usuario, "desde" => $inicio->format("Y-m-d H:i:s"), "hasta" => $fin->format("Y-m-d H:i:s")],
        );
        $cortada = count($actividad) > self::LIMITE || count($negocio) > self::LIMITE;

        $entradas = [
            ...array_map(self::deActividad(...), array_slice($actividad, 0, self::LIMITE)),
            ...array_map(self::deNegocio(...), array_slice($negocio, 0, self::LIMITE)),
        ];
        usort($entradas, static fn(array $a, array $b) => [$a["fecha"], $a["origen"] === "negocio" ? 1 : 0] <=> [$b["fecha"], $b["origen"] === "negocio" ? 1 : 0]);

        return [
            "dia" => $inicio->format("Y-m-d"),
            "usuario" => ["id" => $usuario, "username" => $nombre["username"] ?? null, "nombre" => $nombre["nombre"] ?? null],
            "resumen" => $resumen === null ? null : self::resumen($resumen, $nombre),
            "entradas" => array_slice($entradas, 0, self::LIMITE),
            "cortada" => $cortada || count($entradas) > self::LIMITE,
        ];
    }

    /**
     * @param array<string, mixed>                              $f
     * @param array{username: ?string, nombre: ?string}|null    $nombre
     *
     * @return array<string, mixed>
     */
    private static function resumen(array $f, ?array $nombre): array
    {
        return [
            "usuarioId" => (int) $f["usuario_id"],
            "username" => $nombre["username"] ?? null,
            "nombre" => $nombre["nombre"] ?? $nombre["username"] ?? sprintf("Usuario %d", (int) $f["usuario_id"]),
            "empresaId" => $f["empresa_id"] !== null ? (int) $f["empresa_id"] : null,
            "estacionId" => $f["estacion_id"] !== null ? (int) $f["estacion_id"] : null,
            "primera" => self::fecha($f["primera"] ?? null),
            "ultima" => self::fecha($f["ultima"] ?? null),
            "minutosActivos" => (int) $f["minutos_activos"],
            "sesiones" => (int) $f["sesiones"],
            "peticiones" => (int) $f["peticiones"],
            "escrituras" => (int) $f["escrituras"],
            "errores" => (int) $f["errores"],
            "sondeos" => (int) $f["sondeos"],
            "pantallas" => (int) $f["pantallas"],
            "cambios" => (int) $f["cambios"],
            "operaciones" => (object) ($f["operaciones"] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $f
     *
     * @return array<string, mixed>
     */
    private static function deActividad(array $f): array
    {
        return [
            "origen" => "actividad",
            "fecha" => self::fecha($f["fecha"]),
            "tipo" => $f["tipo"],
            "accion" => $f["accion"],
            "metodo" => $f["metodo"],
            "ruta" => $f["ruta"],
            "estado" => $f["estado"] !== null ? (int) $f["estado"] : null,
            "duracionMs" => $f["duracion_ms"] !== null ? (int) $f["duracion_ms"] : null,
            "ip" => $f["ip"],
            "agente" => $f["agente"],
            "entidad" => $f["entidad"],
            "registroId" => $f["registro_id"] !== null ? (int) $f["registro_id"] : null,
            "detalle" => $f["detalle"] !== null ? json_decode((string) $f["detalle"], true) : null,
            "requestId" => $f["request_id"],
        ];
    }

    /**
     * @param array<string, mixed> $f
     *
     * @return array<string, mixed>
     */
    private static function deNegocio(array $f): array
    {
        $tipo = TipoRegistro::tryFrom((string) $f["entidad"]);
        $operacion = $tipo?->operaciones()::tryFrom((string) $f["operacion"]);
        $detalle = $f["detalle"] !== null ? json_decode((string) $f["detalle"], true) : null;

        return [
            "origen" => "negocio",
            "fecha" => self::fecha($f["fecha"], true),
            "tipo" => "negocio",
            "accion" => $f["operacion"],
            "etiqueta" => $operacion?->etiqueta() ?? (string) $f["operacion"],
            "entidad" => $f["entidad"],
            "registroId" => (int) $f["registro_id"],
            "detalle" => $detalle,
            "requestId" => $f["request_id"],
        ];
    }

    /** ISO 8601 con zona; `bitacora.fecha` no tiene zona: es hora de Guatemala. */
    private static function fecha(mixed $valor, bool $local = false): ?string
    {
        if ($valor === null || $valor === "") {
            return null;
        }
        $zona = new \DateTimeZone(Particiones::ZONA);
        $f = $local ? new \DateTimeImmutable((string) $valor, $zona) : new \DateTimeImmutable((string) $valor);

        return $f->setTimezone($zona)->format("Y-m-d\\TH:i:s.vP");
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array{username: ?string, nombre: ?string}>
     */
    private function nombres(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $filas = $this->db->fetchAllAssociative(
            "SELECT id, username, nombre, apellido FROM usuario WHERE id IN (?)",
            [array_values(array_unique($ids))],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER],
        );
        $nombres = [];
        foreach ($filas as $f) {
            $completo = PersonaBase::nombreCompletoDe($f["nombre"], $f["apellido"]);
            $nombres[(int) $f["id"]] = ["username" => $f["username"], "nombre" => $completo !== "" ? $completo : $f["username"]];
        }

        return $nombres;
    }
}
