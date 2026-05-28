<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'impresora')]
class Impresora
{
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 100)] private string $nombre;
    #[ORM\Column(type: 'string', length: 100)] private string $path;
    #[ORM\Column(name: 'id_tamano_pagina', type: 'integer')] private int $idTamanoPagina;
    #[ORM\Column(name: 'auto_print', type: 'boolean', nullable: true)] private ?bool $autoPrint = null;
    #[ORM\Column(name: 'espacio_letras', type: 'boolean', nullable: true)] private ?bool $espacioLetras = null;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    public function getId(): ?int { return $this->id; }
}
