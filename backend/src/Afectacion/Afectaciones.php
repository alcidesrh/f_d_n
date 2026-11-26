<?php

declare(strict_types=1);

namespace App\Afectacion;

use App\Acceso\Autorizacion;
use App\Acceso\EstacionTrabajo;
use App\Chat\Conversaciones;
use App\Chat\Tarjeta\TarjetaAfectacion;
use App\Entity\Afectacion;
use App\Entity\Bus;
use App\Entity\Empresa;
use App\Entity\Enum\EstadoAfectacion;
use App\Entity\Enum\SujetoAfectacion;
use App\Entity\Estacion;
use App\Entity\TipoAfectacion;
use App\Entity\Usuario;
use App\Programador\EjecucionManual;
use App\Programador\Tarea;
use App\Venta\Transaccion;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Lo que cambia una afectación (ADR-070): reportarla (quien tiene el `owner`
 * del tipo donde cae), crearla sola desde un detector, editarla y resolverla
 * (quien tiene el `target`). Avisar es del centinela: al crearse una, se le
 * despierta para que el primer aviso salga en segundos.
 */
final class Afectaciones
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly Autorizacion $autorizacion,
        private readonly Destinatarios $destinatarios,
        private readonly EstacionTrabajo $estacionTrabajo,
        private readonly EjecucionManual $ejecucion,
        private readonly Conversaciones $chat,
        private readonly ClockInterface $reloj,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * `{ tipo, descripcion, empresa?, estacion?, bus? }`. Lo que falta sale
     * de quien reporta: su estación de trabajo y, si su alcance tiene una
     * sola empresa, esa.
     *
     * @param array<string, mixed> $datos
     */
    public function reportar(Usuario $yo, array $datos): Afectacion
    {
        $tipo = $this->em->find(TipoAfectacion::class, self::id($datos["tipo"] ?? null) ?? 0);
        if ($tipo === null || !$tipo->isActivo()) {
            throw new AfectacionRechazada("Elija un tipo de afectación.", "falta_tipo");
        }
        [$empresa, $estacion, $bus] = $this->ubicar($yo, $tipo, $datos);
        $permisos = $this->autorizacion->de($yo);
        $dimensiones = $this->destinatarios->dimensiones([$tipo->getOwner()]);
        if (!ReglasAfectacion::alcanza($permisos, [$tipo->getOwner()], $dimensiones, $empresa?->getId(), $estacion?->getId())) {
            throw new AfectacionRechazada(sprintf("No puede reportar «%s» ahí.", $tipo->getNombre()), "sin_permiso", 403);
        }
        $a = new Afectacion($tipo, (string) ($datos["descripcion"] ?? ""), $empresa, $estacion, $bus, $yo, $this->ahora());

        return $this->crear($a);
    }

    /**
     * Para los detectores (stock bajo el mínimo, un GPS que calla…): crea la
     * afectación del tipo `$codigo` si no hay otra abierta con la misma
     * `$clave`; si la hay, la devuelve sin tocarla. Null si el tipo no existe
     * o está inactivo (el administrador apagó ese detector).
     */
    public function detectar(string $codigo, string $clave, string $descripcion, ?Empresa $empresa = null, ?Estacion $estacion = null, ?Bus $bus = null): ?Afectacion
    {
        $tipo = $this->em->getRepository(TipoAfectacion::class)->findOneBy(["codigo" => $codigo]);
        if ($tipo === null || !$tipo->isActivo()) {
            return null;
        }
        $abierta = $this->em->createQueryBuilder()
            ->select("a")
            ->from(Afectacion::class, "a")
            ->where("a.tipo = :tipo AND a.clave = :clave AND a.estado <> :resuelta")
            ->setParameter("tipo", $tipo->getId())
            ->setParameter("clave", $clave)
            ->setParameter("resuelta", EstadoAfectacion::RESUELTA)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($abierta instanceof Afectacion) {
            return $abierta;
        }

        return $this->crear(new Afectacion($tipo, $descripcion, $empresa ?? $bus?->getEmpresa(), $estacion, $bus, null, $this->ahora(), $clave));
    }

    public function editar(int $id, Usuario $yo, string $descripcion): Afectacion
    {
        $a = $this->paraAtender($id, $yo);
        $a->editar($descripcion);
        $this->em->flush();

        return $a;
    }

    /** Desde `creada` o `pendiente`; avisa a quien la reportó. */
    public function resolver(int $id, Usuario $yo, ?string $resolucion): Afectacion
    {
        $a = $this->paraAtender($id, $yo);
        $this->transaccion->ejecutar(fn() => $a->resolver($yo, $resolucion, $this->ahora()));
        $reporto = $a->getReportadaPor();
        if ($reporto !== null && $reporto->getId() !== $yo->getId()) {
            try {
                $this->chat->avisarSistema([$reporto], ReglasAfectacion::avisoResuelta($a), [["tipo" => TarjetaAfectacion::TIPO, "id" => (int) $a->getId()]]);
            } catch (\Throwable $e) {
                $this->logger->error("No se pudo avisar la afectación #{id} resuelta: {error}", ["id" => $a->getId(), "error" => $e->getMessage(), "exception" => $e]);
            }
        }

        return $a;
    }

    private function crear(Afectacion $a): Afectacion
    {
        $this->transaccion->ejecutar(function () use ($a) {
            $this->em->persist($a);
            $this->transaccion->despuesDeConfirmar(fn() => $this->despertarCentinela());
        });

        return $a;
    }

    /** El primer aviso sale en la corrida siguiente del centinela; esta la adelanta. */
    private function despertarCentinela(): void
    {
        try {
            $this->ejecucion->encolar(Tarea::VIGILAR_AFECTACIONES);
        } catch (\Throwable $e) {
            // La corrida de cada minuto la avisa igual.
            $this->logger->warning("No se pudo adelantar el centinela de afectaciones: {error}", ["error" => $e->getMessage()]);
        }
    }

    private function paraAtender(int $id, Usuario $yo): Afectacion
    {
        $a = $this->em->find(Afectacion::class, $id);
        if ($a === null) {
            throw new AfectacionRechazada("La afectación no existe.", "afectacion_no_existe", 404);
        }
        if (!$this->destinatarios->alcanza($this->autorizacion->de($yo), $a, [$a->getTipo()->getTarget()])) {
            throw new AfectacionRechazada(sprintf("No atiende «%s» ahí.", $a->getTipo()->getNombre()), "sin_permiso", 403);
        }

        return $a;
    }

    /**
     * Empresa, estación y bus según sobre qué cae el tipo. Una estación es
     * obligatoria para lo de estación; en lo de bus o de empresa es dónde se
     * reportó (la de trabajo de quien reporta).
     *
     * @param array<string, mixed> $datos
     *
     * @return array{0: ?Empresa, 1: ?Estacion, 2: ?Bus}
     */
    private function ubicar(Usuario $yo, TipoAfectacion $tipo, array $datos): array
    {
        $estacionId = self::id($datos["estacion"] ?? null);
        $estacion = $estacionId !== null ? $this->em->find(Estacion::class, $estacionId) : $this->estacionTrabajo->actual($yo);
        if ($estacionId !== null && $estacion === null) {
            throw new AfectacionRechazada("La estación no existe.", "estacion_no_existe");
        }
        $bus = null;
        if ($tipo->getSujeto() === SujetoAfectacion::BUS) {
            $bus = $this->em->find(Bus::class, self::id($datos["bus"] ?? null) ?? 0);
            if ($bus === null) {
                throw new AfectacionRechazada("Elija el bus.", "falta_bus");
            }

            return [$bus->getEmpresa(), $estacion, $bus];
        }
        if ($tipo->getSujeto() === SujetoAfectacion::ESTACION && $estacion === null) {
            throw new AfectacionRechazada("Elija la estación.", "falta_estacion");
        }
        $empresaId = self::id($datos["empresa"] ?? null) ?? $this->autorizacion->de($yo)->alcance($tipo->getOwner())->empresaUnica();
        $empresa = $empresaId !== null ? $this->em->find(Empresa::class, $empresaId) : null;
        if ($empresa === null) {
            throw new AfectacionRechazada("Elija la empresa.", "falta_empresa");
        }

        return [$empresa, $estacion, null];
    }

    private function ahora(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->reloj->now());
    }

    private static function id(mixed $valor): ?int
    {
        if (is_string($valor) && preg_match('#/(\d+)$#', $valor, $m)) {
            $valor = $m[1];
        }
        $id = filter_var($valor, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

        return $id === false ? null : $id;
    }
}
