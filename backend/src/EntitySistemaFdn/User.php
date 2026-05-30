<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'custom_user')]
class User
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 100)] private string $names;
    #[ORM\Column(type: 'string', length: 100)] private string $surnames;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $ipRanges = null;
    #[ORM\Column(type: 'integer')] private int $intentosFallidos;
    #[ORM\Column(type: 'integer', nullable: true)] private ?int $reasignaciones = null;
    #[ORM\Column(type: 'datetime')] private \DateTime $dateCreate;
    #[ORM\Column(type: 'datetime')] private \DateTime $dateLastUdate;
    #[ORM\Column(type: 'string', length: 15, nullable: true)] private ?string $phone = null;
    #[ORM\Column(type: 'boolean')] private bool $accessAppWeb;
    #[ORM\Column(type: 'boolean')] private bool $accessAppMovil;
    #[ORM\Column(name: 'voucher_permitidos_en_el_dia', type: 'integer', nullable: true)] private ?int $voucherPermitidosEnElDia = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    public function getId(): ?int { return $this->id; }
    public function getNames(): string { return $this->names; }
    public function getSurnames(): string { return $this->surnames; }
    public function getPhone(): ?string { return $this->phone; }
}
