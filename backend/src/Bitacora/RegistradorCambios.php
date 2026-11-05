<?php

declare(strict_types=1);

namespace App\Bitacora;

use App\Actividad\AjustesBitacora;
use App\Actividad\RegistroActividad;
use App\Entity\ApiToken;
use App\Entity\AvisoOperativo;
use App\Entity\Bitacora;
use App\Entity\ChatArchivo;
use App\Entity\ChatContactoFijado;
use App\Entity\ChatMensaje;
use App\Entity\ChatMiembro;
use App\Entity\CorreoAdjunto;
use App\Entity\CorreoMensaje;
use App\Entity\EnvioMensajePredeterminado;
use App\Entity\EnvioReporte;
use App\Entity\EstadoTarea;
use App\Entity\LecturaGps;
use App\Entity\LecturaGpsPrueba;
use App\Entity\ReservaAsiento;
use App\Entity\RevisionMetadata;
use App\Entity\SuscripcionPush;
use App\Entity\Usuario;
use App\Entity\Enum\OperacionCambio;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Bitácora de negocio del resto de las entidades: lo que un usuario crea,
 * cambia (campo a campo, antes → después) o elimina de cualquier entidad que
 * no tiene bitácora propia (`TipoRegistro::deObjeto()`). Va a `bitacora` con
 * `entidad = cambio` y la clase en el detalle, en la misma transacción del
 * flush, y se ve en la bitácora diaria del usuario.
 *
 * Solo con un usuario en sesión (lo del sistema, la consola y la migración
 * no tiene autor) y si la configuración lo deja (`cambiosEntidades`). Sigue
 * para todos los usuarios, sin selección: es auditoría. Los campos que
 * parecen secretos quedan como `***`; las entidades que cambian solas o en
 * masa (lecturas GPS, chat, tokens) no se anotan.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postRemove)]
final class RegistradorCambios implements ResetInterface
{
    /** Entidades que no se anotan: ruido, volumen o ya tienen su propio registro. */
    public const EXCLUIDAS = [
        Bitacora::class, ApiToken::class, LecturaGps::class, LecturaGpsPrueba::class, RevisionMetadata::class,
        ChatMensaje::class, ChatMiembro::class, ChatArchivo::class, ChatContactoFijado::class,
        CorreoMensaje::class, CorreoAdjunto::class, EstadoTarea::class, AvisoOperativo::class,
        EnvioReporte::class, EnvioMensajePredeterminado::class, ReservaAsiento::class, SuscripcionPush::class,
    ];

    /** Campos que nunca se anotan (cambian solos). */
    private const CAMPOS_IGNORADOS = ["updatedAt", "createdAt", "actualizadaEn", "ultimoAcceso"];

    private const SECRETO = '/pass|clave|token|secret|cifrad|credencial/i';

    /** @var \WeakMap<object, array{id: int|string|null, detalle: array<string, mixed>}> */
    private \WeakMap $porEliminar;

    public function __construct(
        private readonly Security $security,
        private readonly AjustesBitacora $ajustes,
        private readonly RegistroActividad $actividad,
    ) {
        $this->reset();
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $e = $args->getObject();
        if (!$this->anota($e)) {
            return;
        }
        $this->escribir($args->getObjectManager()->getConnection(), $e, self::id($e), OperacionCambio::CREADO, []);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $e = $args->getObject();
        if (!$this->anota($e)) {
            return;
        }
        $cambios = self::cambios($args->getEntityChangeSet());
        if ($cambios !== []) {
            $this->escribir($args->getObjectManager()->getConnection(), $e, self::id($e), OperacionCambio::MODIFICADO, ["cambios" => $cambios]);
        }
    }

    /** El id de una entidad borrada ya no está en `postRemove`: se toma antes. */
    public function onFlush(OnFlushEventArgs $args): void
    {
        foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityDeletions() as $e) {
            if ($this->anota($e)) {
                $this->porEliminar[$e] = ["id" => self::id($e), "detalle" => []];
            }
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $e = $args->getObject();
        $pendiente = $this->porEliminar[$e] ?? null;
        if ($pendiente === null) {
            return;
        }
        unset($this->porEliminar[$e]);
        $this->escribir($args->getObjectManager()->getConnection(), $e, $pendiente["id"], OperacionCambio::ELIMINADO, $pendiente["detalle"]);
    }

    public function reset(): void
    {
        $this->porEliminar = new \WeakMap();
    }

    /**
     * El conjunto de cambios listo para el detalle: sin campos ignorados, los
     * secretos tapados y cada valor reducido a algo legible.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeset
     *
     * @return array<string, array{de: mixed, a: mixed}>
     */
    public static function cambios(array $changeset): array
    {
        $cambios = [];
        foreach ($changeset as $campo => [$antes, $despues]) {
            if (in_array($campo, self::CAMPOS_IGNORADOS, true)) {
                continue;
            }
            $cambios[$campo] = preg_match(self::SECRETO, $campo)
                ? ["de" => "***", "a" => "***"]
                : ["de" => self::valor($antes), "a" => self::valor($despues)];
        }

        return $cambios;
    }

    /** Un valor del conjunto de cambios, para el detalle (JSON). */
    public static function valor(mixed $v): mixed
    {
        return match (true) {
            $v === null, is_int($v), is_float($v), is_bool($v) => $v,
            is_string($v) => mb_substr($v, 0, 200),
            $v instanceof \BackedEnum => $v->value,
            $v instanceof \DateTimeInterface => $v->format("Y-m-d H:i"),
            $v instanceof \Money\Money => $v->getAmount(),
            is_object($v) && method_exists($v, "getLabel") => mb_substr((string) $v->getLabel(), 0, 120),
            is_object($v) && method_exists($v, "getId") => $v->getId(),
            is_array($v) => mb_substr((string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), 0, 200),
            default => null,
        };
    }

    /** Nombre corto de la clase (sin el proxy de Doctrine). */
    public static function clase(object $e): string
    {
        $clase = $e::class;
        if (($p = strrpos($clase, "\\__CG__\\")) !== false) {
            $clase = substr($clase, $p + 8);
        }

        return substr($clase, (int) strrpos($clase, "\\") + 1);
    }

    private function anota(object $e): bool
    {
        if (!str_starts_with($e::class, "App\\Entity\\") && !str_contains($e::class, "\\__CG__\\App\\Entity\\")) {
            return false;
        }
        foreach (self::EXCLUIDAS as $excluida) {
            if ($e instanceof $excluida) {
                return false;
            }
        }
        if (TipoRegistro::deObjeto($e) !== null || !$this->security->getUser() instanceof Usuario) {
            return false;
        }

        return $this->ajustes->politica()->cambiosEntidades;
    }

    private static function id(object $e): int|string|null
    {
        return method_exists($e, "getId") ? $e->getId() : null;
    }

    /** @param array<string, mixed> $detalle */
    private function escribir(Connection $conexion, object $e, int|string|null $id, OperacionCambio $operacion, array $detalle): void
    {
        $usuario = $this->security->getUser();
        if (!$usuario instanceof Usuario) {
            return;
        }
        $detalle = [
            "clase" => self::clase($e),
            "id" => $id,
            "etiqueta" => method_exists($e, "getLabel") ? mb_substr((string) $e->getLabel(), 0, 120) : null,
            ...$detalle,
        ];
        $conexion->insert(
            "bitacora",
            [
                "entidad" => TipoRegistro::CAMBIO->value,
                "registro_id" => is_int($id) ? $id : 0,
                "operacion" => $operacion->value,
                "usuario_id" => $usuario->getId(),
                "usuario_nombre" => mb_substr((string) ($usuario->getFullName() ?: $usuario->getUsername()), 0, 100),
                "fecha" => new \DateTimeImmutable(),
                "detalle" => json_encode(array_filter($detalle, static fn(mixed $v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                "request_id" => $this->actividad->requestId(),
            ],
            ["fecha" => Types::DATETIME_IMMUTABLE],
        );
    }
}
