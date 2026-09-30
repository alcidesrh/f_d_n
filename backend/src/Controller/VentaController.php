<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BoletoVenta;
use App\Entity\Cliente;
use App\Entity\Estacion;
use App\Entity\Moneda;
use App\Entity\Nacion;
use App\Entity\Salida;
use App\Entity\TipoDocumento;
use App\Entity\TipoPago;
use App\Entity\Usuario;
use App\Venta\Boleto\BoletoPdf;
use App\Venta\Boleto\Comprobantes;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Clientes;
use App\Venta\ConsultaVenta;
use App\Venta\Comprador;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\ConsultaContribuyente;
use App\Venta\Facturacion\Facturador;
use App\Venta\PublicadorOcupacion;
use App\Venta\RegistroVenta;
use App\Venta\ReglasVenta;
use App\Venta\SolicitudVenta;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Venta de boletos en taquilla y agencias (ADR-021). Los usuarios de una
 * agencia usan los mismos endpoints: la venta descuenta su saldo y no lleva
 * factura electrónica.
 *
 * Permisos (acciones planas, ADR-003): `venta.vender`; `venta.cortesia`
 * (asientos sin cobro); `venta.sin_factura` (continuar sin factura
 * electrónica si el certificador no responde). `ROLE_ADMIN` los tiene todos.
 */
#[AsController]
#[Route("/api/venta", name: "api_venta_")]
final class VentaController extends AbstractController
{
    public const VENDER = "venta.vender";
    public const CORTESIA = "venta.cortesia";
    public const SIN_FACTURA = "venta.sin_factura";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConsultaVenta $consulta,
        private readonly ReglasVenta $reglas,
        private readonly RegistroVenta $registro,
        private readonly Clientes $clientes,
        private readonly Comprobantes $comprobantes,
    ) {}

    /** Quién vende y con qué: canal, estación/agencia, permisos y catálogos del formulario. */
    #[Route("/contexto", name: "contexto", methods: ["GET"])]
    public function contexto(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);
        $agencia = $usuario->getAgencia();
        $catalogo = fn(string $clase, callable $fila) => array_map($fila, $this->em->getRepository($clase)->findBy(
            property_exists($clase, "activo") ? ["activo" => true] : [],
            ["nombre" => "ASC"],
        ));

        return $this->json([
            "canal" => $agencia !== null ? "agencia" : "estacion",
            "usuario" => ["id" => $usuario->getId(), "username" => $usuario->getUsername(), "nombre" => $usuario->getFullName()],
            "estacion" => $usuario->getEstacion() === null ? null : ["id" => $usuario->getEstacion()->getId(), "nombre" => $usuario->getEstacion()->getNombre()],
            "agencia" => $agencia === null ? null : [
                "id" => $agencia->getId(),
                "nombre" => $agencia->getNombre(),
                "saldo" => DatosBoleto::importe(new \Money\Money($agencia->getSaldo(), new \Money\Currency($agencia->getMoneda()))),
            ],
            "permisos" => [
                "cortesia" => $agencia === null && $this->isGranted(self::CORTESIA),
                "sinFactura" => $agencia === null && $this->isGranted(self::SIN_FACTURA),
            ],
            "estaciones" => array_map(
                static fn(Estacion $e) => ["id" => $e->getId(), "nombre" => $e->getNombre(), "direccion" => $e->getDireccion()],
                $this->em->getRepository(Estacion::class)->findBy([], ["nombre" => "ASC"]),
            ),
            "tiposPago" => $catalogo(TipoPago::class, static fn(TipoPago $t) => ["id" => $t->getId(), "nombre" => $t->getNombre()]),
            "monedas" => $catalogo(Moneda::class, static fn(Moneda $m) => ["id" => $m->getId(), "sigla" => $m->getSigla(), "nombre" => $m->getNombre()]),
            "tiposDocumento" => $catalogo(TipoDocumento::class, static fn(TipoDocumento $t) => ["id" => $t->getId(), "nombre" => $t->getNombre()]),
            "naciones" => $catalogo(Nacion::class, static fn(Nacion $n) => ["id" => $n->getId(), "nombre" => $n->getNombre()]),
        ]);
    }

    /** `?fecha=AAAA-MM-DD&estacion={id}`: salidas del día que pasan por la estación. */
    #[Route("/salidas", name: "salidas", methods: ["GET"])]
    public function salidas(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);
        $dia = \DateTimeImmutable::createFromFormat("!Y-m-d", (string) $request->query->get("fecha"));
        if ($dia === false) {
            return $this->json(["error" => "Fecha inválida (AAAA-MM-DD)."], Response::HTTP_BAD_REQUEST);
        }
        $estacion = $request->query->getInt("estacion") ?: null;

        return $this->json($this->consulta->salidasDeEstacion(
            $dia,
            $estacion,
            $usuario->getAgencia()?->getEmpresa()?->getId(),
        ));
    }

    #[Route("/salidas/{id<\d+>}", name: "salida", methods: ["GET"])]
    public function salida(Salida $salida): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);

        return $this->json([
            ...$this->consulta->detalle($salida),
            "topico" => PublicadorOcupacion::topico((int) $salida->getId()),
        ]);
    }

    /** `?trayecto={id}`: asientos ocupados para ese trayecto (por defecto, el del salida). */
    #[Route("/salidas/{id<\d+>}/ocupacion", name: "ocupacion", methods: ["GET"])]
    public function ocupacion(Salida $salida, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);

        return $this->responder(function () use ($salida, $request) {
            $trayecto = $this->reglas->trayecto($salida, $request->query->getInt("trayecto") ?: null);

            return ["asientos" => $this->consulta->ocupacion($salida, $this->reglas->tramo($salida, $trayecto))];
        });
    }

    /** `{ salida, trayecto?, asientos: [id], cobrarTrayectoCompleto?, cortesia? }` → precio por asiento y total. */
    #[Route("/cotizacion", name: "cotizacion", methods: ["POST"])]
    public function cotizacion(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);

        return $this->responder(function () use ($request) {
            $datos = $request->toArray();
            $salida = $this->em->find(Salida::class, (int) ($datos["salida"] ?? 0))
                ?? throw new VentaRechazada("El salida no existe.", "no_encontrado", 404);
            $trayecto = $this->reglas->trayecto($salida, isset($datos["trayecto"]) ? (int) $datos["trayecto"] : null);
            $asientos = $this->reglas->asientos($salida, array_map("intval", (array) ($datos["asientos"] ?? [])));

            return $this->reglas->cotizar(
                $salida,
                $trayecto,
                $asientos,
                (bool) ($datos["cobrarTrayectoCompleto"] ?? false),
                (bool) ($datos["cortesia"] ?? false),
            )->toArray();
        });
    }

    /**
     * Registra la venta (ver `SolicitudVenta`). 201 con el comprobante; 502
     * `{ codigo: "facturacion", recuperable, permiteSinFactura }` si el
     * certificador falló (la venta no quedó registrada).
     */
    #[Route("/ventas", name: "vender", methods: ["POST"])]
    public function vender(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);

        return $this->responder(function () use ($request, $usuario) {
            $solicitud = SolicitudVenta::desdeArray($request->toArray());
            if ($solicitud->cortesia && !$this->isGranted(self::CORTESIA)) {
                throw new VentaRechazada("No tiene permiso para emitir cortesías.", "permiso", 403);
            }
            if ($solicitud->sinFacturaElectronica && !$this->isGranted(self::SIN_FACTURA)) {
                throw new VentaRechazada("No tiene permiso para vender sin factura electrónica.", "permiso", 403);
            }

            return $this->comprobantes->de($this->registro->vender($solicitud, $usuario, $this->isGranted(self::SIN_FACTURA)));
        }, Response::HTTP_CREATED);
    }

    /** Comprobante de una venta (reimpresión del ticket). */
    #[Route("/ventas/{id<\d+>}", name: "venta", methods: ["GET"])]
    public function venta(BoletoVenta $venta, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyUnlessPuedeVer($venta, $usuario);

        return $this->json($this->comprobantes->de($venta));
    }

    #[Route("/ventas/{id<\d+>}/pdf", name: "venta_pdf", methods: ["GET"])]
    public function ventaPdf(BoletoVenta $venta, #[CurrentUser] Usuario $usuario, BoletoPdf $pdf): Response
    {
        $this->denyUnlessPuedeVer($venta, $usuario);

        return new Response($pdf->generar($venta), 200, [
            "Content-Type" => "application/pdf",
            "Content-Disposition" => sprintf('inline; filename="%s"', BoletoPdf::nombreArchivo($venta)),
        ]);
    }

    /** `?q=` (NIT, nombre o documento; 3+ caracteres): hasta 20 clientes. */
    #[Route("/clientes", name: "clientes", methods: ["GET"])]
    public function clientes(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);

        return $this->json($this->clientes->buscar((string) $request->query->get("q", "")));
    }

    /** `?nit=`: razón social registrada en la SAT (para completar el cliente). */
    #[Route("/nit", name: "nit", methods: ["GET"])]
    public function nit(Request $request, ConsultaContribuyente $contribuyentes): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);
        $nit = Facturador::normalizarNit((string) $request->query->get("nit"));
        if ($nit === "CF" || !Comprador::nitValido($nit)) {
            return $this->json(["error" => "NIT inválido (dígito verificador).", "codigo" => "nit_invalido"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $nombre = $contribuyentes->nombreDeNit($nit);
        } catch (CertificacionFallida $e) {
            return $this->json(["error" => $e->getMessage(), "codigo" => "sin_respuesta"], Response::HTTP_BAD_GATEWAY);
        }

        return $nombre === null
            ? $this->json(["error" => "La SAT no reconoce ese NIT.", "codigo" => "nit_inexistente"], Response::HTTP_NOT_FOUND)
            : $this->json(["nit" => $nit, "nombre" => $nombre]);
    }

    /** Alta rápida de cliente desde la venta. */
    #[Route("/clientes", name: "cliente_crear", methods: ["POST"])]
    public function crearCliente(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);

        return $this->responder(fn() => Clientes::fila($this->clientes->crear($request->toArray())), Response::HTTP_CREATED);
    }

    /** Edición rápida de cliente desde la venta. */
    #[Route("/clientes/{id<\d+>}", name: "cliente_editar", methods: ["PUT"])]
    public function editarCliente(Cliente $cliente, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VENDER);

        return $this->responder(fn() => Clientes::fila($this->clientes->actualizar($cliente, $request->toArray())));
    }

    /**
     * @param callable(): array<string, mixed>|list<mixed> $operacion
     */
    private function responder(callable $operacion, int $estado = Response::HTTP_OK): JsonResponse
    {
        try {
            return $this->json($operacion(), $estado);
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }

    /** El vendedor, su agencia, o quien tenga permiso de lectura de ventas. */
    private function denyUnlessPuedeVer(BoletoVenta $venta, Usuario $usuario): void
    {
        $agencia = $usuario->getAgencia();
        if ($agencia !== null) {
            if ($venta->getAgencia()?->getId() !== $agencia->getId()) {
                throw $this->createAccessDeniedException();
            }

            return;
        }
        if ($venta->getUsuario()?->getId() !== $usuario->getId() && !$this->isGranted("ROLE_ADMIN")) {
            $this->denyAccessUnlessGranted("read", BoletoVenta::class);
        }
    }
}
