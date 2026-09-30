<?php

namespace App\Services\Factories;

use App\Entity\Empresa;
use App\Repository\EmpresaRepository;

class EmpresaFactory
{
    public function __construct(private EmpresaRepository $empresaRepository)
    {
    }

    public function __invoke(): ?Empresa
    {
        return $this->empresaRepository->findOneBy(['alias' => 'ROSITA']);
    }
}
