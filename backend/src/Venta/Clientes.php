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

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Clientes cuyo NIT, documento, nombre o apellido contiene el término.
     * Con "CF" devuelve los consumidores finales más recientes.
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(string $termino): array
    {
        $termino = trim($termino);
        if (mb_strlen($termino) < self::MIN_BUSQUEDA && strtoupper($termino) !== "CF") {
            return [];
        }
        $qb = $this->em->getRepository(Cliente::class)->createQueryBuilder("c")
            ->leftJoin("c.tipoDocumento", "td")->addSelect("td")
            ->leftJoin("c.nacionalidad", "n")->addSelect("n")
            ->orderBy("c.id", "DESC")
            ->setMaxResults(20);

        $nit = Facturador::normalizarNit($termino);
        if (ctype_digit(str_replace("K", "", $nit)) && strlen($nit) >= self::MIN_BUSQUEDA) {
            // NIT o documento: prefijo (usa el índice) o igualdad del documento.
            $qb->where("c.nit LIKE :prefijo OR c.numeroDocumento = :exacto")
                ->setParameter("prefijo", $nit . "%")
                ->setParameter("exacto", $termino);
        } else {
            $palabras = array_slice(preg_split('/\s+/', mb_strtolower($termino)) ?: [], 0, 4);
            foreach ($palabras as $i => $p) {
                $qb->andWhere("LOWER(CONCAT(c.nombre, ' ', COALESCE(c.apellido, ''), ' ', COALESCE(c.nit, ''))) LIKE :p{$i}")
                    ->setParameter("p{$i}", "%" . addcslashes($p, "%_") . "%");
            }
        }

        return array_map(self::fila(...), $qb->getQuery()->getResult());
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): Cliente
    {
        $cliente = new Cliente();
        $cliente->setCreatedAt(new \DateTime());
        $this->aplicar($cliente, $datos);
        $this->em->persist($cliente);
        $this->em->flush();

        return $cliente;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
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
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function aplicar(Cliente $cliente, array $datos): void
    {
        $texto = static fn(string $campo, int $max) => ($v = trim((string) ($datos[$campo] ?? ""))) !== "" ? mb_substr($v, 0, $max) : null;
        $id = static fn(string $campo) => is_numeric($datos[$campo] ?? null) && (int) $datos[$campo] > 0 ? (int) $datos[$campo] : null;

        $nombre = $texto("nombre", 255) ?? throw new VentaRechazada("El nombre del cliente es obligatorio.", "cliente_nombre");
        $nit = Facturador::normalizarNit($texto("nit", 20));
        if ($nit !== "CF" && !Comprador::nitValido($nit)) {
            throw new VentaRechazada("El NIT no es válido (dígito verificador). Si no tiene, use CF.", "cliente_nit");
        }
        $email = $texto("email", 50);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new VentaRechazada("El correo electrónico no es válido.", "cliente_email");
        }
        $tipoDocumento = $id("tipoDocumento");
        $nacionalidad = $id("nacionalidad");

        $cliente
            ->setNombre($nombre)
            ->setApellido($texto("apellido", 50))
            ->setNit($nit)
            ->setEmail($email)
            ->setTelefono($texto("telefono", 15))
            ->setTipoDocumento($tipoDocumento ? $this->em->find(TipoDocumento::class, $tipoDocumento) : null)
            ->setNumeroDocumento($texto("numeroDocumento", 40))
            ->setNacionalidad($nacionalidad ? $this->em->find(Nacion::class, $nacionalidad) : null);
    }
}
