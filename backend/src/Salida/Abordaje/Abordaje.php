<?php

declare(strict_types=1);

namespace App\Salida\Abordaje;

use App\Encomienda\MovimientosEncomienda;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Cliente;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\Parada;
use App\Entity\Salida;
use App\Entity\Usuario;
use App\Salida\SalidaRechazada;
use App\Salida\VistaSalida;
use App\Venta\Boleto\Flujo\FlujoBoleto;
use App\Venta\Disponibilidad;
use App\Venta\Ocupacion;
use App\Venta\PublicadorOcupacion;
use App\Venta\Tramo;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Abordaje de una salida (ADR-032): ponerla a abordar, chequear sus boletos
 * (presentes → chequeado/tránsito; no presentados → cancelado), iniciarla y
 * finalizarla al llegar.
 * Reglas en `ReglasAbordaje`; cada boleto cambia de estado por su
 * transición (`FlujoBoleto`, ADR-037). Cada operación bloquea la salida (como la
 * venta) para no chocar con una venta o reasignación en curso, y avisa el
 * cambio de ocupación por Mercure: un cancelado libera su asiento.
 */
final class Abordaje
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly PublicadorOcupacion $ocupacion,
        private readonly FlujoBoleto $flujo,
        private readonly ClockInterface $reloj,
        private readonly Disponibilidad $disponibilidad,
        private readonly MovimientosEncomienda $encomiendas,
    ) {}

    /**
     * Las salidas del selector (`ReglasAbordaje::visibleEnSelector`), la más
     * reciente primero.
     *
     * @return list<array<string, mixed>>
     */
    public function salidas(Usuario $usuario): array
    {
        [$desde, $hasta] = ReglasAbordaje::ventanaSelector($this->reloj->now());
        /** @var list<Salida> $salidas */
        $salidas = $this->em->createQueryBuilder()
            ->select("s", "r", "p", "e", "b", "em")
            ->from(Salida::class, "s")
            ->join("s.ruta", "r")
            ->join("r.paradas", "p")
            ->join("p.enclave", "e")
            ->leftJoin("s.bus", "b")
            ->leftJoin("s.empresa", "em")
            ->where("s.fecha BETWEEN :desde AND :hasta")
            ->andWhere("s.estado IN (:estados)")
            ->setParameter("desde", $desde)
            ->setParameter("hasta", $hasta)
            ->setParameter("estados", [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO, EstadoSalida::INICIADA])
            ->orderBy("s.fecha", "DESC")
            ->addOrderBy("s.id", "DESC")
            ->getQuery()
            ->getResult();

        $estacion = $this->estacionDe($usuario);
        $visibles = array_filter($salidas, static function (Salida $s) use ($estacion) {
            $enclaves = array_map(static fn(Parada $p) => (int) $p->getEnclave()->getId(), $s->getRuta()->getParadas()->toArray());

            return $enclaves !== [] && ReglasAbordaje::visibleEnSelector($s->getEstado(), $estacion, array_shift($enclaves), $enclaves);
        });

        return array_values(array_map($this->vistaSalida(...), $visibles));
    }

    /**
     * La salida con sus boletos por chequear (emitidos) y ya chequeados
     * (chequeado, tránsito, finalizado), por número de asiento. `chequeable` dice si
     * este usuario puede marcar el boleto ahora.
     *
     * @return array{salida: array<string, mixed>, topico: string, chequeable: bool, motivo: ?string, boletos: list<array<string, mixed>>}
     */
    public function detalle(int $salidaId, Usuario $usuario): array
    {
        $salida = $this->salida($salidaId);
        $estacion = $this->estacionDe($usuario);
        $motivo = null;
        try {
            ReglasAbordaje::exigirChequeable($salida->getEstado(), $salida->getFecha(), $this->reloj->now());
        } catch (SalidaRechazada $e) {
            $motivo = $e->getMessage();
        }

        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->createQueryBuilder()
            ->select("b", "a", "v", "c", "vc", "t", "o", "d", "ag", "f")
            ->from(BoletoAsiento::class, "b")
            ->join("b.asiento", "a")
            ->join("b.boletoVenta", "v")
            ->join("b.trayecto", "t")
            ->join("t.origen", "o")
            ->join("t.destino", "d")
            ->leftJoin("b.cliente", "c")
            ->leftJoin("v.cliente", "vc")
            ->leftJoin("v.agencia", "ag")
            ->leftJoin("v.factura", "f")
            ->where("b.salida = :salida")
            ->andWhere("b.estado IN (:estados)")
            ->setParameter("salida", $salida)
            ->setParameter("estados", [EstadoBoletoAsiento::EMITIDO, EstadoBoletoAsiento::CHEQUEADO, EstadoBoletoAsiento::TRANSITO, EstadoBoletoAsiento::FINALIZADO])
            ->orderBy("a.numero", "ASC")
            ->addOrderBy("b.ordenSube", "ASC")
            ->getQuery()
            ->getResult();

        return [
            "salida" => $this->vistaSalida($salida),
            // Una venta o anulación durante el abordaje cambia la lista (Mercure).
            "topico" => PublicadorOcupacion::topico($salidaId),
            "chequeable" => $motivo === null,
            "motivo" => $motivo,
            "boletos" => array_map(fn(BoletoAsiento $b) => $this->vistaBoleto($b, $estacion), $boletos),
        ];
    }

    /**
     * @return array{salida: array<string, mixed>}
     *
     * @throws SalidaRechazada
     */
    public function abordar(int $salidaId, Usuario $usuario): array
    {
        $this->transaccion->ejecutar(function () use ($salidaId, $usuario) {
            $salida = $this->bloquear($salidaId);
            $this->exigirDesdeElOrigen($salida, $usuario, "poner la salida a abordar");
            ReglasAbordaje::exigirAbordable($salida->getEstado(), $salida->getFecha(), $this->reloj->now());
            $salida->setEstado(EstadoSalida::ABORDANDO);
        });

        return ["salida" => $this->vistaSalida($this->salida($salidaId))];
    }

    /**
     * Marca los presentes (`$si`) y los que no se presentaron (`$no`). Todo o
     * nada: si un boleto no se puede chequear, no cambia ninguno.
     *
     * @param list<mixed> $si
     * @param list<mixed> $no
     *
     * @return array{chequeados: int, cancelados: int}
     *
     * @throws SalidaRechazada
     */
    public function chequear(int $salidaId, array $si, array $no, Usuario $usuario): array
    {
        [$si, $no] = ReglasAbordaje::seleccion($si, $no);
        $resultado = $this->transaccion->ejecutar(function () use ($salidaId, $si, $no, $usuario) {
            $salida = $this->bloquear($salidaId);
            ReglasAbordaje::exigirChequeable($salida->getEstado(), $salida->getFecha(), $this->reloj->now());
            $estacion = $this->estacionDe($usuario);

            /** @var list<BoletoAsiento> $boletos */
            $boletos = $this->em->getRepository(BoletoAsiento::class)->findBy(["id" => [...$si, ...$no], "salida" => $salida]);
            if (count($boletos) !== count($si) + count($no)) {
                throw new SalidaRechazada("Algún boleto no existe o no es de esta salida.", "boleto_inexistente", 404);
            }
            $alChequear = TransicionBoleto::alChequear($salida->getEstado());
            foreach ($boletos as $b) {
                $this->em->refresh($b);
                // El NO también exige un boleto que se pueda chequear (emitido, venta confirmada).
                $this->flujo->exigir($b, $alChequear);
                $this->exigirEstacion($b, $estacion);
                if (in_array($b->getId(), $si, true)) {
                    $this->flujo->aplicar($b, $alChequear);
                } else {
                    $this->flujo->aplicar($b, TransicionBoleto::NO_PRESENTARSE, ["motivo" => "No se presentó a abordar."]);
                }
            }

            return ["chequeados" => count($si), "cancelados" => count($no)];
        });
        if ($resultado["cancelados"] > 0) {
            $this->ocupacion->cambio($salidaId);
        }

        return $resultado;
    }

    /**
     * Cancela un boleto desde el listado de boletos (el pasajero no viaja):
     * libera el asiento y conserva la venta y su factura, como el chequeo NO.
     * A diferencia del chequeo, no depende de la estación ni de la hora
     * (`TransicionBoleto::CANCELAR`); lo exige el permiso `boleto.cancelar`.
     *
     * @param callable(BoletoVenta): bool $puedeOperar si el usuario puede operar sobre esa venta
     *
     * @return array{id: int, estado: string}
     *
     * @throws SalidaRechazada
     */
    public function cancelar(int $boletoId, string $motivo, callable $puedeOperar): array
    {
        $boleto = $this->em->find(BoletoAsiento::class, $boletoId)
            ?? throw new SalidaRechazada("El boleto no existe.", "boleto_inexistente", 404);
        $salidaId = (int) $boleto->getSalida()->getId();
        $motivo = mb_substr(trim($motivo), 0, 200);

        $this->transaccion->ejecutar(function () use ($boleto, $salidaId, $motivo, $puedeOperar) {
            $this->bloquear($salidaId);
            $this->em->refresh($boleto);
            if (!$puedeOperar($boleto->getBoletoVenta())) {
                throw new SalidaRechazada("No puede operar sobre boletos de esta venta.", "permiso", 403);
            }
            $this->flujo->aplicar($boleto, TransicionBoleto::CANCELAR, ["motivo" => $motivo === "" ? "Cancelado desde el listado de boletos." : $motivo]);
        });
        $this->ocupacion->cambio($salidaId);

        return ["id" => $boletoId, "estado" => EstadoBoletoAsiento::CANCELADO->value];
    }

    /**
     * Revierte un cancelado a emitido (el pasajero sí viaja; el "Revertir"
     * del legado): vuelve a ocupar su asiento, así que antes comprueba que
     * nadie lo haya vendido o apartado en un tramo que se solape. Reglas en
     * `TransicionBoleto::REVERTIR`; lo exige el permiso `boleto.cancelar`.
     *
     * @param callable(BoletoVenta): bool $puedeOperar si el usuario puede operar sobre esa venta
     *
     * @return array{id: int, estado: string}
     *
     * @throws SalidaRechazada
     */
    public function revertir(int $boletoId, callable $puedeOperar): array
    {
        $boleto = $this->em->find(BoletoAsiento::class, $boletoId)
            ?? throw new SalidaRechazada("El boleto no existe.", "boleto_inexistente", 404);
        $salidaId = (int) $boleto->getSalida()->getId();

        $this->transaccion->ejecutar(function () use ($boleto, $salidaId, $puedeOperar) {
            $salida = $this->bloquear($salidaId);
            $this->em->refresh($boleto);
            $venta = $boleto->getBoletoVenta();
            if (!$puedeOperar($venta)) {
                throw new SalidaRechazada("No puede operar sobre boletos de esta venta.", "permiso", 403);
            }
            $this->flujo->exigir($boleto, TransicionBoleto::REVERTIR);

            // Lo que el workflow no sabe: vuelve a ocupar su asiento.
            $asiento = $boleto->getAsiento();
            $estados = Ocupacion::estados(new Tramo((int) $boleto->getOrdenSube(), (int) $boleto->getOrdenBaja()), $this->disponibilidad->ocupantes($salida));
            if (isset($estados[(int) $asiento->getId()])) {
                $ocupado = $estados[(int) $asiento->getId()]["estado"] === Ocupacion::VENDIDO ? "se volvió a vender" : "está apartado en la página web";
                throw new SalidaRechazada(sprintf("El asiento %d %s en ese tramo: el boleto no se puede revertir.", $asiento->getNumero(), $ocupado), "asiento_ocupado", 409);
            }
            $this->flujo->aplicar($boleto, TransicionBoleto::REVERTIR);
        });
        $this->ocupacion->cambio($salidaId);

        return ["id" => $boletoId, "estado" => EstadoBoletoAsiento::EMITIDO->value];
    }

    /**
     * Inicia la salida: chequeados → tránsito; emitidos que subían en el
     * origen → cancelado. Los que suben más adelante se chequean en su
     * parada.
     *
     * @return array{salida: array<string, mixed>, enTransito: int, cancelados: int}
     *
     * @throws SalidaRechazada
     */
    public function iniciar(int $salidaId, Usuario $usuario): array
    {
        $conteo = $this->transaccion->ejecutar(function () use ($salidaId, $usuario) {
            $salida = $this->bloquear($salidaId);
            $this->exigirDesdeElOrigen($salida, $usuario, "iniciar la salida");
            ReglasAbordaje::exigirIniciable($salida->getEstado());
            $salida->setEstado(EstadoSalida::INICIADA);

            $conteo = ["enTransito" => 0, "cancelados" => 0];
            /** @var list<BoletoAsiento> $boletos */
            $boletos = $this->em->getRepository(BoletoAsiento::class)->findBy([
                "salida" => $salida,
                "estado" => [EstadoBoletoAsiento::EMITIDO->value, EstadoBoletoAsiento::CHEQUEADO->value],
            ]);
            foreach ($boletos as $b) {
                $transicion = ReglasAbordaje::transicionAlIniciar($b->getEstado(), (int) $b->getOrdenSube());
                if ($transicion === TransicionBoleto::NO_PRESENTARSE) {
                    $this->flujo->aplicar($b, $transicion, ["motivo" => "Inició la salida y el boleto no se chequeó."]);
                    $conteo["cancelados"]++;
                } elseif ($transicion === TransicionBoleto::PARTIR) {
                    $this->flujo->aplicar($b, $transicion);
                    $conteo["enTransito"]++;
                }
            }

            // Las encomiendas que van en la salida pasan a tránsito (ADR-035).
            $this->encomiendas->alIniciarSalida($salida);

            return $conteo;
        });
        $this->ocupacion->cambio($salidaId);

        return ["salida" => $this->vistaSalida($this->salida($salidaId))] + $conteo;
    }

    /**
     * Finaliza la salida (llegó a su destino): tránsito → finalizado;
     * emitidos que nadie chequeó → cancelado.
     *
     * @return array{salida: array<string, mixed>, finalizados: int, cancelados: int}
     *
     * @throws SalidaRechazada
     */
    public function finalizar(int $salidaId, Usuario $usuario): array
    {
        $conteo = $this->transaccion->ejecutar(function () use ($salidaId, $usuario) {
            $salida = $this->bloquear($salidaId);
            $destino = $salida->getRuta()?->getDestino();
            ReglasAbordaje::exigirDesdeElDestino($this->estacionDe($usuario), (int) $destino?->getId(), (string) $destino?->getNombre(), "finalizar la salida");
            ReglasAbordaje::exigirFinalizable($salida->getEstado());
            $salida->setEstado(EstadoSalida::FINALIZADA);

            $conteo = ["finalizados" => 0, "cancelados" => 0];
            /** @var list<BoletoAsiento> $boletos */
            $boletos = $this->em->getRepository(BoletoAsiento::class)->findBy([
                "salida" => $salida,
                "estado" => [EstadoBoletoAsiento::EMITIDO->value, EstadoBoletoAsiento::CHEQUEADO->value, EstadoBoletoAsiento::TRANSITO->value],
            ]);
            foreach ($boletos as $b) {
                $pasos = ReglasAbordaje::pasosAlFinalizar($b->getEstado());
                foreach ($pasos as $paso) {
                    $this->flujo->aplicar($b, $paso, $paso === TransicionBoleto::NO_PRESENTARSE ? ["motivo" => "Finalizó la salida y el boleto no se chequeó."] : []);
                    // Un flush por paso: la bitácora anota cada transición.
                    if (count($pasos) > 1) {
                        $this->em->flush();
                    }
                }
                match ($pasos === [] ? null : end($pasos)->hacia()) {
                    EstadoBoletoAsiento::FINALIZADO => $conteo["finalizados"]++,
                    EstadoBoletoAsiento::CANCELADO => $conteo["cancelados"]++,
                    default => null,
                };
            }

            return $conteo;
        });
        $this->ocupacion->cambio($salidaId);

        return ["salida" => $this->vistaSalida($this->salida($salidaId))] + $conteo;
    }

    /**
     * Quién chequea (el resto lo dice el flujo): la estación donde sube el
     * pasajero, o un usuario de oficina.
     *
     * @throws SalidaRechazada
     */
    private function exigirEstacion(BoletoAsiento $b, ?int $estacion): void
    {
        $sube = $b->getTrayecto()->getOrigen();
        if (!ReglasAbordaje::puedeChequearEn($estacion, (int) $sube?->getId())) {
            throw new SalidaRechazada(sprintf("El boleto %d (asiento %d) sube en %s: lo chequea esa estación.", $b->getId(), $b->getAsiento()->getNumero(), $sube?->getNombre() ?? "otra parada"), "estacion_no_sube", 403);
        }
    }

    /** @throws SalidaRechazada */
    private function exigirDesdeElOrigen(Salida $salida, Usuario $usuario, string $operacion): void
    {
        $origen = $salida->getRuta()?->getOrigen();
        ReglasAbordaje::exigirDesdeElOrigen($this->estacionDe($usuario), (int) $origen?->getId(), (string) $origen?->getNombre(), $operacion);
    }

    /** Id de la estación del usuario (una estación es un enclave), o null si es de oficina. */
    private function estacionDe(Usuario $usuario): ?int
    {
        $id = $this->em->find(Usuario::class, $usuario->getId())?->getEstacion()?->getId();

        return $id === null ? null : (int) $id;
    }

    private function salida(int $id): Salida
    {
        return $this->em->find(Salida::class, $id)
            ?? throw new SalidaRechazada("La salida no existe.", "salida_inexistente", 404);
    }

    private function bloquear(int $id): Salida
    {
        return $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE)
            ?? throw new SalidaRechazada("La salida no existe.", "salida_inexistente", 404);
    }

    /** @return array<string, mixed> */
    private function vistaSalida(Salida $s): array
    {
        return [
            "id" => (int) $s->getId(),
            "fecha" => $s->getFecha()?->format(DATE_ATOM),
            "estado" => $s->getEstado()->value,
            "ruta" => VistaSalida::ruta($s->getRuta()),
            "origenId" => $s->getRuta()->getOrigen()?->getId(),
            "destinoId" => $s->getRuta()->getDestino()?->getId(),
            "empresa" => $s->getEmpresa()?->getNombreCorto(),
            "bus" => $s->getBus()?->getCodigo(),
        ];
    }

    /** @return array<string, mixed> */
    private function vistaBoleto(BoletoAsiento $b, ?int $estacion): array
    {
        $venta = $b->getBoletoVenta();
        $cliente = $b->getCliente() ?? $venta->getCliente();
        $factura = $venta->getFactura();
        $sube = $b->getTrayecto()->getOrigen();

        return [
            "id" => (int) $b->getId(),
            "asiento" => $b->getAsiento()->getNumero(),
            "clase" => $b->getAsiento()->getClase()->value,
            "estado" => $b->getEstado()->value,
            "documento" => match (true) {
                $venta->isVoucher() => "Voucher",
                $venta->isCortesia() => "Cortesía",
                $factura !== null => trim(sprintf("%s %s", $factura->getSerie(), $factura->getDte())),
                $venta->getAgencia() !== null => sprintf("Agencia %s", $venta->getAgencia()->getNombre()),
                $venta->getCanal() === CanalVenta::WEB => "Página web",
                default => null,
            },
            "sube" => $sube?->getNombre(),
            "baja" => $b->getTrayecto()->getDestino()?->getNombre(),
            "ordenSube" => $b->getOrdenSube(),
            "cliente" => $cliente instanceof Cliente ? mb_strtoupper($cliente->getNombreCompleto()) : null,
            "chequeable" => $this->flujo->puede($b, TransicionBoleto::alChequear($b->getSalida()->getEstado()))
                && ReglasAbordaje::puedeChequearEn($estacion, (int) $sube?->getId()),
        ];
    }
}
