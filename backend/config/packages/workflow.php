<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\TransicionBoleto;

/*
 * Máquinas de estado de `symfony/workflow` (ADR-037). En PHP y no en YAML
 * para que las transiciones salgan del mismo enum que valida el setter de la
 * entidad: no hay dos copias que se desincronicen.
 *
 * `bin/console workflow:dump boleto_asiento --dump-format=mermaid` dibuja el
 * diagrama.
 */
return App::config([
    "framework" => [
        "workflows" => [
            TransicionBoleto::WORKFLOW => [
                "type" => "state_machine",
                "marking_store" => ["type" => "method", "property" => "estado"],
                "supports" => [BoletoAsiento::class],
                "initial_marking" => EstadoBoletoAsiento::EMITIDO,
                "places" => EstadoBoletoAsiento::cases(),
                "transitions" => array_combine(
                    array_map(static fn(TransicionBoleto $t) => $t->value, TransicionBoleto::cases()),
                    array_map(static fn(TransicionBoleto $t) => ["from" => $t->desde(), "to" => [$t->hacia()]], TransicionBoleto::cases()),
                ),
            ],
        ],
    ],
]);
