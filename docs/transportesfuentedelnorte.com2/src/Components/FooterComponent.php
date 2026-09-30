<?php

// src/Components/ButtonLinkComponent.php
namespace App\Components;

use App\Repository\ServicioRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('footer')]
class FooterComponent
{

    public function __construct(private ServicioRepository $servicioRepository)
    {
    }

    public function getServicios()
    {
        return $this->servicioRepository->findBy(['inicio' => true], ['prioridad' => 'ASC']);
    }
}
