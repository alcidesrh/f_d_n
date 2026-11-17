<?php

declare(strict_types=1);

namespace App\Venta\Descuento;

use App\Entity\Promocion;
use App\Entity\Salida;
use App\Entity\TipoPasajero;
use App\Venta\Cotizacion;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Aplica a una cotización de taquilla o de agencia los tipos de pasajero
 * elegidos y las promociones que valen para la salida (ADR-066). Las reglas
 * están en `ReglasDescuento`.
 */
class Descuentos
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Los tipos que se ofrecen al vender, por nombre.
     *
     * @return list<array{id: int, nombre: string, porciento: int, requisito: ?string}>
     */
    public function tipos(): array
    {
        return array_map(
            static fn(TipoPasajero $t) => ["id" => (int) $t->getId(), "nombre" => $t->getNombre(), "porciento" => $t->getPorciento(), "requisito" => $t->getRequisito()],
            $this->em->getRepository(TipoPasajero::class)->findBy(["activo" => true], ["nombre" => "ASC"]),
        );
    }

    /**
     * @param array<int, ?int> $tipos tipo de pasajero por id de asiento (sin tipo: null o ausente)
     *
     * @throws VentaRechazada si un tipo no existe o el código no vale para esta salida
     */
    public function aplicar(Cotizacion $cotizacion, Salida $salida, array $tipos, ?string $codigo): Cotizacion
    {
        $codigo = ReglasDescuento::codigo($codigo);
        $porTipo = $this->tiposPorId(array_values(array_unique(array_filter($tipos))));
        $promociones = $this->promociones($salida, $codigo);

        $descuentos = [];
        foreach ($cotizacion->lineas as $l) {
            $asientoId = (int) $l["asiento"]->getId();
            $tipoId = $tipos[$asientoId] ?? null;
            $descuentos[$asientoId] = ReglasDescuento::elegir($tipoId === null ? null : $porTipo[$tipoId], $promociones);
        }

        return $cotizacion->conDescuentos($descuentos);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array{id: int, nombre: string, porciento: int}>
     */
    private function tiposPorId(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $porId = [];
        foreach ($this->em->getRepository(TipoPasajero::class)->findBy(["id" => $ids, "activo" => true]) as $t) {
            $porId[(int) $t->getId()] = ["id" => (int) $t->getId(), "nombre" => $t->getNombre(), "porciento" => $t->getPorciento()];
        }
        if (count($porId) !== count($ids)) {
            throw new VentaRechazada("Algún tipo de pasajero no existe o ya no está activo.", "tipo_pasajero");
        }

        return $porId;
    }

    /**
     * Las promociones que valen para la salida: las que se aplican solas y, si
     * se escribió, la de ese código (que debe valer).
     *
     * @return list<array{id: int, nombre: string, porciento: int}>
     */
    private function promociones(Salida $salida, ?string $codigo): array
    {
        $fecha = $salida->getFecha()->format("Y-m-d");
        $filas = $this->em->createQueryBuilder()
            ->select("p.id", "p.nombre", "p.porciento", "p.codigo", "p.activa", "p.desde", "p.hasta", "IDENTITY(p.empresa) AS empresa", "IDENTITY(p.ruta) AS ruta", "IDENTITY(p.busClase) AS busClase")
            ->from(Promocion::class, "p")
            ->where("p.activa = true")
            ->andWhere("p.desde <= :fecha AND p.hasta >= :fecha")
            ->andWhere($codigo === null ? "p.codigo IS NULL" : "(p.codigo IS NULL OR p.codigo = :codigo)")
            ->setParameter("fecha", $fecha)
            ->getQuery();
        if ($codigo !== null) {
            $filas->setParameter("codigo", $codigo);
        }
        $deSalida = [
            "fecha" => $fecha,
            "empresa" => $salida->getEmpresa()?->getId(),
            "ruta" => $salida->getRuta()?->getId(),
            "busClase" => $salida->getBus()?->getClase()?->getId(),
        ];

        $valen = [];
        $conCodigo = false;
        foreach ($filas->getArrayResult() as $f) {
            $promo = [
                "activa" => (bool) $f["activa"],
                "codigo" => $f["codigo"],
                "desde" => $f["desde"]->format("Y-m-d"),
                "hasta" => $f["hasta"]->format("Y-m-d"),
                "empresa" => $f["empresa"] === null ? null : (int) $f["empresa"],
                "ruta" => $f["ruta"] === null ? null : (int) $f["ruta"],
                "busClase" => $f["busClase"] === null ? null : (int) $f["busClase"],
            ];
            if (ReglasDescuento::vale($promo, $deSalida, $codigo)) {
                $valen[] = ["id" => (int) $f["id"], "nombre" => (string) $f["nombre"], "porciento" => (int) $f["porciento"]];
                $conCodigo = $conCodigo || $promo["codigo"] !== null;
            }
        }
        if ($codigo !== null && !$conCodigo) {
            throw new VentaRechazada("El código promocional «{$codigo}» no existe o no vale para esta salida.", "codigo_promocion");
        }

        return $valen;
    }
}
