<?php

// src/Components/ButtonLinkComponent.php
namespace App\Components;

use App\Repository\ServicioRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('header')]
class HeaderComponent
{

    public function __construct(private ServicioRepository $servicioRepository)
    {
    }

    public function getServicios()
    {
        return $this->servicioRepository->findBy(['inicio' => true], ['prioridad' => 'ASC']);
    }
}
