<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\ModoGrupoChat;
use App\Entity\Enum\TipoCanalChat;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conversación del chat interno: directa entre dos usuarios o de grupo. Solo
 * se usa por `App\Chat` y `/api/chat/*`; no se publica en GraphQL. Un grupo
 * tiene dueño (quien lo creó: lo edita), es abierto o dirigido y puede tener
 * grupos abiertos como miembros (`ChatSubgrupo`).
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

    /** Solo los grupos: ruta relativa de la foto en disco (`App\Chat\FotoGrupo`). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $foto = null;

    /** Solo los grupos: quién escribe. */
    #[ORM\Column(length: 10, nullable: true, enumType: ModoGrupoChat::class)]
    private ?ModoGrupoChat $modo = null;

    /** Ordena la bandeja: la conversación con el último mensaje va arriba. */
    #[ORM\Column]
    private \DateTimeImmutable $actividad;

    /** @var Collection<int, ChatMiembro> */
    #[ORM\OneToMany(mappedBy: "canal", targetEntity: ChatMiembro::class, cascade: ["persist"], orphanRemoval: true)]
    private Collection $miembros;

    /** @var Collection<int, ChatSubgrupo> */
    #[ORM\OneToMany(mappedBy: "canal", targetEntity: ChatSubgrupo::class, cascade: ["persist"], orphanRemoval: true)]
    private Collection $subgrupos;

    private function __construct(TipoCanalChat $tipo, ?Usuario $creadoPor)
    {
        $this->tipo = $tipo;
        $this->creadoPor = $creadoPor;
        $this->creadoEn = new \DateTimeImmutable();
        $this->actividad = $this->creadoEn;
        $this->miembros = new ArrayCollection();
        $this->subgrupos = new ArrayCollection();
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
    public static function grupo(string $nombre, Usuario $creador, array $miembros, ModoGrupoChat $modo = ModoGrupoChat::Abierto): self
    {
        $canal = new self(TipoCanalChat::Grupo, $creador);
        $canal->nombre = $nombre;
        $canal->modo = $modo;
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

    /**
     * Lo hace miembro por sí mismo (si ya estaba por un grupo miembro, pasa a
     * estarlo por sí mismo). Si es nuevo, lo anterior a `$leidoHasta` no le
     * cuenta como no leído.
     */
    public function agregar(Usuario $usuario, int $leidoHasta = 0): void
    {
        $miembro = $this->miembro($usuario);
        if ($miembro !== null) {
            $miembro->setVia(null);

            return;
        }
        $miembro = new ChatMiembro($this, $usuario);
        $miembro->leyoHasta($leidoHasta);
        $this->miembros->add($miembro);
    }

    /** Deja de ser miembro por sí mismo; si está en un grupo miembro, sigue por él. */
    public function quitar(Usuario $usuario): void
    {
        $miembro = $this->miembro($usuario);
        if ($miembro === null || !$miembro->esDirecto()) {
            return;
        }
        $via = $this->grupoMiembroDe($usuario);
        if ($via !== null) {
            $miembro->setVia($via);
        } else {
            $this->miembros->removeElement($miembro);
        }
    }

    public function agregarSubgrupo(ChatCanal $grupo, bool $emisor = false): void
    {
        $existente = $this->subgrupo($grupo);
        if ($existente !== null) {
            $existente->setEmisor($emisor);
        } else {
            $this->subgrupos->add(new ChatSubgrupo($this, $grupo, $emisor));
        }
    }

    public function quitarSubgrupo(ChatCanal $grupo): void
    {
        $existente = $this->subgrupo($grupo);
        if ($existente !== null) {
            $this->subgrupos->removeElement($existente);
        }
    }

    public function subgrupo(ChatCanal $grupo): ?ChatSubgrupo
    {
        foreach ($this->subgrupos as $s) {
            if ($s->getGrupo() === $grupo) {
                return $s;
            }
        }

        return null;
    }

    /**
     * Pone al día a los que están por un grupo miembro: entran los miembros
     * nuevos de esos grupos (sin no leídos anteriores a `$leidoHasta`) y salen
     * los que ya no están en ninguno. Los miembros por sí mismos no cambian.
     */
    public function sincronizar(int $leidoHasta = 0): void
    {
        /** @var array<int, array{Usuario, ChatCanal}> $porGrupo */
        $porGrupo = [];
        foreach ($this->subgrupos as $s) {
            foreach ($s->getGrupo()->getMiembros() as $m) {
                if ($m->esDirecto()) {
                    $porGrupo[(int) $m->getUsuario()->getId()] ??= [$m->getUsuario(), $s->getGrupo()];
                }
            }
        }
        foreach ($this->miembros->toArray() as $m) {
            if ($m->esDirecto()) {
                continue;
            }
            $via = $porGrupo[(int) $m->getUsuario()->getId()][1] ?? null;
            if ($via === null) {
                $this->miembros->removeElement($m);
            } else {
                $m->setVia($via);
            }
        }
        foreach ($porGrupo as [$usuario, $via]) {
            if ($this->miembro($usuario) === null) {
                $miembro = new ChatMiembro($this, $usuario, $via);
                $miembro->leyoHasta($leidoHasta);
                $this->miembros->add($miembro);
            }
        }
    }

    /** El primer grupo miembro donde `$usuario` está por sí mismo. */
    private function grupoMiembroDe(Usuario $usuario): ?ChatCanal
    {
        foreach ($this->subgrupos as $s) {
            if ($s->getGrupo()->miembro($usuario)?->esDirecto()) {
                return $s->getGrupo();
            }
        }

        return null;
    }

    /** Edita el grupo: el dueño o, si ya no existe, cualquiera de sus miembros por sí mismos. */
    public function administra(Usuario $usuario): bool
    {
        if ($this->tipo !== TipoCanalChat::Grupo) {
            return false;
        }

        return $this->creadoPor !== null
            ? $this->creadoPor->getId() === $usuario->getId()
            : (bool) $this->miembro($usuario)?->esDirecto();
    }

    /**
     * Puede escribir aquí (sin contar el directorio, que se mira aparte en
     * los directos). En un dirigido: quien lo administra, los miembros
     * emisores y los miembros de los grupos miembros emisores.
     */
    public function puedeEscribir(Usuario $usuario): bool
    {
        $miembro = $this->miembro($usuario);
        if ($miembro === null || $this->tipo === TipoCanalChat::Sistema) {
            return false;
        }
        if ($this->tipo === TipoCanalChat::Directo || $this->modo !== ModoGrupoChat::Dirigido) {
            return true;
        }
        if ($this->administra($usuario) || $miembro->esEmisor()) {
            return true;
        }
        foreach ($this->subgrupos as $s) {
            if ($s->esEmisor() && $s->getGrupo()->miembro($usuario)?->esDirecto()) {
                return true;
            }
        }

        return false;
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

    public function renombrar(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function getModo(): ?ModoGrupoChat
    {
        return $this->modo;
    }

    public function setModo(ModoGrupoChat $modo): void
    {
        $this->modo = $modo;
    }

    public function getFoto(): ?string
    {
        return $this->foto;
    }

    public function setFoto(?string $foto): void
    {
        $this->foto = $foto;
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

    /** @return Collection<int, ChatSubgrupo> */
    public function getSubgrupos(): Collection
    {
        return $this->subgrupos;
    }
}
