<?php

declare(strict_types=1);

namespace App\Vigilancia;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class VigilarOperacionHandler
{
    public function __construct(private readonly VigilanciaOperativa $vigilancia) {}

    /** @return array{ambitos: int, nuevas: int, avisados: int} */
    public function __invoke(VigilarOperacion $mensaje): array
    {
        return $this->vigilancia->vigilar();
    }
}
