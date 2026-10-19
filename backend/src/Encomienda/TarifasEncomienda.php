<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Enclave;
use App\Entity\EncomiendaArticulo;
use App\Entity\EncomiendaEspecial;
use App\Entity\Enum\TipoArticuloEncomienda;
use App\Entity\TarifaEncomiendaDistancia;
use App\Entity\TarifaEncomiendaPeso;
use Doctrine\ORM\EntityManagerInterface;
use Money\Money;
use Psr\Clock\ClockInterface;

/**
 * Busca las tarifas vigentes de cada artículo y cotiza la encomienda
 * (`Cotizador`, ADR-035): paquete = tarifa por peso + recargo por distancia
 * del par de enclaves; especial = su precio.
 */
final class TarifasEncomienda
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * Cotiza y arma los artículos (sin persistir).
     *
     * @param list<ArticuloSolicitado> $pedidos
     *
     * @return array{cotizacion: CotizacionEncomienda, articulos: list<EncomiendaArticulo>}
     *
     * @throws EncomiendaRechazada si falta una tarifa o un especial no existe
     */
    public function cotizar(Enclave $origen, Enclave $destino, array $pedidos, bool $porCobrar = false): array
    {
        if ($origen->getId() === $destino->getId()) {
            throw new EncomiendaRechazada("El origen y el destino de la encomienda no pueden ser el mismo.");
        }
        $recargo = null;
        $escala = null;
        $entradas = [];
        $articulos = [];
        foreach ($pedidos as $i => $p) {
            $articulo = (new EncomiendaArticulo())
                ->setTipo($p->tipo)
                ->setCantidad($p->cantidad)
                ->setDescripcion($p->descripcion);
            if ($p->tipo === TipoArticuloEncomienda::ESPECIAL) {
                $especial = $this->em->find(EncomiendaEspecial::class, (int) $p->especialId);
                if ($especial === null || !$especial->isActivo()) {
                    throw new EncomiendaRechazada(sprintf("Artículo %d: ese tipo de encomienda especial no existe o está inactivo.", $i + 1));
                }
                if ($porCobrar && !$especial->isPermitePorCobrar()) {
                    throw new EncomiendaRechazada(sprintf("«%s» no se envía por cobrar.", $especial->getNombre()), "especial_sin_por_cobrar");
                }
                $articulo->setEspecial($especial);
                $tarifa = $especial->getPrecio();
                $extra = Money::GTQ(0);
            } else {
                $escala ??= $this->escalaPeso();
                $tarifa = self::tarifaDePeso($escala, (float) $p->peso)
                    ?? throw new EncomiendaRechazada(sprintf("No hay tarifa para un paquete de %s lb.", rtrim(rtrim(number_format((float) $p->peso, 2, ".", ""), "0"), ".")), "sin_tarifa");
                $recargo ??= $this->recargo($origen, $destino);
                $extra = $recargo;
                $articulo->setPeso($p->peso)->setAlto($p->alto)->setAncho($p->ancho)->setLargo($p->largo);
            }
            $entradas[] = ["tarifa" => $tarifa, "recargo" => $extra, "cantidad" => $p->cantidad];
            $articulos[] = $articulo;
        }

        $cotizacion = Cotizador::cotizar($entradas);
        foreach ($cotizacion->lineas as $i => $l) {
            $articulos[$i]->setTarifa($l["tarifa"])->setRecargo($l["recargo"])->setImporte($l["importe"]);
        }

        return ["cotizacion" => $cotizacion, "articulos" => $articulos];
    }

    /** Recargo vigente entre dos enclaves (en cualquier sentido); 0 si no hay. */
    public function recargo(Enclave $a, Enclave $b): Money
    {
        /** @var TarifaEncomiendaDistancia|null $t */
        $t = $this->em->createQueryBuilder()
            ->select("t")
            ->from(TarifaEncomiendaDistancia::class, "t")
            ->where("((t.origen = :a AND t.destino = :b) OR (t.origen = :b AND t.destino = :a)) AND t.vigenteDesde <= :ahora")
            ->setParameter("a", $a)
            ->setParameter("b", $b)
            ->setParameter("ahora", \DateTime::createFromImmutable($this->reloj->now()))
            ->orderBy("t.vigenteDesde", "DESC")
            ->addOrderBy("t.id", "DESC")
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $t?->getPrecio() ?? Money::GTQ(0);
    }

    /**
     * Las tarifas por peso ya vigentes, de la más reciente a la más vieja.
     *
     * @return list<TarifaEncomiendaPeso>
     */
    public function escalaPeso(): array
    {
        return $this->em->createQueryBuilder()
            ->select("t")
            ->from(TarifaEncomiendaPeso::class, "t")
            ->where("t.vigenteDesde <= :ahora")
            ->setParameter("ahora", \DateTime::createFromImmutable($this->reloj->now()))
            ->orderBy("t.vigenteDesde", "DESC")
            ->addOrderBy("t.id", "DESC")
            ->getQuery()
            ->getResult();
    }

    /**
     * La más reciente que cubre ese peso.
     *
     * @param list<TarifaEncomiendaPeso> $escala de la más reciente a la más vieja
     */
    public static function tarifaDePeso(array $escala, float $peso): ?Money
    {
        foreach ($escala as $t) {
            if ($t->cubre($peso)) {
                return $t->getPrecio();
            }
        }

        return null;
    }
}
