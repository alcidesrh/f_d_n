<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Ubicacion;
use App\Acceso\Ubicador;
use App\Encomienda\ReglasEncomienda;
use App\Entity\BoletoAsiento;
use App\Entity\Empresa;
use App\Entity\Encomienda;
use App\Entity\Enum\Dispensa;
use App\Entity\Estacion;
use App\Entity\Salida;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;
use App\Venta\ReglasBoletos;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Sobre qué es una solicitud de autorización (ADR-040): carga los
 * registros, dice dónde cae la operación (quién la aprueba), arma lo que se
 * muestra y comprueba que de verdad haga falta —no se piden autorizaciones
 * para lo que ya se puede, ni para lo que ni con autorización se podría—.
 */
final class SujetosAutorizacion
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * @return array{ubicacion: Ubicacion, empresa: ?Empresa, estacion: ?Estacion, detalle: array<string, mixed>}
     *
     * @throws AutorizacionRechazada
     */
    public function de(Usuario $usuario, Autorizable $a): array
    {
        // Cada operación se pide sobre sus registros: las cortesías sobre una salida, anular encomiendas sobre encomiendas, lo demás sobre boletos.
        $esperado = match ($a->capacidad) {
            Capacidad::VENTA_CORTESIA => SolicitudAutorizacion::SUJETO_SALIDA,
            Capacidad::ENCOMIENDA_ANULAR => SolicitudAutorizacion::SUJETO_ENCOMIENDAS,
            default => SolicitudAutorizacion::SUJETO_BOLETOS,
        };
        if ($a->sujeto !== $esperado) {
            throw new AutorizacionRechazada("Esa operación no se autoriza sobre esos registros.", "sujeto_invalido");
        }
        $sujeto = match ($a->sujeto) {
            SolicitudAutorizacion::SUJETO_SALIDA => $this->salida($usuario, $a),
            SolicitudAutorizacion::SUJETO_ENCOMIENDAS => $this->encomiendas($usuario, $a),
            default => $this->boletos($a),
        };
        $this->exigirBloqueo($usuario, $a, $sujeto["ubicacion"], $sujeto["boletos"] ?? [], $sujeto["encomiendas"] ?? []);
        unset($sujeto["boletos"], $sujeto["encomiendas"]);

        return $sujeto;
    }

    /** @return array{ubicacion: Ubicacion, empresa: ?Empresa, estacion: ?Estacion, detalle: array<string, mixed>, boletos: list<BoletoAsiento>} */
    private function boletos(Autorizable $a): array
    {
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->getRepository(BoletoAsiento::class)->findBy(["id" => $a->ids], ["id" => "ASC"]);
        if (count($boletos) !== count($a->ids)) {
            throw new AutorizacionRechazada("Algún boleto no existe.", "no_encontrado", 404);
        }
        $venta = $boletos[0]->getBoletoVenta();
        foreach ($boletos as $b) {
            if ($b->getBoletoVenta()->getId() !== $venta->getId()) {
                throw new AutorizacionRechazada("Pida una autorización por venta: los boletos son de ventas distintas.", "ventas_distintas");
            }
        }
        $salida = $boletos[0]->getSalida();

        return [
            "ubicacion" => Ubicador::venta($venta),
            "empresa" => $salida->getEmpresa(),
            "estacion" => $venta->getEstacion(),
            "detalle" => [
                "venta" => $venta->getId(),
                "boletos" => array_map(static fn(BoletoAsiento $b) => [
                    "id" => $b->getId(),
                    "asiento" => $b->getAsiento()?->getNumero(),
                    "estado" => $b->getEstado()->value,
                    "salida" => $b->getSalida()->getId(),
                    "fecha" => $b->getSalida()->getFecha()?->format(DATE_ATOM),
                    "ruta" => $b->getSalida()->getRuta()?->getNombre(),
                    "trayecto" => sprintf("%s → %s", $b->getTrayecto()?->getOrigen()?->getNombre() ?? "?", $b->getTrayecto()?->getDestino()?->getNombre() ?? "?"),
                    "pasajero" => $b->getCliente()?->getNombreCompleto(),
                ], $boletos),
            ],
            "boletos" => $boletos,
        ];
    }

    /** @return array{ubicacion: Ubicacion, empresa: ?Empresa, estacion: ?Estacion, detalle: array<string, mixed>} */
    private function salida(Usuario $usuario, Autorizable $a): array
    {
        $salida = $this->em->find(Salida::class, $a->ids[0]) ?? throw new AutorizacionRechazada("La salida no existe.", "no_encontrado", 404);

        return [
            "ubicacion" => Ubicador::operacion($salida->getEmpresa()?->getId(), $usuario),
            "empresa" => $salida->getEmpresa(),
            "estacion" => $usuario->getAgencia() === null ? $usuario->getEstacion() : null,
            "detalle" => [
                "salida" => [
                    "id" => $salida->getId(),
                    "fecha" => $salida->getFecha()?->format(DATE_ATOM),
                    "ruta" => $salida->getRuta()?->getNombre(),
                    "bus" => $salida->getBus()?->getCodigo(),
                ],
                "asientos" => $a->cantidad,
            ],
        ];
    }

    /**
     * Encomiendas de una empresa (ADR-058). Cae donde el usuario opera sobre
     * esa empresa, como al anularla (`EncomiendaController::puedeOperar`).
     *
     * @return array{ubicacion: Ubicacion, empresa: ?Empresa, estacion: ?Estacion, detalle: array<string, mixed>, encomiendas: list<Encomienda>}
     */
    private function encomiendas(Usuario $usuario, Autorizable $a): array
    {
        /** @var list<Encomienda> $encomiendas */
        $encomiendas = $this->em->getRepository(Encomienda::class)->findBy(["id" => $a->ids], ["id" => "ASC"]);
        if (count($encomiendas) !== count($a->ids)) {
            throw new AutorizacionRechazada("Alguna encomienda no existe.", "no_encontrado", 404);
        }
        $empresa = $encomiendas[0]->getEmpresa();
        foreach ($encomiendas as $e) {
            if ($e->getEmpresa()?->getId() !== $empresa?->getId()) {
                throw new AutorizacionRechazada("Pida una autorización por empresa: las encomiendas son de empresas distintas.", "empresas_distintas");
            }
        }

        return [
            "ubicacion" => Ubicador::operacion($empresa?->getId(), $usuario),
            "empresa" => $empresa,
            "estacion" => $usuario->getAgencia() === null ? $usuario->getEstacion() : null,
            "detalle" => [
                "encomiendas" => array_map(static fn(Encomienda $e) => [
                    "id" => $e->getId(),
                    "codigo" => $e->getCodigo(),
                    "estado" => $e->getEstado()->value,
                    "origen" => $e->getOrigen()?->getNombre(),
                    "destino" => $e->getDestino()?->getNombre(),
                    "salida" => $e->getSalida()?->getId(),
                    "fecha" => $e->getSalida()?->getFecha()?->format(DATE_ATOM),
                ], $encomiendas),
            ],
            "encomiendas" => $encomiendas,
        ];
    }

    /**
     * Que la regla de la que se pide dispensa de verdad bloquee a `$usuario`.
     *
     * @param list<BoletoAsiento> $boletos
     * @param list<Encomienda>    $encomiendas
     *
     * @throws AutorizacionRechazada
     */
    private function exigirBloqueo(Usuario $usuario, Autorizable $a, Ubicacion $donde, array $boletos, array $encomiendas): void
    {
        $permisos = $this->autorizacion->de($usuario);
        $operacion = ReglasAutorizacion::operacion($a->capacidad);
        $tiene = $permisos->puede($a->capacidad->value, $donde);
        match ($a->dispensa) {
            Dispensa::PERMISO => $tiene
                ? throw new AutorizacionRechazada(sprintf("Ya puede %s ahí: no hace falta autorización.", $operacion), "sin_bloqueo")
                : null,
            Dispensa::LIMITE => match (true) {
                !$tiene => throw new AutorizacionRechazada(sprintf("No tiene permiso para %s ahí: pida la autorización por permiso.", $operacion), "sin_permiso"),
                ($limite = $a->capacidad->limiteQueBloquea()) === null || $permisos->limite($a->capacidad->value, $limite, $donde) === null
                    => throw new AutorizacionRechazada(sprintf("No tiene límite para %s: no hace falta autorización.", $operacion), "sin_bloqueo"),
                default => null,
            },
            Dispensa::HORA => $this->exigirSoloPorHora($boletos),
            Dispensa::ESTADO => $this->exigirSoloPorEstado($boletos, $encomiendas),
        };
    }

    /**
     * @param list<BoletoAsiento> $boletos
     * @param list<Encomienda>    $encomiendas
     *
     * @throws AutorizacionRechazada
     */
    private function exigirSoloPorEstado(array $boletos, array $encomiendas): void
    {
        $ahora = $this->reloj->now();
        foreach ($boletos as $b) {
            if (!ReglasBoletos::soloPorEstado($b, $ahora)) {
                throw new AutorizacionRechazada(
                    sprintf("El boleto del asiento %s no necesita autorización fuera de estado: o ya se puede, o basta la de fuera de hora, o ni con ella se puede (está %s).", $b->getAsiento()?->getNumero(), $b->getEstado()->value),
                    "sin_bloqueo",
                );
            }
        }
        foreach ($encomiendas as $e) {
            if (!ReglasEncomienda::soloPorEstado($e)) {
                throw new AutorizacionRechazada(
                    sprintf("La encomienda %s no necesita autorización fuera de estado: o ya se puede anular, o ni con ella se puede (está %s).", $e->getCodigo(), mb_strtolower($e->getEstado()->etiqueta())),
                    "sin_bloqueo",
                );
            }
        }
    }

    /**
     * @param list<BoletoAsiento> $boletos
     *
     * @throws AutorizacionRechazada
     */
    private function exigirSoloPorHora(array $boletos): void
    {
        $ahora = $this->reloj->now();
        foreach ($boletos as $b) {
            if (!ReglasBoletos::soloPorHora($b, $ahora)) {
                throw new AutorizacionRechazada(
                    sprintf("El boleto del asiento %s no necesita autorización fuera de hora: o todavía está a tiempo o ni con ella se puede (está %s, la salida %s).", $b->getAsiento()?->getNumero(), $b->getEstado()->value, $b->getSalida()->getEstado()->value),
                    "sin_bloqueo",
                );
            }
        }
    }
}
