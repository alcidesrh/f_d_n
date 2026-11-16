<?php

declare(strict_types=1);

namespace App\Mantenimiento;

use App\Acceso\Alcance;
use App\Acceso\FiltroAlcance;
use App\Entity\Bus;
use App\Entity\OrdenMantenimiento;
use App\Entity\PlanMantenimiento;
use Doctrine\ORM\EntityManagerInterface;

/**
 * En qué va cada bus con cada plan de mantenimiento activo (ADR-065): lee
 * buses, planes y la última orden de cada par, y aplica `ReglasMantenimiento`.
 */
class EstadoFlota
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @return list<array{id: int, nombre: string, cadaKm: ?int, cadaDias: ?int}> los activos, por nombre */
    public function planes(): array
    {
        return array_map(
            static fn(PlanMantenimiento $p) => ["id" => (int) $p->getId(), "nombre" => $p->getNombre(), "cadaKm" => $p->getCadaKm(), "cadaDias" => $p->getCadaDias()],
            $this->em->getRepository(PlanMantenimiento::class)->findBy(["activo" => true], ["nombre" => "ASC"]),
        );
    }

    /**
     * Los buses (los del alcance, o todos) con su estado por plan y el peor.
     *
     * @return list<array{id: int, codigo: string, matricula: ?string, empresaId: ?int, odometroKm: int, estado: string,
     *     planes: list<array{plan: int, estado: string, faltanKm: ?int, faltanDias: ?int, ultimaFecha: ?string, ultimoKm: ?int}>}>
     */
    public function buses(\DateTimeImmutable $hoy, ?Alcance $alcance = null): array
    {
        $planes = $this->planes();
        $qb = $this->em->createQueryBuilder()
            ->select("b.id", "b.codigo", "b.matricula", "b.odometroKm", "IDENTITY(b.empresa) AS empresa")
            ->from(Bus::class, "b")
            ->orderBy("b.codigo", "ASC");
        if ($alcance !== null) {
            FiltroAlcance::aplicar($qb, $alcance, "b.empresa", compartidos: true);
        }
        $ultimas = $this->ultimas();
        $dia = $hoy->format("Y-m-d");

        $buses = [];
        foreach ($qb->getQuery()->getArrayResult() as $b) {
            $porPlan = [];
            foreach ($planes as $p) {
                $u = $ultimas[(int) $b["id"]][$p["id"]] ?? null;
                $porPlan[] = ["plan" => $p["id"]]
                    + ReglasMantenimiento::estado($p["cadaKm"], $p["cadaDias"], $u["km"] ?? null, $u["fecha"] ?? null, (int) $b["odometroKm"], $dia)
                    + ["ultimaFecha" => $u["fecha"] ?? null, "ultimoKm" => $u["km"] ?? null];
            }
            $buses[] = [
                "id" => (int) $b["id"],
                "codigo" => (string) $b["codigo"],
                "matricula" => $b["matricula"],
                "empresaId" => $b["empresa"] === null ? null : (int) $b["empresa"],
                "odometroKm" => (int) $b["odometroKm"],
                "estado" => ReglasMantenimiento::peor(array_column($porPlan, "estado")),
                "planes" => $porPlan,
            ];
        }

        return $buses;
    }

    /**
     * Los buses con algún plan vencido, para advertir al programar una salida.
     *
     * @return array<int, true> por id de bus
     */
    public function vencidos(\DateTimeImmutable $hoy): array
    {
        $vencidos = [];
        // Sin planes no hay nada que vencer: no se lee la flota en cada carga del programador.
        if ($this->em->getRepository(PlanMantenimiento::class)->count(["activo" => true]) === 0) {
            return $vencidos;
        }
        foreach ($this->buses($hoy) as $b) {
            if ($b["estado"] === ReglasMantenimiento::VENCIDO) {
                $vencidos[$b["id"]] = true;
            }
        }

        return $vencidos;
    }

    /**
     * La última orden de cada bus con cada plan: su fecha y el odómetro más alto.
     *
     * @return array<int, array<int, array{fecha: string, km: ?int}>> bus → plan
     */
    private function ultimas(): array
    {
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(o.bus) AS bus", "IDENTITY(o.plan) AS plan", "MAX(o.fecha) AS fecha", "MAX(o.odometroKm) AS km")
            ->from(OrdenMantenimiento::class, "o")
            ->where("o.plan IS NOT NULL")
            ->groupBy("o.bus", "o.plan")
            ->getQuery()
            ->getArrayResult();
        $ultimas = [];
        foreach ($filas as $f) {
            $fecha = $f["fecha"] instanceof \DateTimeInterface ? $f["fecha"]->format("Y-m-d") : substr((string) $f["fecha"], 0, 10);
            $ultimas[(int) $f["bus"]][(int) $f["plan"]] = ["fecha" => $fecha, "km" => $f["km"] === null ? null : (int) $f["km"]];
        }

        return $ultimas;
    }
}
