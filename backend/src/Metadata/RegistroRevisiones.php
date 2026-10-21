<?php

declare(strict_types=1);

namespace App\Metadata;

use App\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Revisiones de la metadata del cliente (`revision_metadata`, ADR-036): lee
 * la vigente y crea la siguiente, una a la vez entre procesos (cerrojo de
 * PostgreSQL). Escribe con DBAL en su propia transacción: no se mezcla con lo
 * que el EntityManager de la petición tenga a medias.
 *
 * Tras confirmar avisa por Mercure en `TOPICO` solo con el número: cada
 * navegador pide el manifiesto y decide qué le falta.
 */
final class RegistroRevisiones
{
    /** Tópico público: el aviso no lleva más que el número de la revisión. */
    public const TOPICO = "/metadata";

    /** La cabecera `X-Metadata-Revision` repite el número sin consultar la base en cada respuesta. */
    private const MEMORIA_SEGUNDOS = 2.0;

    public readonly ModoPublicacion $modo;

    /** @var array{0: int, 1: float}|null número vigente y hasta cuándo vale */
    private ?array $memoria = null;

    public function __construct(
        private readonly Connection $conexion,
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
        #[Autowire(env: "default:metadata_publicacion_defecto:METADATA_PUBLICACION")]
        string $modo,
    ) {
        $this->modo = ModoPublicacion::desde($modo);
    }

    /** La última revisión; null si todavía no hay ninguna. */
    public function vigente(): ?Estado
    {
        $fila = $this->conexion->fetchAssociative("SELECT id, estado FROM revision_metadata ORDER BY id DESC LIMIT 1");
        if ($fila === false) {
            return null;
        }

        return Estado::desde((int) $fila["id"], (array) json_decode((string) $fila["estado"], true, 512, JSON_THROW_ON_ERROR));
    }

    /** Número de la vigente (0 sin ninguna), recordado unos segundos por proceso. */
    public function numeroVigente(): int
    {
        if ($this->memoria !== null && $this->memoria[1] > microtime(true)) {
            return $this->memoria[0];
        }
        $numero = (int) $this->conexion->fetchOne("SELECT COALESCE(MAX(id), 0) FROM revision_metadata");
        $this->recordar($numero);

        return $numero;
    }

    /**
     * Crea la revisión si hay algo que publicar y avisa a los navegadores. Sin
     * cambios ni aviso ni recarga completa no crea nada (salvo la primera).
     *
     * @param string|null $esquema huella del schema; null: no se compara
     * @param array<string, ?string>|null $huellas ver `Estado::cambios()`
     *
     * @return array{Estado, Cambios}|null la nueva y lo que cambió
     */
    public function registrar(
        Origen $origen,
        ?string $esquema,
        ?array $huellas,
        bool $todas = false,
        ?Usuario $usuario = null,
        ?string $nota = null,
        bool $aviso = false,
        bool $completa = false,
    ): ?array {
        $this->conexion->beginTransaction();
        try {
            // Dos procesos que publican a la vez no comparan contra la misma anterior.
            $this->conexion->executeQuery("SELECT pg_advisory_xact_lock(hashtext('revision_metadata'))");
            $anterior = $this->vigente() ?? Estado::inicial();
            $cambios = $anterior->cambios($esquema, $huellas, $todas);
            if ($anterior->revision > 0 && $cambios->vacio() && !$aviso && !$completa) {
                $this->conexion->commit();

                return null;
            }
            $numero = (int) $this->conexion->fetchOne("SELECT nextval(pg_get_serial_sequence('revision_metadata', 'id'))");
            $estado = $anterior->siguiente($numero, $cambios, $esquema, $huellas, $completa, $aviso, $nota);
            $this->conexion->insert(
                "revision_metadata",
                [
                    "id" => $numero,
                    "estado" => json_encode($estado->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    "cambios" => json_encode($cambios->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    "origen" => $origen->value,
                    "aviso" => $aviso,
                    "completa" => $completa,
                    "nota" => $nota,
                    "usuario_id" => $usuario?->getId(),
                    "usuario_nombre" => $usuario === null ? null : mb_substr((string) ($usuario->getFullName() ?: $usuario->getUsername()), 0, 100),
                    "creada_en" => new \DateTimeImmutable(),
                ],
                ["aviso" => Types::BOOLEAN, "completa" => Types::BOOLEAN, "creada_en" => Types::DATETIME_IMMUTABLE],
            );
            $this->conexion->commit();
        } catch (\Throwable $e) {
            if ($this->conexion->isTransactionActive()) {
                $this->conexion->rollBack();
            }

            throw $e;
        }
        $this->recordar($numero);
        $this->avisar($numero);

        return [$estado, $cambios];
    }

    /**
     * Las últimas revisiones, de la más nueva a la más vieja.
     *
     * @return list<array{revision: int, origen: string, creadaEn: string, usuario: ?string, nota: ?string, aviso: bool, completa: bool, cambios: array<string, mixed>}>
     */
    public function historial(int $limite = 20): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT id, origen, creada_en, usuario_nombre, nota, aviso, completa, cambios FROM revision_metadata ORDER BY id DESC LIMIT ?",
            [$limite],
            [ParameterType::INTEGER],
        );

        return array_map(static fn(array $fila): array => [
            "revision" => (int) $fila["id"],
            "origen" => (string) $fila["origen"],
            "creadaEn" => (new \DateTimeImmutable((string) $fila["creada_en"]))->format(DATE_ATOM),
            "usuario" => $fila["usuario_nombre"] === null ? null : (string) $fila["usuario_nombre"],
            "nota" => $fila["nota"] === null ? null : (string) $fila["nota"],
            "aviso" => (bool) $fila["aviso"],
            "completa" => (bool) $fila["completa"],
            "cambios" => Cambios::desde((array) json_decode((string) $fila["cambios"], true, 512, JSON_THROW_ON_ERROR))->toArray(),
        ], $filas);
    }

    private function recordar(int $numero): void
    {
        $this->memoria = [$numero, microtime(true) + self::MEMORIA_SEGUNDOS];
    }

    private function avisar(int $numero): void
    {
        try {
            $this->hub->publish(new Update(self::TOPICO, json_encode(["revision" => $numero], JSON_THROW_ON_ERROR)));
        } catch (\Throwable $e) {
            // Sin Mercure se enteran igual: por la cabecera de la próxima respuesta o al volver a la pestaña.
            $this->logger->warning("No se pudo avisar la revisión {revision} de la metadata: {error}", [
                "revision" => $numero,
                "error" => $e->getMessage(),
            ]);
        }
    }
}
