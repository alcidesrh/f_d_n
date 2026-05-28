<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'job_sync')]
class JobSync
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'integer')] private int $nivel;
    #[ORM\Column(type: 'string', length: 6, nullable: true)] private ?string $type = null;
    #[ORM\Column(type: 'smallint')] private int $web1estado;
    #[ORM\Column(type: 'smallint')] private int $web2estado;
    #[ORM\Column(type: 'smallint')] private int $web3estado;
    #[ORM\Column(type: 'smallint')] private int $web4estado;
    #[ORM\Column(type: 'array')] private array $data;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    public function getId(): ?int { return $this->id; }
}
