<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\TipoCanalChat;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conversación del chat interno: directa entre dos usuarios o de grupo. Solo
 * se usa por `App\Chat` y `/api/chat/*`; no se publica en GraphQL.
 */
#[ORM\Entity]
class ChatCanal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, enumType: TipoCanalChat::class)]
    private TipoCanalChat $tipo;

    /** Solo los grupos; el directo se nombra por el otro miembro. */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $nombre = null;

    /** `{menor}-{mayor}` de los ids de usuario en los directos: uno por par. */
    #[ORM\Column(length: 30, unique: true, nullable: true)]
    private ?string $clave = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $creadoPor = null;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    /** Ordena la bandeja: la conversación con el último mensaje va arriba. */
    #[ORM\Column]
    private \DateTimeImmutable $actividad;

    /** @var Collection<int, ChatMiembro> */
    #[ORM\OneToMany(mappedBy: "canal", targetEntity: ChatMiembro::class, cascade: ["persist"], orphanRemoval: true)]
    private Collection $miembros;

    private function __construct(TipoCanalChat $tipo, ?Usuario $creadoPor)
    {
        $this->tipo = $tipo;
        $this->creadoPor = $creadoPor;
        $this->creadoEn = new \DateTimeImmutable();
        $this->actividad = $this->creadoEn;
        $this->miembros = new ArrayCollection();
    }

    public static function directo(Usuario $a, Usuario $b): self
    {
        $canal = new self(TipoCanalChat::Directo, $a);
        $canal->clave = self::claveDirecto((int) $a->getId(), (int) $b->getId());
        $canal->agregar($a);
        $canal->agregar($b);

        return $canal;
    }

    /** Avisos automáticos para un usuario: un canal por usuario, solo lectura. */
    public static function sistema(Usuario $usuario): self
    {
        $canal = new self(TipoCanalChat::Sistema, null);
        $canal->nombre = "Avisos del sistema";
        $canal->clave = self::claveSistema((int) $usuario->getId());
        $canal->agregar($usuario);

        return $canal;
    }

    public static function claveSistema(int $usuario): string
    {
        return sprintf("s-%d", $usuario);
    }

    /** @param list<Usuario> $miembros además del creador */
    public static function grupo(string $nombre, Usuario $creador, array $miembros): self
    {
        $canal = new self(TipoCanalChat::Grupo, $creador);
        $canal->nombre = $nombre;
        $canal->agregar($creador);
        foreach ($miembros as $m) {
            $canal->agregar($m);
        }

        return $canal;
    }

    public static function claveDirecto(int $a, int $b): string
    {
        return sprintf("%d-%d", min($a, $b), max($a, $b));
    }

    public function agregar(Usuario $usuario): void
    {
        if ($this->miembro($usuario) === null) {
            $this->miembros->add(new ChatMiembro($this, $usuario));
        }
    }

    public function miembro(Usuario $usuario): ?ChatMiembro
    {
        foreach ($this->miembros as $m) {
            if ($m->getUsuario()->getId() === $usuario->getId()) {
                return $m;
            }
        }

        return null;
    }

    public function huboActividad(\DateTimeImmutable $cuando): void
    {
        $this->actividad = $cuando;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): TipoCanalChat
    {
        return $this->tipo;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function getCreadoPor(): ?Usuario
    {
        return $this->creadoPor;
    }

    public function getActividad(): \DateTimeImmutable
    {
        return $this->actividad;
    }

    /** @return Collection<int, ChatMiembro> */
    public function getMiembros(): Collection
    {
        return $this->miembros;
    }
}
