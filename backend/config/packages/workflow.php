<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Entity\BoletoAsiento;
use App\Entity\Encomienda;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\Enum\TransicionEncomienda;

/*
 * Máquinas de estado de `symfony/workflow` (ADR-037). En PHP y no en YAML
 * para que las transiciones salgan del mismo enum que valida el setter de la
 * entidad: no hay dos copias que se desincronicen.
 *
 * `bin/console workflow:dump boleto_asiento --dump-format=mermaid` (o
 * `encomienda`) dibuja el diagrama.
 */

/**
 * Las transiciones de un enum con `desde()` y `hacia()`, por nombre.
 *
 * @param list<TransicionBoleto|TransicionEncomienda> $casos
 */
$transiciones = static fn(array $casos): array => array_combine(
    array_map(static fn(TransicionBoleto|TransicionEncomienda $t) => $t->value, $casos),
    array_map(static fn(TransicionBoleto|TransicionEncomienda $t) => ["from" => $t->desde(), "to" => [$t->hacia()]], $casos),
);

return App::config([
    "framework" => [
        "workflows" => [
            TransicionBoleto::WORKFLOW => [
                "type" => "state_machine",
                "marking_store" => ["type" => "method", "property" => "estado"],
                "supports" => [BoletoAsiento::class],
                "initial_marking" => EstadoBoletoAsiento::EMITIDO,
                "places" => EstadoBoletoAsiento::cases(),
                "transitions" => $transiciones(TransicionBoleto::cases()),
            ],
            TransicionEncomienda::WORKFLOW => [
                "type" => "state_machine",
                "marking_store" => ["type" => "method", "property" => "estado"],
                "supports" => [Encomienda::class],
                "initial_marking" => EstadoEncomienda::RECIBIDA,
                "places" => EstadoEncomienda::cases(),
                "transitions" => $transiciones(TransicionEncomienda::cases()),
            ],
        ],
    ],
]);
