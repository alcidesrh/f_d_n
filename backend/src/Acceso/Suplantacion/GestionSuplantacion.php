<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

use App\Entity\ConfiguracionSuplantacion;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Ver y cambiar `ConfiguracionSuplantacion` desde la administración (solo el
 * administrador del sistema): si está activa, cuánto dura, qué se puede en
 * el chat y el correo, si se avisa al usuario y si se exige un motivo.
 */
final class GestionSuplantacion
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AjustesSuplantacion $ajustes,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array<string, mixed> */
    public function datos(): array
    {
        $c = $this->ajustes->actual();

        return [
            "activa" => $c->isActiva(),
            "duracionMinutos" => $c->getDuracionMinutos(),
            "chat" => $c->getChat()->value,
            "avisarUsuario" => $c->isAvisarUsuario(),
            "exigirMotivo" => $c->isExigirMotivo(),
            "actualizadaEn" => $c->getActualizadaEn()?->format(\DATE_ATOM),
            "actualizadaPor" => $c->getActualizadaPor()?->getLabel(),
            "catalogo" => [
                "chat" => array_map(static fn(ModoChat $m) => ["valor" => $m->value, "etiqueta" => $m->etiqueta()], ModoChat::cases()),
                "duracion" => ["min" => ReglasSuplantacion::DURACION_MIN, "max" => ReglasSuplantacion::DURACION_MAX],
            ],
        ];
    }

    /**
     * Cambia solo lo que viene.
     *
     * @param array<string, mixed> $datos
     *
     * @throws SuplantacionRechazada si algo no es válido (no guarda nada)
     */
    public function guardar(array $datos, ?Usuario $por): ConfiguracionSuplantacion
    {
        $config = $this->em->find(ConfiguracionSuplantacion::class, ConfiguracionSuplantacion::ID);
        if ($config === null) {
            $config = new ConfiguracionSuplantacion();
            $this->em->persist($config);
        }
        if (array_key_exists("activa", $datos)) {
            $config->setActiva((bool) $datos["activa"]);
        }
        if (array_key_exists("duracionMinutos", $datos)) {
            $minutos = filter_var($datos["duracionMinutos"], FILTER_VALIDATE_INT);
            if ($minutos === false || $minutos < ReglasSuplantacion::DURACION_MIN || $minutos > ReglasSuplantacion::DURACION_MAX) {
                throw new SuplantacionRechazada(sprintf("La duración debe ser de %d a %d minutos.", ReglasSuplantacion::DURACION_MIN, ReglasSuplantacion::DURACION_MAX), "duracion_invalida");
            }
            $config->setDuracionMinutos($minutos);
        }
        if (array_key_exists("chat", $datos)) {
            $config->setChat(ModoChat::tryFrom((string) $datos["chat"]) ?? throw new SuplantacionRechazada("El modo del chat debe ser permitir, lectura o bloquear.", "chat_invalido"));
        }
        if (array_key_exists("avisarUsuario", $datos)) {
            $config->setAvisarUsuario((bool) $datos["avisarUsuario"]);
        }
        if (array_key_exists("exigirMotivo", $datos)) {
            $config->setExigirMotivo((bool) $datos["exigirMotivo"]);
        }
        $config->actualizada($por, \DateTimeImmutable::createFromInterface($this->reloj->now()));
        $this->em->flush();

        return $config;
    }
}
