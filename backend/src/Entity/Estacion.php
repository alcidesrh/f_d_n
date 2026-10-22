<?php

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use App\Repository\EstacionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EstacionRepository::class)]
#[ApiResourcePaginationPage]
#[Recurso(nombre: "Estaciones", area: "Geografía", lecturaLibre: true)]
class Estacion extends Enclave {}
