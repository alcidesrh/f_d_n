<?php

namespace App\Entity;

use Gedmo\Mapping\Annotation as Gedmo;
use App\Repository\ServicioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Translatable\Translatable;

#[ORM\Entity(repositoryClass: ServicioRepository::class)]
// #[ORM\Cache(usage: "NONSTRICT_READ_WRITE")]
class Servicio implements Translatable {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Slug(fields: ['nombre'])]
    private ?string $slug = null;

    private ?string $nombreEn = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $texto = null;

    private ?string $textoEn = null;

    #[ORM\ManyToOne(cascade: ['remove'])]
    #[ORM\JoinColumn(onDelete: 'set null')]
    private ?Archivo $imagen = null;

    #[Gedmo\Locale]
    private $locale;

    #[ORM\Column(nullable: true)]
    private ?bool $inicio = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $prioridad = null;

    public function __construct() {
        $this->prioridad = 1;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function getNombreEn(): ?string {
        return $this->nombreEn;
    }

    public function setNombreEn($p) {
        $this->nombreEn = $p;
        return $this;
    }

    public function setNombre(?string $nombre): self {
        $this->nombre = $nombre;

        return $this;
    }

    public function getTexto(): ?string {
        return $this->texto;
    }

    public function getTextoEn(): ?string {
        return $this->textoEn;
    }

    public function setTextoEn($p) {
        $this->textoEn = $p;
        return $this;
    }

    public function setTexto(?string $texto): self {
        $this->texto = $texto;

        return $this;
    }

    public function getImagen(): ?Archivo {
        return $this->imagen;
    }

    public function setImagen(?Archivo $imagen): self {
        $this->imagen = $imagen;

        return $this;
    }

    public function setTranslatableLocale($locale) {
        $this->locale = $locale;
        return $this;
    }

    public function getSlug(): ?string {
        return $this->slug;
    }

    public function setSlug(string $slug): self {
        $this->slug = $slug;

        return $this;
    }

    public function isInicio(): ?bool {
        return $this->inicio;
    }

    public function setInicio(?bool $inicio): self {
        $this->inicio = $inicio;

        return $this;
    }

    public function getPrioridad(): ?int {
        return $this->prioridad;
    }

    public function setPrioridad(?int $prioridad): self {
        $this->prioridad = $prioridad;

        return $this;
    }
}
