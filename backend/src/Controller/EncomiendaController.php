<?php

declare(strict_types=1);

namespace App\Controller;

use App\Encomienda\ArticuloSolicitado;
use App\Encomienda\ConsultaEncomiendas;
use App\Encomienda\DatosEncomienda;
use App\Encomienda\EncomiendaRechazada;
use App\Encomienda\EntregaEncomienda;
use App\Encomienda\MovimientosEncomienda;
use App\Encomienda\RegistroEncomienda;
use App\Encomienda\RutasEncomienda;
use App\Encomienda\SolicitudEncomienda;
use App\Encomienda\TarifasEncomienda;
use App\Entity\Empresa;
use App\Entity\Encomienda;
use App\Entity\EncomiendaEspecial;
use App\Entity\Enclave;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Estacion;
use App\Entity\Moneda;
use App\Entity\Salida;
use App\Entity\TipoPago;
use App\Entity\Usuario;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Encomiendas (ADR-035): registrar, consultar, embarcar, desembarcar,
 * entregar y anular. Un usuario con empresa solo ve y opera las de su
 * empresa; uno con estación, solo en su estación.
 *
 * Permisos: `encomienda.ver`, `encomienda.registrar`, `encomienda.interna`
 * (sin cobro), `encomienda.embarcar` (embarcar y desembarcar),
 * `encomienda.entregar`, `encomienda.anular`, `encomienda.especiales`
 * (catálogo de especiales); `venta.sin_factura` para la contingencia de la
 * factura. `ROLE_ADMIN` los tiene todos.
 */
#[AsController]
#[Route("/api/encomiendas", name: "api_encomiendas_")]
final class EncomiendaController extends AbstractController
{
    public const VER = "encomienda.ver";
    public const REGISTRAR = "encomienda.registrar";
    public const INTERNA = "encomienda.interna";
    public const EMBARCAR = "encomienda.embarcar";
    public const ENTREGAR = "encomienda.entregar";
    public const ANULAR = "encomienda.anular";
    public const ESPECIALES = "encomienda.especiales";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConsultaEncomiendas $consulta,
    ) {}

    /** Quién opera y con qué: estación, empresa, permisos y catálogos del formulario. */
    #[Route("/contexto", name: "contexto", methods: ["GET"])]
    public function contexto(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        if (!$this->isGranted(self::VER) && !$this->isGranted(self::REGISTRAR)) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $usuario->getEmpresa();
        $estacion = $usuario->getEstacion();

        return $this->json([
            "usuario" => ["id" => $usuario->getId(), "username" => $usuario->getUsername()],
            "estacion" => $estacion === null ? null : ["id" => $estacion->getId(), "nombre" => $estacion->getNombre(), "departamento" => $estacion->getDepartamento()],
            "empresa" => $empresa === null ? null : ["id" => $empresa->getId(), "nombre" => $empresa->getNombre()],
            "permisos" => [
                "ver" => $this->isGranted(self::VER),
                "registrar" => $this->isGranted(self::REGISTRAR),
                "interna" => $this->isGranted(self::INTERNA),
                "embarcar" => $this->isGranted(self::EMBARCAR),
                "entregar" => $this->isGranted(self::ENTREGAR),
                "anular" => $this->isGranted(self::ANULAR),
                "especiales" => $this->isGranted(self::ESPECIALES),
                "sinFactura" => $this->isGranted(VentaController::SIN_FACTURA),
            ],
            "empresas" => array_map(
                static fn(Empresa $e) => ["id" => $e->getId(), "nombre" => $e->getNombre(), "alias" => $e->getAlias()],
                $empresa !== null ? [$empresa] : $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"]),
            ),
            "estaciones" => array_map(
                static fn(Estacion $e) => ["id" => $e->getId(), "nombre" => $e->getNombre(), "direccion" => $e->getDireccion(), "departamento" => $e->getDepartamento()],
                $this->em->getRepository(Estacion::class)->findBy([], ["nombre" => "ASC"]),
            ),
            "especiales" => array_map(
                static fn(EncomiendaEspecial $e) => ["id" => $e->getId(), "nombre" => $e->getNombre(), "descripcion" => $e->getDescripcion(), "precio" => DatosBoleto::importe($e->getPrecio()), "permitePorCobrar" => $e->isPermitePorCobrar()],
                $this->em->getRepository(EncomiendaEspecial::class)->findBy(["activo" => true], ["nombre" => "ASC"]),
            ),
            "tiposPago" => array_map(static fn(TipoPago $t) => ["id" => $t->getId(), "nombre" => $t->getNombre()], $this->em->getRepository(TipoPago::class)->findBy(["activo" => true], ["nombre" => "ASC"])),
            "monedas" => array_map(static fn(Moneda $m) => ["id" => $m->getId(), "sigla" => $m->getSigla(), "nombre" => $m->getNombre()], $this->em->getRepository(Moneda::class)->findBy(["activo" => true], ["nombre" => "ASC"])),
            "porPagina" => ConsultaEncomiendas::POR_PAGINA,
        ]);
    }

    /** `{ origen?, destino, articulos, modalidad? }` → precio por artículo, descuento y total. */
    #[Route("/cotizacion", name: "cotizacion", methods: ["POST"])]
    public function cotizacion(Request $request, #[CurrentUser] Usuario $usuario, TarifasEncomienda $tarifas): JsonResponse
    {
        if (!$this->isGranted(self::REGISTRAR) && !$this->isGranted(VentaController::VENDER)) {
            throw $this->createAccessDeniedException();
        }

        return $this->responder(function () use ($request, $usuario, $tarifas) {
            $datos = $request->toArray();
            $origenId = SolicitudEncomienda::entero($datos["origen"] ?? null, "origen", false);
            $origen = $origenId !== null ? $this->em->find(Enclave::class, $origenId) : $usuario->getEstacion();
            $destino = $this->em->find(Enclave::class, (int) SolicitudEncomienda::entero($datos["destino"] ?? null, "destino"));
            if ($origen === null || $destino === null) {
                throw new EncomiendaRechazada("Indique el origen y el destino.");
            }
            $porCobrar = ($datos["modalidad"] ?? null) === ModalidadEncomienda::POR_COBRAR->value;

            return $tarifas->cotizar($origen, $destino, ArticuloSolicitado::lista($datos["articulos"] ?? null), $porCobrar)["cotizacion"]->toArray();
        });
    }

    /** `?origen=&destino=`: rutas que van del origen al destino, con las empresas que las operan esta semana. */
    #[Route("/rutas", name: "rutas", methods: ["GET"])]
    public function rutas(Request $request, RutasEncomienda $rutas, ClockInterface $reloj): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::REGISTRAR);
        $lista = $rutas->entre($request->query->getInt("origen"), $request->query->getInt("destino"));
        $empresas = [];
        if ($lista !== []) {
            $filas = $this->em->createQueryBuilder()
                ->select("IDENTITY(s.ruta) AS ruta", "e.id", "e.nombre")
                ->distinct()
                ->from(Salida::class, "s")
                ->join("s.empresa", "e")
                ->where("s.ruta IN (:rutas) AND s.fecha >= :desde AND s.fecha < :hasta AND s.estado <> :cancelada")
                ->setParameter("rutas", $lista)
                ->setParameter("desde", \DateTime::createFromImmutable($reloj->now()->setTime(0, 0)))
                ->setParameter("hasta", \DateTime::createFromImmutable($reloj->now()->setTime(0, 0)->modify("+8 days")))
                ->setParameter("cancelada", EstadoSalida::CANCELADA->value)
                ->getQuery()->getScalarResult();
            foreach ($filas as $f) {
                $empresas[(int) $f["ruta"]][] = ["id" => (int) $f["id"], "nombre" => $f["nombre"]];
            }
        }

        return $this->json(array_map(static fn($r) => [
            "id" => $r->getId(),
            "nombre" => $r->getNombre(),
            "empresas" => $empresas[(int) $r->getId()] ?? [],
        ], $lista));
    }

    /**
     * Registra una encomienda sin boleto (ver `SolicitudEncomienda`). 201 con
     * sus datos; 502 `{ codigo: "facturacion", recuperable, permiteSinFactura }`
     * si el certificador falló (no quedó registrada).
     */
    #[Route("", name: "registrar", methods: ["POST"])]
    public function registrar(Request $request, #[CurrentUser] Usuario $usuario, RegistroEncomienda $registro): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::REGISTRAR);

        return $this->responder(function () use ($request, $usuario, $registro) {
            $s = SolicitudEncomienda::desdeArray($request->toArray());
            if ($s->modalidad === ModalidadEncomienda::INTERNA && !$this->isGranted(self::INTERNA)) {
                throw new EncomiendaRechazada("No tiene permiso para registrar encomiendas internas.", "permiso", 403);
            }
            if ($s->sinFacturaElectronica && !$this->isGranted(VentaController::SIN_FACTURA)) {
                throw new EncomiendaRechazada("No tiene permiso para registrar sin factura electrónica.", "permiso", 403);
            }

            return DatosEncomienda::de($registro->registrar($s, $usuario, $this->isGranted(VentaController::SIN_FACTURA)));
        }, Response::HTTP_CREATED);
    }

    /** Listado paginado (filtros en `ConsultaEncomiendas::listado`), lo más nuevo primero. */
    #[Route("", name: "listado", methods: ["GET"])]
    public function listado(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->json($this->consulta->listado($request->query->all(), $usuario->getEmpresa()?->getId()));
    }

    #[Route("/{id<\d+>}", name: "ver", methods: ["GET"])]
    public function ver(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->json(DatosEncomienda::de($this->encomienda($id, $usuario)));
    }

    /** `{ encomiendas: [id], salida }` → `{ embarcadas: [id], fallidas: [{ id, codigo, error }] }`. */
    #[Route("/embarcar", name: "embarcar", methods: ["POST"])]
    public function embarcar(Request $request, #[CurrentUser] Usuario $usuario, MovimientosEncomienda $movimientos): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EMBARCAR);

        return $this->responder(function () use ($request, $usuario, $movimientos) {
            $datos = $request->toArray();

            return $movimientos->embarcar(
                array_map("intval", (array) ($datos["encomiendas"] ?? [])),
                (int) SolicitudEncomienda::entero($datos["salida"] ?? null, "salida"),
                $usuario,
                fn(Encomienda $e) => $this->puedeVer($e, $usuario),
            );
        });
    }

    /** `{ encomiendas: [id], estacion? }` → `{ desembarcadas: [id], fallidas }`. La estación es la del usuario (o la indicada, de oficina). */
    #[Route("/desembarcar", name: "desembarcar", methods: ["POST"])]
    public function desembarcar(Request $request, #[CurrentUser] Usuario $usuario, MovimientosEncomienda $movimientos): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EMBARCAR);

        return $this->responder(function () use ($request, $usuario, $movimientos) {
            $datos = $request->toArray();

            return $movimientos->desembarcar(
                array_map("intval", (array) ($datos["encomiendas"] ?? [])),
                SolicitudEncomienda::entero($datos["estacion"] ?? null, "estacion", false),
                $usuario,
                fn(Encomienda $e) => $this->puedeVer($e, $usuario),
            );
        });
    }

    /** `{ receptor?, clienteFactura?, tipoPago?, moneda?, sinFacturaElectronica? }` (ver `EntregaEncomienda`). */
    #[Route("/{id<\d+>}/entregar", name: "entregar", methods: ["POST"])]
    public function entregar(int $id, Request $request, #[CurrentUser] Usuario $usuario, MovimientosEncomienda $movimientos): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ENTREGAR);
        $this->encomienda($id, $usuario);

        return $this->responder(fn() => DatosEncomienda::de($movimientos->entregar(
            $id,
            EntregaEncomienda::desdeArray($request->toArray()),
            $usuario,
            $this->isGranted(VentaController::SIN_FACTURA),
        )));
    }

    /**
     * Entrega múltiple: `{ encomiendas: [id], receptor?, clienteFactura?, tipoPago?, moneda?, sinFacturaElectronica? }`
     * → `{ entregadas: [id], fallidas: [{ id, codigo, error, facturacion?, recuperable? }] }`.
     */
    #[Route("/entregar", name: "entregar_varias", methods: ["POST"])]
    public function entregarVarias(Request $request, #[CurrentUser] Usuario $usuario, MovimientosEncomienda $movimientos): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ENTREGAR);

        return $this->responder(function () use ($request, $usuario, $movimientos) {
            $datos = $request->toArray();

            return $movimientos->entregarVarias(
                array_map("intval", (array) ($datos["encomiendas"] ?? [])),
                EntregaEncomienda::desdeArray($datos),
                $usuario,
                $this->isGranted(VentaController::SIN_FACTURA),
                fn(Encomienda $e) => $this->puedeVer($e, $usuario),
            );
        });
    }

    /** `{ ruta }`: otra ruta para la que espera en bodega (pendientes de envío). */
    #[Route("/{id<\d+>}/ruta", name: "ruta", methods: ["POST"])]
    public function cambiarRuta(int $id, Request $request, #[CurrentUser] Usuario $usuario, MovimientosEncomienda $movimientos, RutasEncomienda $rutas): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::REGISTRAR);
        $this->encomienda($id, $usuario);

        return $this->responder(fn() => DatosEncomienda::de($movimientos->cambiarRuta(
            $id,
            (int) SolicitudEncomienda::entero($request->toArray()["ruta"] ?? null, "ruta"),
            $usuario,
            $rutas,
        )));
    }

    /** `?estacion=`: lo que espera en la bodega de la estación (la del usuario, si tiene) para salir. */
    #[Route("/pendientes-envio", name: "pendientes_envio", methods: ["GET"])]
    public function pendientesEnvio(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->responder(fn() => $this->consulta->pendientesEnvio($this->estacion($request, $usuario), $usuario->getEmpresa()?->getId()));
    }

    /** `?estacion=&q=`: lo que se puede entregar en la estación (la del usuario, si tiene). */
    #[Route("/pendientes-entrega", name: "pendientes_entrega", methods: ["GET"])]
    public function pendientesEntrega(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ENTREGAR);

        return $this->responder(fn() => $this->consulta->pendientesEntrega(
            $this->estacion($request, $usuario),
            $usuario->getEmpresa()?->getId(),
            trim((string) $request->query->get("q", "")) ?: null,
        ));
    }

    /** Catálogo de especiales, también los inactivos (para administrarlo). */
    #[Route("/especiales", name: "especiales", methods: ["GET"])]
    public function especiales(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ESPECIALES);

        return $this->json(array_map(
            static fn(EncomiendaEspecial $e) => self::especial($e),
            $this->em->getRepository(EncomiendaEspecial::class)->findBy([], ["activo" => "DESC", "nombre" => "ASC"]),
        ));
    }

    /** `{ nombre, descripcion?, precio, permitePorCobrar, activo }` (el «Registrar especial» del legado). */
    #[Route("/especiales", name: "especial_crear", methods: ["POST"])]
    public function crearEspecial(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ESPECIALES);

        return $this->responder(fn() => self::especial($this->guardarEspecial(new EncomiendaEspecial(), $request->toArray())), Response::HTTP_CREATED);
    }

    #[Route("/especiales/{id<\d+>}", name: "especial_editar", methods: ["POST"])]
    public function editarEspecial(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ESPECIALES);
        $especial = $this->em->find(EncomiendaEspecial::class, $id) ?? throw $this->createNotFoundException();

        return $this->responder(fn() => self::especial($this->guardarEspecial($especial, $request->toArray())));
    }

    /** `{ motivo }`: anula la que no ha viajado (y su factura en el certificador). */
    #[Route("/{id<\d+>}/anular", name: "anular", methods: ["POST"])]
    public function anular(int $id, Request $request, #[CurrentUser] Usuario $usuario, MovimientosEncomienda $movimientos): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ANULAR);
        $this->encomienda($id, $usuario);

        return $this->responder(fn() => DatosEncomienda::de($movimientos->anular($id, (string) ($request->toArray()["motivo"] ?? ""), $usuario)));
    }

    /** `?fecha=AAAA-MM-DD&estacion=&destino=`: salidas que pasan por la estación (la del usuario, si tiene) ese día; con `destino`, si llegan hasta él. */
    #[Route("/salidas", name: "salidas", methods: ["GET"])]
    public function salidas(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EMBARCAR);

        return $this->responder(function () use ($request, $usuario) {
            $dia = \DateTimeImmutable::createFromFormat("!Y-m-d", (string) $request->query->get("fecha"))
                ?: throw new EncomiendaRechazada("Fecha inválida (AAAA-MM-DD).");

            return $this->consulta->salidasDeEstacion($dia, $this->estacion($request, $usuario), $usuario->getEmpresa()?->getId(), $request->query->getInt("destino") ?: null);
        });
    }

    /** `?estacion=`: lo que va a bordo de la salida y lo que espera en la bodega de la estación. */
    #[Route("/salidas/{id<\d+>}", name: "procesar", methods: ["GET"])]
    public function procesar(Salida $salida, Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EMBARCAR);

        return $this->responder(fn() => $this->consulta->procesar($salida, $this->estacion($request, $usuario), $usuario->getEmpresa()?->getId()));
    }

    /** @param array<string, mixed> $d */
    private function guardarEspecial(EncomiendaEspecial $e, array $d): EncomiendaEspecial
    {
        $nombre = trim((string) ($d["nombre"] ?? ""));
        if ($nombre === "" || mb_strlen($nombre) > 100) {
            throw new EncomiendaRechazada("Indique el nombre (hasta 100 caracteres).");
        }
        $precio = $d["precio"] ?? null;
        if (!is_numeric($precio) || (float) $precio < 0 || (float) $precio > 100000) {
            throw new EncomiendaRechazada("Indique un precio válido.");
        }
        // Uno nuevo todavía no tiene id (y `Base::$id` no se lee antes de persistirlo).
        $id = $this->em->contains($e) ? $e->getId() : null;
        $repetido = $this->em->createQueryBuilder()
            ->select("COUNT(x.id)")->from(EncomiendaEspecial::class, "x")
            ->where("LOWER(x.nombre) = :n AND x.activo = true" . ($id !== null ? " AND x.id <> :id" : ""))
            ->setParameter("n", mb_strtolower($nombre));
        if ($id !== null) {
            $repetido->setParameter("id", $id);
        }
        if ((bool) ($d["activo"] ?? true) && (int) $repetido->getQuery()->getSingleScalarResult() > 0) {
            throw new EncomiendaRechazada("Ya hay un especial activo con ese nombre.", "repetido", 409);
        }
        $descripcion = trim((string) ($d["descripcion"] ?? ""));
        $e->setNombre($nombre)
            ->setDescripcion($descripcion === "" ? null : mb_substr($descripcion, 0, 255))
            ->setPrecioMonto(round((float) $precio, 2))
            ->setPermitePorCobrar((bool) ($d["permitePorCobrar"] ?? true))
            ->setActivo((bool) ($d["activo"] ?? true));
        $this->em->persist($e);
        $this->em->flush();

        return $e;
    }

    /** @return array<string, mixed> */
    private static function especial(EncomiendaEspecial $e): array
    {
        return [
            "id" => $e->getId(),
            "nombre" => $e->getNombre(),
            "descripcion" => $e->getDescripcion(),
            "precio" => DatosBoleto::importe($e->getPrecio()),
            "permitePorCobrar" => $e->isPermitePorCobrar(),
            "activo" => $e->isActivo(),
            "legado" => $e->getLegacyId() !== null,
        ];
    }

    private function estacion(Request $request, Usuario $usuario): Estacion
    {
        return $usuario->getEstacion()
            ?? $this->em->find(Estacion::class, $request->query->getInt("estacion"))
            ?? throw new EncomiendaRechazada("Elija la estación.");
    }

    private function encomienda(int $id, Usuario $usuario): Encomienda
    {
        $e = $this->em->find(Encomienda::class, $id) ?? throw $this->createNotFoundException();
        if (!$this->puedeVer($e, $usuario)) {
            throw $this->createAccessDeniedException();
        }

        return $e;
    }

    private function puedeVer(Encomienda $e, Usuario $usuario): bool
    {
        return $usuario->getEmpresa() === null || $usuario->getEmpresa()->getId() === $e->getEmpresa()?->getId();
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
}
