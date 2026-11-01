<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Configuración del certificador FEL Forcon: una sola fila. Reemplaza a
 * `FEL_CERTIFICADOR`, `FEL_FORCON_URL`, `FEL_FORCON_USUARIO`/`FEL_FORCON_CLAVE`
 * y el parámetro `fel_emisor_en_linea`. Las credenciales de cada empresa
 * siguen en `CredencialFel`. Se lee con `App\Venta\Facturacion\Forcon\AjustesForcon`
 * y se edita con `app:fel:configuracion`; no es un recurso de la API.
 */
#[ORM\Entity]
class ConfiguracionForcon
{
    public const ID = 1;
    public const URL_PRUEBAS = "https://pruebasfel.eforcon.com";

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    /** Encendido, las facturas se certifican en Forcon; apagado, con el certificador simulado (desarrollo). */
    #[ORM\Column(options: ["default" => false])]
    private bool $activo = false;

    /** API de Forcon: la de pruebas o la de producción que entrega su soporte. */
    #[ORM\Column(length: 255, options: ["default" => self::URL_PRUEBAS])]
    private string $url = self::URL_PRUEBAS;

    /** Usuario global: solo si una empresa no tiene sus credenciales (`CredencialFel`). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $usuario = null;

    /** Clave global cifrada (`CredencialesFel::cifrar`). */
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $claveCifrada = null;

    /**
     * Regla temporal: las ventas en línea de una empresa se facturan a nombre
     * de otra, como en el legado (NIT de la empresa => NIT del emisor).
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: "json", options: ["default" => "{}"])]
    private array $emisoresEnLinea = [];

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

    public function getUsuario(): ?string
    {
        return $this->usuario;
    }

    public function getClaveCifrada(): ?string
    {
        return $this->claveCifrada;
    }

    /** Usuario y clave (ya cifrada) globales; null en ambos para quitarlos. */
    public function setCredencialGlobal(?string $usuario, ?string $claveCifrada): static
    {
        $this->usuario = $usuario;
        $this->claveCifrada = $claveCifrada;

        return $this;
    }

    /** @return array<string, string> */
    public function getEmisoresEnLinea(): array
    {
        return $this->emisoresEnLinea;
    }

    /** @param array<string, string> $emisores NIT de la empresa => NIT de quien factura sus ventas en línea */
    public function setEmisoresEnLinea(array $emisores): static
    {
        $this->emisoresEnLinea = $emisores;

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
