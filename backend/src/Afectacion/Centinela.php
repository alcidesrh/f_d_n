<?php

declare(strict_types=1);

namespace App\Afectacion;

use App\Chat\Conversaciones;
use App\Chat\Tarjeta\TarjetaAfectacion;
use App\Entity\Afectacion;
use App\Entity\Enum\EstadoAfectacion;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * El centinela de las afectaciones (ADR-070): la tarea
 * `vigilar_afectaciones` lo corre cada minuto (y al reportarse una, para
 * que el primer aviso salga en segundos). Mira cada afectación abierta,
 * decide con `ReglasCentinela` y avisa por el canal del sistema del chat
 * (con push al teléfono, ADR-061), con la afectación como tarjeta.
 *
 * Es el único que avisa: corre bajo el cerrojo de su tarea (ADR-044), así
 * que dos corridas no repiten un aviso. Un aviso que falla se reintenta en
 * la corrida siguiente; uno que no encuentra a nadie no la pasa a
 * pendiente, pero el escalamiento sigue su ritmo.
 */
final class Centinela
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Destinatarios $destinatarios,
        private readonly Conversaciones $chat,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return array{abiertas: int, iniciales: int, escalamientos: int, sinDestinatarios: int, fallidos: int} */
    public function revisar(\DateTimeImmutable $ahora): array
    {
        /** @var list<Afectacion> $abiertas */
        $abiertas = $this->em->createQueryBuilder()
            ->select("a", "t", "e", "s", "b", "r")
            ->from(Afectacion::class, "a")
            ->join("a.tipo", "t")
            ->leftJoin("a.empresa", "e")
            ->leftJoin("a.estacion", "s")
            ->leftJoin("a.bus", "b")
            ->leftJoin("a.reportadaPor", "r")
            ->where("a.estado <> :resuelta")
            ->setParameter("resuelta", EstadoAfectacion::RESUELTA)
            ->orderBy("a.creadaEn")
            ->getQuery()
            ->getResult();

        $resumen = ["abiertas" => count($abiertas), "iniciales" => 0, "escalamientos" => 0, "sinDestinatarios" => 0, "fallidos" => 0];
        foreach ($abiertas as $a) {
            $decision = ReglasCentinela::para($a, $ahora);
            if ($decision === ReglasCentinela::NADA) {
                continue;
            }
            $escalada = $decision === ReglasCentinela::ESCALAMIENTO;
            $tipo = $a->getTipo();
            $usuarios = $this->destinatarios->de($a, $escalada ? $tipo->capacidadesEscaladas() : [$tipo->getTarget()]);
            if ($usuarios === []) {
                $resumen["sinDestinatarios"]++;
                if ($escalada) {
                    // Sin nadie a quien avisar: cuenta para el ritmo (si no, se intentaría cada minuto).
                    $a->avisada($ahora, true, false);
                    $this->em->flush();
                }
                continue;
            }
            try {
                $this->chat->avisarSistema($usuarios, ReglasAfectacion::aviso($a, $escalada, $ahora), [["tipo" => TarjetaAfectacion::TIPO, "id" => (int) $a->getId()]]);
            } catch (\Throwable $e) {
                // Una que falla no deja sin aviso a las demás; la próxima corrida la reintenta.
                $resumen["fallidos"]++;
                $this->logger->error("No se pudo avisar la afectación #{id}: {error}", ["id" => $a->getId(), "error" => $e->getMessage(), "exception" => $e]);
                continue;
            }
            if ($a->getEstado() === EstadoAfectacion::CREADA) {
                $a->enviada($ahora, $escalada);
            } else {
                $a->avisada($ahora, $escalada);
            }
            $this->em->flush();
            $resumen[$escalada ? "escalamientos" : "iniciales"]++;
        }

        return $resumen;
    }
}
