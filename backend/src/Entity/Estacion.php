<?php

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourcePaginationPage]
#[Recurso(nombre: "Estaciones", area: "Geografía", lecturaLibre: true)]
class Estacion extends Enclave {}
