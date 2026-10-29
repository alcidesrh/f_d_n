<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\TipoMensajePredeterminado;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una instancia de mensaje predeterminado del chat (ADR-045): con nombre,
 * un tipo del catálogo (`TipoMensajePredeterminado`), su audiencia (usuarios
 * y grupos del chat) y el contenido propio que va con cada envío. Llega a
 * los avisos del sistema de cada destinatario. Puede haber varias del mismo
 * tipo (p. ej. "Salida iniciada" de dos rutas).
 *
 * Se escribe solo por `/api/chat/predeterminados` (`chat.predeterminados`).
 * Los archivos son de chat (`ChatArchivo` sin mensaje): mientras la instancia
 * los use, la purga de sueltos no los toca; cada envío lleva copias.
 */
#[ORM\Entity]
class MensajePredeterminado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $nombre;

    #[ORM\Column(length: 30, enumType: TipoMensajePredeterminado::class)]
    private TipoMensajePredeterminado $tipo;

    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Ruta $ruta = null;

    /** `HH:MM` (America/Guatemala) de los que salen a una hora. */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $hora = null;

    /** @var list<int> días ISO (1 = lunes … 7 = domingo); vacío = todos */
    #[ORM\Column(type: "json", options: ["default" => "[]"])]
    private array $dias = [];

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $texto = null;

    /** @var Collection<int, ChatArchivo> */
    #[ORM\ManyToMany(targetEntity: ChatArchivo::class)]
    #[ORM\JoinTable(name: "mensaje_predeterminado_archivo")]
    #[ORM\InverseJoinColumn(onDelete: "CASCADE")]
    private Collection $archivos;

    /** @var Collection<int, Usuario> */
    #[ORM\ManyToMany(targetEntity: Usuario::class)]
    #[ORM\JoinTable(name: "mensaje_predeterminado_usuario")]
    #[ORM\InverseJoinColumn(onDelete: "CASCADE")]
    private Collection $usuarios;

    /** @var Collection<int, ChatCanal> grupos del chat: llega a sus miembros */
    #[ORM\ManyToMany(targetEntity: ChatCanal::class)]
    #[ORM\JoinTable(name: "mensaje_predeterminado_grupo")]
    #[ORM\InverseJoinColumn(onDelete: "CASCADE")]
    private Collection $grupos;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $creadoPor = null;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    #[ORM\Column]
    private \DateTimeImmutable $actualizadoEn;

    public function __construct(string $nombre, TipoMensajePredeterminado $tipo, ?Usuario $creadoPor)
    {
        $this->nombre = $nombre;
        $this->tipo = $tipo;
        $this->creadoPor = $creadoPor;
        $this->archivos = new ArrayCollection();
        $this->usuarios = new ArrayCollection();
        $this->grupos = new ArrayCollection();
        $this->creadoEn = $this->actualizadoEn = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function getTipo(): TipoMensajePredeterminado
    {
        return $this->tipo;
    }

    public function setTipo(TipoMensajePredeterminado $tipo): void
    {
        $this->tipo = $tipo;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): void
    {
        $this->activo = $activo;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): void
    {
        $this->empresa = $empresa;
    }

    public function getRuta(): ?Ruta
    {
        return $this->ruta;
    }

    public function setRuta(?Ruta $ruta): void
    {
        $this->ruta = $ruta;
    }

    public function getHora(): ?string
    {
        return $this->hora;
    }

    public function setHora(?string $hora): void
    {
        $this->hora = $hora;
    }

    /** @return list<int> */
    public function getDias(): array
    {
        return $this->dias;
    }

    /** @param list<int> $dias */
    public function setDias(array $dias): void
    {
        $this->dias = $dias;
    }

    public function getTexto(): ?string
    {
        return $this->texto;
    }

    public function setTexto(?string $texto): void
    {
        $this->texto = $texto;
    }

    /** @return Collection<int, ChatArchivo> */
    public function getArchivos(): Collection
    {
        return $this->archivos;
    }

    /** @param list<ChatArchivo> $archivos */
    public function setArchivos(array $archivos): void
    {
        $this->archivos = new ArrayCollection($archivos);
    }

    /** @return Collection<int, Usuario> */
    public function getUsuarios(): Collection
    {
        return $this->usuarios;
    }

    /** @param list<Usuario> $usuarios */
    public function setUsuarios(array $usuarios): void
    {
        $this->usuarios = new ArrayCollection($usuarios);
    }

    /** @return Collection<int, ChatCanal> */
    public function getGrupos(): Collection
    {
        return $this->grupos;
    }

    /** @param list<ChatCanal> $grupos */
    public function setGrupos(array $grupos): void
    {
        $this->grupos = new ArrayCollection($grupos);
    }

    public function getCreadoPor(): ?Usuario
    {
        return $this->creadoPor;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }

    public function getActualizadoEn(): \DateTimeImmutable
    {
        return $this->actualizadoEn;
    }

    public function tocar(): void
    {
        $this->actualizadoEn = new \DateTimeImmutable();
    }
}
