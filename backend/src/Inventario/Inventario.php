<?php

declare(strict_types=1);

namespace App\Inventario;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Entity\Afectacion;
use App\Entity\Almacen;
use App\Entity\Articulo;
use App\Entity\Enum\TipoMovimiento;
use App\Entity\Existencia;
use App\Entity\Movimiento;
use App\Entity\Usuario;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Psr\Clock\ClockInterface;

/**
 * Lo que cambia el inventario (ADR-071): cada cambio de una existencia va
 * con el `Movimiento` que lo explica, en la misma transacción y con la fila
 * de la existencia bloqueada (dos salidas a la vez no dejan un negativo).
 * Tras el commit, `AlertasExistencia` mira si quedó debajo del mínimo.
 *
 * - entrada, salida y envío: `inventario.mover` en el almacén;
 * - recepción: `inventario.mover` en el almacén que recibe;
 * - ajuste (un conteo): `inventario.ajustar`;
 * - niveles (mínimo, punto de reorden, máximo): `inventario.articulos`.
 *
 * Un almacén inactivo no recibe ni envía, pero se cuenta (para dejarlo en
 * cero) y recibe lo que ya venía en camino. Un artículo inactivo ya no
 * entra ni se envía; lo que queda se gasta o se ajusta.
 */
final class Inventario
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly Autorizacion $autorizacion,
        private readonly AlertasExistencia $alertas,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * `{ tipo: entrada|salida|envio|ajuste, almacen, articulo, cantidad, costo?, destino?, documento?, motivo?, afectacion? }`.
     * `cantidad` es lo que entra, sale o se envía (mayor que cero); en un
     * ajuste, lo contado. `costo` (quetzales por unidad) solo en la entrada.
     *
     * @param array<string, mixed> $datos
     */
    public function registrar(Usuario $yo, array $datos): Movimiento
    {
        $tipo = TipoMovimiento::tryFrom((string) ($datos["tipo"] ?? ""));
        if ($tipo === null || $tipo === TipoMovimiento::RECEPCION) {
            throw new InventarioRechazado("Elija entrada, salida, envío o ajuste.", "tipo_invalido");
        }
        $almacen = $this->almacen($datos["almacen"] ?? null);
        $articulo = $this->articulo($datos["articulo"] ?? null);
        $this->exigir($yo, $tipo === TipoMovimiento::AJUSTE ? Capacidad::INVENTARIO_AJUSTAR : Capacidad::INVENTARIO_MOVER, $almacen);
        if ($tipo !== TipoMovimiento::AJUSTE && !$almacen->isActivo()) {
            throw new InventarioRechazado(sprintf("«%s» está inactivo.", $almacen->getNombre()), "almacen_inactivo", 409);
        }
        if (in_array($tipo, [TipoMovimiento::ENTRADA, TipoMovimiento::ENVIO], true) && !$articulo->isActivo()) {
            throw new InventarioRechazado(sprintf("«%s» está inactivo.", $articulo->getNombre()), "articulo_inactivo", 409);
        }
        $cantidad = Cantidad::centesimas($datos["cantidad"] ?? null) ?? throw new InventarioRechazado("Escriba la cantidad (hasta 2 decimales).", "cantidad_invalida");
        $destino = $tipo === TipoMovimiento::ENVIO ? $this->destino($almacen, $datos["destino"] ?? null) : null;
        $costo = null;
        if ($tipo === TipoMovimiento::ENTRADA) {
            $costo = Cantidad::diezmilesimas($datos["costo"] ?? null);
            if ($costo === null || $costo < 0) {
                throw new InventarioRechazado("Escriba el costo por unidad (hasta 4 decimales).", "costo_invalido");
            }
        }
        $motivo = is_string($datos["motivo"] ?? null) ? $datos["motivo"] : null;
        if ($tipo === TipoMovimiento::AJUSTE && trim((string) $motivo) === "") {
            throw new InventarioRechazado("Diga por qué se ajusta (conteo, merma, rotura…).", "falta_motivo");
        }
        $documento = is_string($datos["documento"] ?? null) ? $datos["documento"] : null;
        $afectacion = $this->afectacion($datos["afectacion"] ?? null);

        return $this->transaccion->ejecutar(function () use ($yo, $tipo, $almacen, $articulo, $cantidad, $costo, $destino, $documento, $motivo, $afectacion) {
            $e = $this->existenciaBloqueada($almacen, $articulo);
            $ahora = $this->ahora();
            switch ($tipo) {
                case TipoMovimiento::ENTRADA:
                    $e->entrar($cantidad, (int) $costo);
                    $m = new Movimiento($tipo, $e, Cantidad::texto($cantidad), Cantidad::costoTexto((int) $costo), $yo, $ahora, $documento, $motivo);
                    break;
                case TipoMovimiento::AJUSTE:
                    $diferencia = $e->contar($cantidad);
                    $m = new Movimiento($tipo, $e, Cantidad::texto($diferencia), Cantidad::costoTexto($e->costo()), $yo, $ahora, $documento, $motivo);
                    break;
                default:
                    // Salida o envío: sale al costo promedio, que no cambia.
                    $e->sacar($cantidad);
                    $m = new Movimiento($tipo, $e, Cantidad::texto(-$cantidad), Cantidad::costoTexto($e->costo()), $yo, $ahora, $documento, $motivo);
                    if ($destino !== null) {
                        $m->hacia($destino);
                    }
            }
            $m->por($afectacion);
            $this->em->persist($m);
            $this->transaccion->despuesDeConfirmar(fn() => $this->alertas->revisar($e));

            return $m;
        });
    }

    /**
     * Llegó un envío: entra completo al almacén de destino, al costo con que
     * salió. Si llegó de menos o roto, se registra después una salida o un
     * ajuste con su motivo.
     */
    public function recibir(Usuario $yo, int $envioId, ?string $documento = null): Movimiento
    {
        return $this->transaccion->ejecutar(function () use ($yo, $envioId, $documento) {
            $envio = $this->em->find(Movimiento::class, $envioId, LockMode::PESSIMISTIC_WRITE);
            if ($envio === null || $envio->getTipo() !== TipoMovimiento::ENVIO) {
                throw new InventarioRechazado("El envío no existe.", "envio_no_existe", 404);
            }
            $destino = $envio->getDestino() ?? throw new InventarioRechazado("El almacén de destino ya no existe.", "destino_no_existe", 409);
            $this->exigir($yo, Capacidad::INVENTARIO_MOVER, $destino);
            $ahora = $this->ahora();
            $envio->recibido($ahora);
            $e = $this->existenciaBloqueada($destino, $envio->getArticulo());
            $cantidad = -(int) Cantidad::centesimas($envio->getCantidad());
            $costo = (int) Cantidad::diezmilesimas($envio->getCostoUnitario());
            $e->entrar($cantidad, $costo);
            $motivo = sprintf("Envío desde %s", $envio->getAlmacen()->getNombre());
            $m = (new Movimiento(TipoMovimiento::RECEPCION, $e, Cantidad::texto($cantidad), Cantidad::costoTexto($costo), $yo, $ahora, $documento ?? $envio->getDocumento(), $motivo))
                ->deEnvio($envio)
                ->por($envio->getAfectacion());
            $this->em->persist($m);
            $this->transaccion->despuesDeConfirmar(fn() => $this->alertas->revisar($e));

            return $m;
        });
    }

    /**
     * `{ minimo, puntoReorden?, maximo? }` (vacío = sin poner; el mínimo
     * vacío es 0, sin alerta). Crea la existencia si aún no la hay.
     *
     * @param array<string, mixed> $datos
     */
    public function niveles(Usuario $yo, int $almacenId, int $articuloId, array $datos): Existencia
    {
        $almacen = $this->almacen($almacenId);
        $articulo = $this->articulo($articuloId);
        $this->exigir($yo, Capacidad::INVENTARIO_ARTICULOS, $almacen);
        $nivel = static function (mixed $valor, string $nombre): ?int {
            if ($valor === null || (is_string($valor) && trim($valor) === "")) {
                return null;
            }

            return Cantidad::centesimas($valor) ?? throw new InventarioRechazado(sprintf("El %s debe ser un número con hasta 2 decimales.", $nombre), "nivel_invalido");
        };
        $minimo = $nivel($datos["minimo"] ?? null, "mínimo") ?? 0;
        $puntoReorden = $nivel($datos["puntoReorden"] ?? null, "punto de reorden");
        $maximo = $nivel($datos["maximo"] ?? null, "máximo");

        return $this->transaccion->ejecutar(function () use ($almacen, $articulo, $minimo, $puntoReorden, $maximo) {
            $e = $this->existenciaBloqueada($almacen, $articulo);
            $e->niveles($minimo, $puntoReorden, $maximo);
            $this->transaccion->despuesDeConfirmar(fn() => $this->alertas->revisar($e));

            return $e;
        });
    }

    /**
     * La existencia del artículo en el almacén, creada si no la hay y con su
     * fila bloqueada hasta el commit. Dentro de una transacción.
     */
    private function existenciaBloqueada(Almacen $almacen, Articulo $articulo): Existencia
    {
        $this->em->getConnection()->executeStatement(
            "INSERT INTO existencia (almacen_id, articulo_id) VALUES (:almacen, :articulo) ON CONFLICT (almacen_id, articulo_id) DO NOTHING",
            ["almacen" => $almacen->getId(), "articulo" => $articulo->getId()],
        );

        return $this->em->createQuery("SELECT e FROM " . Existencia::class . " e WHERE e.almacen = :almacen AND e.articulo = :articulo")
            ->setParameter("almacen", $almacen->getId())
            ->setParameter("articulo", $articulo->getId())
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getSingleResult();
    }

    private function exigir(Usuario $yo, Capacidad $capacidad, Almacen $almacen): void
    {
        if ($this->autorizacion->de($yo)->puede($capacidad->value, ReglasInventario::ubicacion($capacidad, $almacen))) {
            return;
        }
        $que = match ($capacidad) {
            Capacidad::INVENTARIO_AJUSTAR => "ajustar el inventario",
            Capacidad::INVENTARIO_ARTICULOS => "cambiar los niveles",
            default => "mover el inventario",
        };

        throw new InventarioRechazado(sprintf("No puede %s de «%s».", $que, $almacen->getNombre()), "sin_permiso", 403);
    }

    private function almacen(mixed $valor): Almacen
    {
        return $this->em->find(Almacen::class, self::id($valor) ?? 0) ?? throw new InventarioRechazado("Elija el almacén.", "falta_almacen");
    }

    private function articulo(mixed $valor): Articulo
    {
        return $this->em->find(Articulo::class, self::id($valor) ?? 0) ?? throw new InventarioRechazado("Elija el artículo.", "falta_articulo");
    }

    /** A dónde va un envío: otro almacén activo de la misma empresa. */
    private function destino(Almacen $origen, mixed $valor): Almacen
    {
        $destino = $this->em->find(Almacen::class, self::id($valor) ?? 0) ?? throw new InventarioRechazado("Elija a qué almacén se envía.", "falta_destino");
        if ($destino->getId() === $origen->getId()) {
            throw new InventarioRechazado("El envío va a otro almacén.", "destino_invalido");
        }
        if ($destino->getEmpresa()?->getId() !== $origen->getEmpresa()?->getId()) {
            throw new InventarioRechazado(sprintf("«%s» es de otra empresa.", $destino->getNombre()), "destino_invalido");
        }
        if (!$destino->isActivo()) {
            throw new InventarioRechazado(sprintf("«%s» está inactivo.", $destino->getNombre()), "almacen_inactivo", 409);
        }

        return $destino;
    }

    private function afectacion(mixed $valor): ?Afectacion
    {
        if ($valor === null || $valor === "") {
            return null;
        }

        return $this->em->find(Afectacion::class, self::id($valor) ?? 0) ?? throw new InventarioRechazado("La afectación no existe.", "afectacion_no_existe");
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
