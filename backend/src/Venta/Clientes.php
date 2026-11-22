<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Cliente;
use App\Entity\Nacion;
use App\Entity\TipoDocumento;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\Facturador;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Búsqueda y alta rápida de clientes desde la pantalla de venta.
 */
final class Clientes
{
    public const MIN_BUSQUEDA = 3;

    private const MAX_RESULTADOS = 20;

    /** Con menos resultados que estos, la búsqueda suma los de nombre parecido. */
    private const POCOS_RESULTADOS = 5;

    /** `word_similarity` desde la que un nombre «se parece»: en la búsqueda y al dar de alta. */
    private const UMBRAL_BUSQUEDA = 0.5;

    private const UMBRAL_NOMBRE = 0.6;

    /** Mismo teléfono y nombre al menos así de parecido: la misma persona. */
    private const UMBRAL_MISMA_PERSONA = 0.5;

    private const MAX_PARECIDOS = 6;

    private const BLOQUEO_COMODIN = 4204204;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Clientes cuyo NIT, documento, teléfono, nombre o apellido contiene el
     * término, sin acentos ni signos. Si con eso salen pocos, se completan con
     * los de nombre parecido (`parecido`: una letra de más o de menos; ADR-068).
     * Con "CF" devuelve los consumidores finales más recientes. El comodín va primero.
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(string $termino): array
    {
        $termino = trim($termino);
        if (mb_strlen($termino) < self::MIN_BUSQUEDA && strtoupper($termino) !== "CF") {
            return [];
        }
        $conexion = $this->em->getConnection();
        $parecidos = [];

        $nit = Facturador::normalizarNit($termino);
        if (ctype_digit(str_replace("K", "", $nit)) && strlen($nit) >= self::MIN_BUSQUEDA) {
            // NIT, documento o teléfono, cada uno por su índice (`nat_cliente_nit_prefijo`,
            // `nat_cliente_documento`, `nat_cliente_telefono`).
            $condiciones = ["nit LIKE :prefijo", IdentidadCliente::SQL_DOCUMENTO . " = :documento"];
            $parametros = ["prefijo" => $nit . "%", "documento" => IdentidadCliente::documento($termino)];
            if (($telefono = IdentidadCliente::telefonoComparable($termino)) !== null) {
                $condiciones[] = IdentidadCliente::SQL_TELEFONO . " = :telefono";
                $parametros["telefono"] = $telefono;
            }
        } else {
            $clave = IdentidadCliente::clave($termino);
            if ($clave === "") {
                return [];
            }
            // Cada palabra, por el índice de trigramas `nat_cliente_busqueda`.
            $condiciones = [];
            $parametros = [];
            foreach (array_slice(explode(" ", $clave), 0, 4) as $i => $p) {
                $condiciones[] = IdentidadCliente::SQL_CLAVE . " LIKE :p{$i}";
                $parametros["p{$i}"] = "%{$p}%";
            }
            $condiciones = ["(" . implode(" AND ", $condiciones) . ")"];
            // Un pasaporte o carné (letras y números) es una sola palabra.
            if (!str_contains($clave, " ") && strlen((string) IdentidadCliente::documento($termino)) >= 5) {
                $condiciones[] = IdentidadCliente::SQL_DOCUMENTO . " = :documento";
                $parametros["documento"] = IdentidadCliente::documento($termino);
            }
        }
        $ids = array_map(intval(...), $conexion->fetchFirstColumn(
            "SELECT id FROM cliente WHERE " . implode(" OR ", $condiciones) . " ORDER BY comodin DESC, id DESC LIMIT " . self::MAX_RESULTADOS,
            $parametros,
        ));
        if (isset($clave) && count($ids) < self::POCOS_RESULTADOS && mb_strlen($clave) >= 4) {
            $parecidos = $this->nombresParecidos($clave, $ids, self::MAX_RESULTADOS - count($ids), self::UMBRAL_BUSQUEDA);
        }

        $clientes = $this->cargar([...$ids, ...array_keys($parecidos)]);

        return array_values(array_map(
            static fn(Cliente $c) => [...self::fila($c), "parecido" => isset($parecidos[$c->getId()])],
            $clientes,
        ));
    }

    /**
     * Clientes ya registrados que podrían ser el que se va a dar de alta
     * (ADR-068). `fuerte`: mismo NIT, mismo documento, o mismo teléfono con
     * nombre parecido; casi seguro es la misma persona. Si no, solo se parece
     * (mismo teléfono, que puede ser de la familia, o nombre parecido).
     *
     * @param array<string, mixed> $datos los del formulario de cliente
     * @return list<array{cliente: array<string, mixed>, motivo: string, fuerte: bool}>
     */
    public function parecidos(array $datos, ?int $excepto = null): array
    {
        $nit = Facturador::normalizarNit(trim((string) ($datos["nit"] ?? "")));
        $nit = $nit !== "CF" && Comprador::nitValido($nit) ? $nit : null;
        $documento = IdentidadCliente::documento((string) ($datos["numeroDocumento"] ?? ""));
        $documento = strlen((string) $documento) >= 4 ? $documento : null;
        $telefono = IdentidadCliente::telefonoComparable((string) ($datos["telefono"] ?? ""));
        $nombre = IdentidadCliente::clave(trim(($datos["nombre"] ?? "") . " " . ($datos["apellido"] ?? "")));
        // Un nombre solo («Luis») es de cientos: hacen falta nombre y apellido.
        $nombre = str_contains($nombre, " ") ? $nombre : null;

        $columnas = ["id"];
        $condiciones = [];
        $parametros = ["nombre" => $nombre ?? "", "excepto" => $excepto ?? 0];
        // Cada valor va dos veces (columna y condición): con dos nombres, que PDO no repite uno.
        foreach ([
            "nit" => [$nit, "nit"],
            "documento" => [$documento, IdentidadCliente::SQL_DOCUMENTO],
            "telefono" => [$telefono, IdentidadCliente::SQL_TELEFONO],
        ] as $campo => [$valor, $expresion]) {
            $columnas[] = ($valor === null ? "FALSE" : "{$expresion} = :{$campo}_c") . " AS mismo_{$campo}";
            if ($valor !== null) {
                $condiciones[] = "{$expresion} = :{$campo}";
                $parametros[$campo] = $parametros["{$campo}_c"] = $valor;
            }
        }
        $columnas[] = "word_similarity(:nombre, " . IdentidadCliente::SQL_CLAVE . ") AS parecido";

        $filas = $condiciones === [] ? [] : $this->em->getConnection()->fetchAllAssociative(
            "SELECT " . implode(", ", $columnas) . " FROM cliente
              WHERE (" . implode(" OR ", $condiciones) . ") AND NOT comodin AND id <> :excepto
              ORDER BY id DESC LIMIT 20",
            $parametros,
        );
        $candidatos = [];
        foreach ($filas as $f) {
            $parecido = (float) $f["parecido"];
            [$motivo, $fuerte] = match (true) {
                (bool) $f["mismo_nit"] => ["Mismo NIT", true],
                (bool) $f["mismo_documento"] => ["Mismo documento", true],
                $parecido >= self::UMBRAL_MISMA_PERSONA => ["Mismo teléfono y nombre parecido", true],
                default => ["Mismo teléfono", false],
            };
            $candidatos[(int) $f["id"]] = ["motivo" => $motivo, "fuerte" => $fuerte, "parecido" => $parecido];
        }
        if ($nombre !== null) {
            $excluir = [...array_keys($candidatos), ...($excepto ? [$excepto] : [])];
            foreach ($this->nombresParecidos($nombre, $excluir, self::MAX_PARECIDOS, self::UMBRAL_NOMBRE) as $id => $parecido) {
                $candidatos[$id] = ["motivo" => $parecido >= 0.95 ? "Mismo nombre" : "Nombre parecido", "fuerte" => false, "parecido" => $parecido];
            }
        }
        uasort($candidatos, static fn(array $a, array $b) => [$b["fuerte"], $b["parecido"]] <=> [$a["fuerte"], $a["parecido"]]);
        $candidatos = array_slice($candidatos, 0, self::MAX_PARECIDOS, true);
        $clientes = $this->cargar(array_keys($candidatos));

        return array_values(array_map(
            static fn(Cliente $c) => ["cliente" => self::fila($c), "motivo" => $candidatos[$c->getId()]["motivo"], "fuerte" => $candidatos[$c->getId()]["fuerte"]],
            $clientes,
        ));
    }

    /**
     * El cliente comodín. Se crea la primera vez que se pide, como cualquier
     * cliente: así tiene el mismo id en el legado (ADR-042).
     */
    public function comodin(): Cliente
    {
        return $this->clienteComodin() ?? $this->em->wrapInTransaction(function (): Cliente {
            $this->em->getConnection()->fetchOne("SELECT pg_advisory_xact_lock(:k)", ["k" => self::BLOQUEO_COMODIN]);
            $cliente = $this->clienteComodin();
            if ($cliente === null) {
                $cliente = Cliente::comodin();
                $cliente->setCreatedAt(new \DateTime());
                $this->em->persist($cliente);
            }

            return $cliente;
        });
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): Cliente
    {
        $cliente = new Cliente();
        $cliente->setCreatedAt(new \DateTime());
        $this->aplicar($cliente, $datos);
        if (empty($datos["registrarAunqueExista"])) {
            $fuertes = array_values(array_filter($this->parecidos($datos), static fn(array $p) => $p["fuerte"]));
            if ($fuertes !== []) {
                throw new VentaRechazada(
                    "Ya hay un cliente registrado con esos datos: úselo, o confirme que es otra persona.",
                    "cliente_duplicado",
                    409,
                    ["parecidos" => $fuertes],
                );
            }
        }
        $this->em->persist($cliente);
        $this->em->flush();

        return $cliente;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        if ($cliente->isComodin()) {
            throw new VentaRechazada("El consumidor final no se edita: si el cliente da sus datos, regístrelo.", "cliente_comodin");
        }
        $this->aplicar($cliente, $datos);
        $this->em->flush();

        return $cliente;
    }

    /** @return array<string, mixed> */
    public static function fila(Cliente $c): array
    {
        return [
            "id" => $c->getId(),
            "nombre" => $c->getNombre(),
            "apellido" => $c->getApellido(),
            "nombreCompleto" => $c->getNombreCompleto(),
            "nit" => $c->getNit() ?: "CF",
            "email" => $c->getEmail(),
            "telefono" => $c->getTelefono(),
            "tipoDocumento" => $c->getTipoDocumento()?->getId(),
            "numeroDocumento" => $c->getNumeroDocumento(),
            "nacionalidad" => $c->getNacionalidad()?->getId(),
            "label" => sprintf("%s / %s", $c->getNit() ?: "CF", $c->getNombreCompleto()),
            "comodin" => $c->isComodin(),
        ];
    }

    /**
     * Ids de los clientes cuyo nombre se parece a `$clave` (ya normalizada), con
     * cuánto, de más a menos. El umbral va en la transacción porque el operador
     * `<%`, el que usa el índice `nat_cliente_busqueda`, no lo recibe.
     *
     * @param list<int> $excluir
     * @return array<int, float>
     */
    private function nombresParecidos(string $clave, array $excluir, int $limite, float $umbral): array
    {
        $conexion = $this->em->getConnection();

        return $conexion->transactional(static function () use ($conexion, $clave, $excluir, $limite, $umbral): array {
            $conexion->executeQuery("SELECT set_config('pg_trgm.word_similarity_threshold', :umbral, true)", ["umbral" => (string) $umbral]);
            $filas = $conexion->fetchAllKeyValue(
                "SELECT id, word_similarity(:clave, " . IdentidadCliente::SQL_CLAVE . ") AS s FROM cliente
                  WHERE :clave_indice <% " . IdentidadCliente::SQL_CLAVE . " AND NOT comodin AND id <> ALL(CAST(:excluir AS int[]))
                  ORDER BY s DESC, id DESC LIMIT " . max(0, $limite),
                ["clave" => $clave, "clave_indice" => $clave, "excluir" => "{" . implode(",", $excluir) . "}"],
            );

            return array_map(floatval(...), $filas);
        });
    }

    /**
     * Los clientes de esos ids, en ese orden.
     *
     * @param list<int> $ids
     * @return list<Cliente>
     */
    private function cargar(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $porId = [];
        foreach ($this->em->getRepository(Cliente::class)->createQueryBuilder("c")
            ->leftJoin("c.tipoDocumento", "td")->addSelect("td")
            ->leftJoin("c.nacionalidad", "n")->addSelect("n")
            ->where("c.id IN (:ids)")->setParameter("ids", $ids)
            ->getQuery()->getResult() as $c) {
            $porId[$c->getId()] = $c;
        }

        return array_values(array_filter(array_map(static fn(int $id) => $porId[$id] ?? null, $ids)));
    }

    private function clienteComodin(): ?Cliente
    {
        return $this->em->getRepository(Cliente::class)->findOneBy(["comodin" => true]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function aplicar(Cliente $cliente, array $datos): void
    {
        $texto = static fn(string $campo, int $max) => ($v = trim((string) ($datos[$campo] ?? ""))) !== "" ? mb_substr($v, 0, $max) : null;
        $id = static fn(string $campo) => is_numeric($datos[$campo] ?? null) && (int) $datos[$campo] > 0 ? (int) $datos[$campo] : null;

        $nombre = IdentidadCliente::nombre($texto("nombre", 255)) ?? throw new VentaRechazada("El nombre del cliente es obligatorio.", "cliente_nombre");
        $nit = Facturador::normalizarNit($texto("nit", 20));
        if ($nit !== "CF" && !Comprador::nitValido($nit)) {
            throw new VentaRechazada("El NIT no es válido (dígito verificador). Si no tiene, use CF.", "cliente_nit");
        }
        $email = ($e = $texto("email", 50)) !== null ? mb_strtolower($e) : null;
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new VentaRechazada("El correo electrónico no es válido.", "cliente_email");
        }
        $tipoDocumento = ($t = $id("tipoDocumento")) ? $this->em->find(TipoDocumento::class, $t) : null;
        $nacionalidad = $id("nacionalidad");
        $documento = IdentidadCliente::documento($texto("numeroDocumento", 40));
        // El DPI se revisa al escribirlo: los del legado que ya estaban mal no impiden editar lo demás.
        $cambiaDocumento = $documento !== IdentidadCliente::documento($cliente->getNumeroDocumento()) || $tipoDocumento !== $cliente->getTipoDocumento();
        if ($documento !== null && $cambiaDocumento && $tipoDocumento?->getSigla() === IdentidadCliente::SIGLA_DPI && !IdentidadCliente::cuiValido($documento)) {
            throw new VentaRechazada("El DPI no es válido: revise los 13 dígitos del CUI.", "cliente_documento");
        }

        $cliente
            ->setNombre($nombre)
            ->setApellido(IdentidadCliente::nombre($texto("apellido", 50)))
            ->setNit($nit)
            ->setEmail($email)
            ->setTelefono(IdentidadCliente::telefono($texto("telefono", 30)))
            ->setTipoDocumento($tipoDocumento)
            ->setNumeroDocumento($documento)
            ->setNacionalidad($nacionalidad ? $this->em->find(Nacion::class, $nacionalidad) : null);
    }
}
