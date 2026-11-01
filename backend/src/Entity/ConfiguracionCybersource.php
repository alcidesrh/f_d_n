<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Configuración de la pasarela de pago Cybersource: una sola fila. Reemplaza
 * a `PAGO_PASARELA`, `CYBERSOURCE_URL` y `CYBERSOURCE_ORG_ID`. El comercio de
 * cada empresa sigue en `CredencialPago`. Se lee con
 * `App\Venta\Pago\Cybersource\AjustesCybersource` y se edita con
 * `app:pago:configuracion`; no es un recurso de la API.
 */
#[ORM\Entity]
class ConfiguracionCybersource
{
    public const ID = 1;
    public const URL_PRUEBAS = "https://apitest.cybersource.com";
    public const URL_PRODUCCION = "https://api.cybersource.com";

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    /** Encendida, los cobros con tarjeta van a Cybersource; apagada, a la pasarela simulada (desarrollo). */
    #[ORM\Column(options: ["default" => false])]
    private bool $activo = false;

    /** API de Cybersource: pruebas (`apitest`) o producción (`api`). */
    #[ORM\Column(length: 255, options: ["default" => self::URL_PRUEBAS])]
    private string $url = self::URL_PRUEBAS;

    /** Org ID de ThreatMetrix (huella del dispositivo para Decision Manager); null = sin huella. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $orgId = null;

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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getOrgId(): ?string
    {
        return $this->orgId;
    }

    public function setOrgId(?string $orgId): static
    {
        $this->orgId = $orgId !== null && trim($orgId) !== "" ? trim($orgId) : null;

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
