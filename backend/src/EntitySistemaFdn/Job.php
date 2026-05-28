<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'Job')]
class Job
{
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $name = null;
    #[ORM\Column(type: 'string')] private string $serviceId;
    #[ORM\Column(type: 'object')] private object $proxy;
    #[ORM\Column(type: 'datetime')] private \DateTime $nextExecutionDate;
    #[ORM\Column(type: 'datetime')] private \DateTime $insertionDate;
    #[ORM\Column(type: 'datetime')] private \DateTime $firstExecutionDate;
    #[ORM\Column(type: 'datetime', nullable: true)] private ?\DateTime $lastExecutionDate = null;
    #[ORM\Column(type: 'string', nullable: true)] private ?string $repeatEvery = null;
    #[ORM\Column(type: 'bigint')] private int $executionCount;
    #[ORM\Column(type: 'string', length: 10)] private string $status;
    #[ORM\Column(type: 'object', nullable: true)] private ?object $lastException = null;
    public function getId(): ?int { return $this->id; }
}
