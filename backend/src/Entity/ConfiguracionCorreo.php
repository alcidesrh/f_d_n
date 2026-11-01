<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Configuración del correo (ADR-048): una sola fila. Reemplaza a
 * `CORREO_DOMINIO`, `CORREO_DOMINIO_ENTRADA`, `CORREO_MAILPACE_CLAVE`,
 * `VENTA_CORREO_REMITENTE`, `PROGRAMADOR_CORREO_REMITENTE`,
 * `PAGINA_CONTACTO_CORREO` y, encendida, a `MAILER_DSN`. Se lee con
 * `App\Correo\Configuracion\AjustesCorreo` y se edita en la administración
 * (`/configuracion/correo`); no es un recurso de la API.
 */
#[ORM\Entity]
class ConfiguracionCorreo
{
    public const ID = 1;
    public const SERVIDOR = "smtp.mailpace.com";
    public const PUERTO = 465;
    private const DOMINIO = "sistemafdn.com";
    public const REMITENTE_BOLETOS = "boletos@transportesfuentedelnorte.com";
    public const REMITENTE_REPORTES = "reportes@transportesfuentedelnorte.com";
    public const BUZON_CONTACTO = "contacto@transportesfuentedelnorte.com";

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    /** Encendido, todo correo sale por MailPace con el token de su dominio; apagado, por `MAILER_DSN` (en desarrollo, a ninguna parte). */
    #[ORM\Column(options: ["default" => false])]
    private bool $activo = false;

    #[ORM\Column(length: 255, options: ["default" => self::SERVIDOR])]
    private string $servidor = self::SERVIDOR;

    #[ORM\Column(options: ["default" => self::PUERTO])]
    private int $puerto = self::PUERTO;

    /**
     * Dominios verificados en MailPace => su token (API token del dominio)
     * cifrado con `CifradoCredenciales`, o null si falta. MailPace da un
     * token por dominio: un correo sale con el del dominio de su remitente.
     *
     * @var array<string, ?string>
     */
    #[ORM\Column(type: "json", options: ["default" => "{}"])]
    private array $tokens = [];

    /** El de las direcciones de los usuarios (`{usuario}@sistemafdn.com`). */
    #[ORM\Column(length: 255, options: ["default" => self::DOMINIO])]
    private string $dominio = self::DOMINIO;

    /** Donde se recibe, si es otro (MailPace solo recibe en un subdominio); null = el mismo. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dominioEntrada = null;

    /** Clave pública (base64) con la que MailPace firma el webhook de entrada; null = no se acepta correo entrante. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $claveWebhook = null;

    #[ORM\Column(length: 180, options: ["default" => self::REMITENTE_BOLETOS])]
    private string $remitenteBoletos = self::REMITENTE_BOLETOS;

    #[ORM\Column(length: 180, options: ["default" => self::REMITENTE_REPORTES])]
    private string $remitenteReportes = self::REMITENTE_REPORTES;

    /** Buzón que recibe el formulario de contacto de la página. */
    #[ORM\Column(length: 180, options: ["default" => self::BUZON_CONTACTO])]
    private string $buzonContacto = self::BUZON_CONTACTO;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $actualizadaEn = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $actualizadaPor = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    public function getServidor(): string
    {
        return $this->servidor;
    }

    public function getPuerto(): int
    {
        return $this->puerto;
    }

    public function setServidor(string $servidor, int $puerto): static
    {
        $this->servidor = $servidor;
        $this->puerto = $puerto;

        return $this;
    }

    /** @return array<string, ?string> */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /** @param array<string, ?string> $tokens dominio => token cifrado (o null) */
    public function setTokens(array $tokens): static
    {
        ksort($tokens);
        $this->tokens = $tokens;

        return $this;
    }

    public function getDominio(): string
    {
        return $this->dominio;
    }

    public function getDominioEntrada(): ?string
    {
        return $this->dominioEntrada;
    }

    public function setDominios(string $dominio, ?string $entrada): static
    {
        $this->dominio = $dominio;
        $this->dominioEntrada = $entrada !== null && $entrada !== $dominio ? $entrada : null;

        return $this;
    }

    public function getClaveWebhook(): ?string
    {
        return $this->claveWebhook;
    }

    public function setClaveWebhook(?string $clave): static
    {
        $this->claveWebhook = $clave;

        return $this;
    }

    public function getRemitenteBoletos(): string
    {
        return $this->remitenteBoletos;
    }

    public function getRemitenteReportes(): string
    {
        return $this->remitenteReportes;
    }

    public function getBuzonContacto(): string
    {
        return $this->buzonContacto;
    }

    public function setDirecciones(string $remitenteBoletos, string $remitenteReportes, string $buzonContacto): static
    {
        $this->remitenteBoletos = $remitenteBoletos;
        $this->remitenteReportes = $remitenteReportes;
        $this->buzonContacto = $buzonContacto;

        return $this;
    }

    public function getActualizadaEn(): ?\DateTimeImmutable
    {
        return $this->actualizadaEn;
    }

    public function getActualizadaPor(): ?Usuario
    {
        return $this->actualizadaPor;
    }

    public function actualizada(?Usuario $por, \DateTimeImmutable $en): static
    {
        $this->actualizadaPor = $por;
        $this->actualizadaEn = $en;

        return $this;
    }
}
