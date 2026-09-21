<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Repository\EstacionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EstacionRepository::class)]
#[ApiResourcePaginationPage]
class Estacion extends Enclave {}
