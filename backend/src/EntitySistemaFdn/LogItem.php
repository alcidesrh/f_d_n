<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'custom_log')]
class LogItem
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 255)] private string $username;
    #[ORM\Column(type: 'string', length: 255)] private string $channel;
    #[ORM\Column(type: 'string', length: 255)] private string $level;
    #[ORM\Column(type: 'string', length: 1000)] private string $message;
    #[ORM\Column(type: 'datetime')] private \DateTime $createdAt;
    #[ORM\Column(type: 'string', length: 10)] private string $method;
    #[ORM\Column(type: 'boolean')] private bool $isAjax;
    #[ORM\Column(type: 'string', length: 10)] private string $scheme;
    #[ORM\Column(type: 'string', length: 1000)] private string $httpHost;
    #[ORM\Column(type: 'string', length: 20)] private string $clientIp;
    #[ORM\Column(type: 'boolean')] private bool $isSecure;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $entity = null;
    #[ORM\Column(type: 'string', length: 200, nullable: true)] private ?string $entityIds = null;
    #[ORM\ManyToOne(targetEntity: LogCode::class)] #[ORM\JoinColumn(name: 'codigo', referencedColumnName: 'codigo')] private ?LogCode $codigo = null;
    public function getId(): ?int { return $this->id; }
}
