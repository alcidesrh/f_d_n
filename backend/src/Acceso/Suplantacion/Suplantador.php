<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

use App\Acceso\Administracion\BitacoraAcceso;
use App\Acceso\Autorizacion;
use App\Actividad\Detalle;
use App\Actividad\RegistroActividad;
use App\Actividad\RutaNormalizada;
use App\Actividad\TipoActividad;
use App\Bitacora\TipoRegistro;
use App\Chat\Conversaciones;
use App\Entity\ApiToken;
use App\Entity\Enum\OperacionUsuario;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Suplantación de usuarios: un administrador del sistema entra como otro
 * usuario con un token aparte, corto y marcado con quién lo emitió. Como
 * todo lo que puede hacer una petición sale del dueño del token
 * (`Autorizacion`), quien suplanta tiene exactamente las asignaciones del
 * usuario. El inicio y el fin quedan en la bitácora diaria (siempre) y en la
 * de accesos del usuario; cada petición, con `suplantado_por_id`
 * (`App\Actividad\CapturaPeticion`).
 */
final class Suplantador
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
        private readonly AjustesSuplantacion $ajustes,
        private readonly SuplantacionActual $actual,
        private readonly RegistroActividad $actividad,
        private readonly BitacoraAcceso $bitacora,
        private readonly Conversaciones $conversaciones,
        private readonly ClockInterface $reloj,
        private readonly LoggerInterface $logger,
    ) {}

    /** @throws SuplantacionRechazada */
    public function iniciar(Usuario $actor, int $objetivoId, ?string $motivo, Request $request): ApiToken
    {
        $objetivo = $this->em->find(Usuario::class, $objetivoId) ?? throw new SuplantacionRechazada("El usuario no existe.", "usuario_inexistente", 404);
        $config = $this->ajustes->actual();
        $motivo = ReglasSuplantacion::motivo($motivo);
        $rechazo = ReglasSuplantacion::rechazo(
            activa: $config->isActiva(),
            actorEsSistema: $this->autorizacion->de($actor)->sistema(),
            actorYaSuplanta: $this->actual->activa(),
            mismoUsuario: $objetivo->getId() === $actor->getId(),
            objetivoActivo: $objetivo->isActivo(),
            objetivoEsSistema: $this->autorizacion->de($objetivo)->sistema(),
            exigeMotivo: $config->isExigirMotivo(),
            motivo: $motivo,
        );
        if ($rechazo !== null) {
            throw $rechazo;
        }

        // Una suplantación a la vez por administrador: las anteriores se cierran.
        $this->em->createQuery("UPDATE " . ApiToken::class . " t SET t.activo = false WHERE t.suplantadoPor = :actor AND t.activo = true")
            ->setParameter("actor", $actor)
            ->execute();

        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());
        $expira = $ahora->modify(sprintf("+%d minutes", $config->getDuracionMinutos()));
        $token = (new ApiToken())->setUsuario($objetivo)->suplantacion($actor, $motivo)->setActivo(true)->setExpira(\DateTime::createFromImmutable($expira));
        $objetivo->addApiToken($token);
        $this->em->persist($token);
        $this->em->flush();

        $this->anotar($actor, $objetivo, "suplantacion_inicio", $request, ["motivo" => $motivo, "expira" => $expira->format(\DATE_ATOM)]);
        $this->bitacora->anotar(TipoRegistro::USUARIO, (int) $objetivo->getId(), OperacionUsuario::SUPLANTACION_INICIO, $actor, ["motivo" => $motivo, "expira" => $expira->format(\DATE_ATOM)]);
        if ($config->isAvisarUsuario()) {
            $this->avisar($objetivo, sprintf(
                "%s (administrador del sistema) entró a su cuenta el %s%s. Todo lo que haga queda en la bitácora.",
                self::nombre($actor),
                $ahora->setTimezone(new \DateTimeZone("America/Guatemala"))->format("d/m/Y H:i"),
                $motivo === null ? "" : sprintf(". Motivo: «%s»", $motivo),
            ));
        }

        return $token;
    }

    /** Cierra la suplantación de esta petición. @throws SuplantacionRechazada si no la hay */
    public function terminar(Request $request): void
    {
        $token = $this->actual->token() ?? throw new SuplantacionRechazada("No está usando la cuenta de otro usuario.", "sin_suplantacion", 409);
        $actor = $token->getSuplantadoPor();
        $objetivo = $token->getUsuario();
        $token->setActivo(false);
        $this->em->flush();
        if ($actor !== null && $objetivo !== null) {
            $this->anotar($actor, $objetivo, "suplantacion_fin", $request, []);
            $this->bitacora->anotar(TipoRegistro::USUARIO, (int) $objetivo->getId(), OperacionUsuario::SUPLANTACION_FIN, $actor);
        }
        $this->actual->reset();
    }

    /**
     * La suplantación de esta petición, para que la app la muestre (estático:
     * `/api/me/acceso` no necesita el resto de las dependencias).
     *
     * @return array{actor: array{id: int, username: string, nombre: string}, motivo: ?string, expira: ?string, chat: string}|null
     */
    public static function estado(SuplantacionActual $actual, AjustesSuplantacion $ajustes): ?array
    {
        $token = $actual->token();
        $actor = $token?->getSuplantadoPor();
        if ($token === null || $actor === null) {
            return null;
        }

        return [
            "actor" => ["id" => (int) $actor->getId(), "username" => $actor->getUsername(), "nombre" => self::nombre($actor)],
            "motivo" => $token->getMotivo(),
            "expira" => $token->getExpira()?->format(\DATE_ATOM),
            "chat" => $ajustes->actual()->getChat()->value,
        ];
    }

    /**
     * En la bitácora diaria de quien suplanta, aunque la política no siga a
     * nadie: es de seguridad.
     *
     * @param array<string, mixed> $detalle
     */
    private function anotar(Usuario $actor, Usuario $objetivo, string $accion, Request $request, array $detalle): void
    {
        $this->actividad->anotar(TipoActividad::SESION, [
            "usuario_id" => (int) $actor->getId(),
            "accion" => $accion,
            "metodo" => mb_substr($request->getMethod(), 0, 7),
            "ruta" => RutaNormalizada::de($request->getPathInfo())->ruta,
            "estado" => 200,
            "ip" => $request->getClientIp(),
            "agente" => mb_substr((string) $request->headers->get("User-Agent", ""), 0, 255) ?: null,
            "entidad" => "usuario",
            "registro_id" => (int) $objetivo->getId(),
            "detalle" => Detalle::json(["usuario" => $objetivo->getUsername(), ...$detalle], 1024),
        ]);
    }

    private function avisar(Usuario $objetivo, string $texto): void
    {
        try {
            $this->conversaciones->avisarSistema([$objetivo], $texto);
        } catch (\Throwable $e) {
            // El aviso no detiene la suplantación (ya quedó en la bitácora).
            $this->logger->error("No se pudo avisar la suplantación al usuario: {error}", ["error" => $e->getMessage(), "exception" => $e]);
        }
    }

    private static function nombre(Usuario $u): string
    {
        $nombre = trim((string) $u->getFullName());

        return $nombre !== "" ? $nombre : $u->getUsername();
    }
}
