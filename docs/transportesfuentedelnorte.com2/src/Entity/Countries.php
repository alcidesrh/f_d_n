<?php

namespace App\Entity;

use App\Repository\CountriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountriesRepository::class)]
#[ORM\Cache(usage: "READ_ONLY")]
class Countries {
    #[ORM\Id]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: States::class)]
    private Collection $states;

    #[ORM\OneToMany(mappedBy: 'country', targetEntity: Cities::class)]
    private Collection $cities;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $translations = null;

    #[ORM\Column(length: 3)]
    private ?string $iso3 = null;

    #[ORM\Column(length: 2)]
    private ?string $iso2 = null;

    #[ORM\Column(length: 191)]
    private ?string $emoji = null;

    public function __construct() {
        $this->states = new ArrayCollection();
        $this->cities = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getIso3(): ?string {
        return $this->iso3;
    }

    public function setIso3(string $iso3): self {
        $this->iso3 = $iso3;

        return $this;
    }

    public function getIso2(): ?string {
        return $this->iso2;
    }

    public function setIso2(string $iso2): self {
        $this->iso2 = $iso2;

        return $this;
    }

    public function getEmoji(): ?string {
        return $this->emoji;
    }

    /**
     * @return Collection<int, States>
     */
    public function getStates(): Collection {
        return $this->states;
    }

    public function addState(States $state): self {
        if (!$this->states->contains($state)) {
            $this->states->add($state);
            $state->setCountry($this);
        }

        return $this;
    }

    public function removeState(States $state): self {
        if ($this->states->removeElement($state)) {
            // set the owning side to null (unless already changed)
            if ($state->getCountry() === $this) {
                $state->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Cities>
     */
    public function getCities(): Collection {
        return $this->cities;
    }

    public function addCity(Cities $city): self {
        if (!$this->cities->contains($city)) {
            $this->cities->add($city);
            $city->setCountry($this);
        }

        return $this;
    }

    public function removeCity(Cities $city): self {
        if ($this->cities->removeElement($city)) {
            // set the owning side to null (unless already changed)
            if ($city->getCountry() === $this) {
                $city->setCountry(null);
            }
        }

        return $this;
    }

    public function getTranslations(): ?array {
        return json_decode(stripslashes($this->translations), true);
    }

    public function setTranslations(string $translations): self {
        $this->translations = $translations;

        return $this;
    }

    public function __toString() {
        return $this->name;
    }
}
