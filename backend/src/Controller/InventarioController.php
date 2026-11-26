<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Inventario\ConsultaInventario;
use App\Inventario\Inventario;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Inventario de los almacenes (ADR-071). Cualquier usuario con sesión
 * entra: ve los almacenes donde tiene alguna capacidad `inventario.*` y
 * cada operación pide la suya en ese almacén (`Inventario`). Los artículos
 * y los almacenes se mantienen por el CRUD genérico (`inventario.articulos`).
 * Errores: `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/inventario", name: "api_inventario_")]
final class InventarioController extends AbstractController
{
    public function __construct(
        private readonly Inventario $inventario,
        private readonly ConsultaInventario $consulta,
    ) {}

    /** `[{ id, nombre, tipo, empresa, estacion, bus, activo, faltantes, puede: { mover, ajustar, niveles } }]` */
    #[Route("/almacenes", name: "almacenes", methods: ["GET"])]
    public function almacenes(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json($this->consulta->almacenes($usuario));
    }

    /** `{ almacen, filas: [{ articulo, cantidad, costoPromedio, valor, minimo, puntoReorden, maximo, estado, reponer, enCamino }], valor }` */
    #[Route("/almacenes/{id<\d+>}/existencias", name: "existencias", methods: ["GET"])]
    public function existencias(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json($this->consulta->existencias($usuario, $id));
    }

    /** `?articulo=&pagina=` */
    #[Route("/almacenes/{id<\d+>}/movimientos", name: "movimientos", methods: ["GET"])]
    public function movimientos(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $articulo = $request->query->getInt("articulo");

        return $this->json($this->consulta->movimientos($usuario, $id, $articulo > 0 ? $articulo : null, $request->query->getInt("pagina", 1)));
    }

    /** A dónde puede enviar ese almacén: los demás activos de su empresa. */
    #[Route("/almacenes/{id<\d+>}/destinos", name: "destinos", methods: ["GET"])]
    public function destinos(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json($this->consulta->destinos($usuario, $id));
    }

    /** `{ minimo, puntoReorden?, maximo? }` */
    #[Route("/almacenes/{almacen<\d+>}/articulos/{articulo<\d+>}/niveles", name: "niveles", methods: ["POST"])]
    public function niveles(int $almacen, int $articulo, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $e = $this->inventario->niveles($usuario, $almacen, $articulo, $request->toArray());

        return $this->json($this->consulta->existencia($e));
    }

    /** Los artículos activos, para elegir qué entra o se mueve. */
    #[Route("/articulos", name: "articulos", methods: ["GET"])]
    public function articulos(): JsonResponse
    {
        return $this->json($this->consulta->articulos());
    }

    /** Los envíos en camino desde o hacia los almacenes que ve. */
    #[Route("/en-camino", name: "en_camino", methods: ["GET"])]
    public function enCamino(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json($this->consulta->enCamino($usuario));
    }

    /** Lo que toca reponer en los almacenes que ve, lo más grave primero. */
    #[Route("/por-reponer", name: "por_reponer", methods: ["GET"])]
    public function porReponer(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json($this->consulta->porReponer($usuario));
    }

    /** `{ tipo: entrada|salida|envio|ajuste, almacen, articulo, cantidad, costo?, destino?, documento?, motivo?, afectacion? }` */
    #[Route("/movimientos", name: "registrar", methods: ["POST"])]
    public function registrar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $m = $this->inventario->registrar($usuario, $request->toArray());

        return $this->json($this->consulta->movimiento($m), Response::HTTP_CREATED);
    }

    /** `{ documento? }`: el envío llegó entero al destino. */
    #[Route("/envios/{id<\d+>}/recibir", name: "recibir", methods: ["POST"])]
    public function recibir(int $id, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $documento = $request->getContent() === "" ? null : ($request->toArray()["documento"] ?? null);
        $m = $this->inventario->recibir($usuario, $id, is_string($documento) ? $documento : null);

        return $this->json($this->consulta->movimiento($m), Response::HTTP_CREATED);
    }
}
