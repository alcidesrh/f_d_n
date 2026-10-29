<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado;

use App\Chat\Archivos;
use App\Chat\Conversaciones;
use App\Entity\ChatArchivo;
use App\Entity\ChatMiembro;
use App\Entity\MensajePredeterminado;
use App\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Entrega un mensaje predeterminado (ADR-045) en los avisos del sistema de
 * su audiencia: los usuarios elegidos y los miembros de los grupos elegidos,
 * sin repetir y solo los activos. El texto es el contenido propio de la
 * instancia y, debajo, el del predeterminado; los archivos, copias de los de
 * la instancia más los generados (PDF).
 *
 * Una vez por instancia y clave (`EnvioMensajePredeterminado`): la
 * constancia se escribe antes de enviar y se borra si el envío falla.
 */
final class Difusion
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly Conversaciones $chat,
        private readonly Archivos $archivos,
    ) {}

    /** @return list<Usuario> */
    public function audiencia(MensajePredeterminado $m): array
    {
        $usuarios = [];
        foreach ($m->getUsuarios() as $u) {
            $usuarios[(int) $u->getId()] = $u;
        }
        $grupos = $m->getGrupos()->toArray();
        if ($grupos !== []) {
            /** @var list<ChatMiembro> $miembros */
            $miembros = $this->em->createQuery("SELECT mi, u FROM App\Entity\ChatMiembro mi JOIN mi.usuario u WHERE mi.canal IN (:grupos)")
                ->setParameter("grupos", $grupos)->getResult();
            foreach ($miembros as $mi) {
                $usuarios[(int) $mi->getUsuario()->getId()] ??= $mi->getUsuario();
            }
        }

        return array_values(array_filter($usuarios, static fn(Usuario $u) => $u->isActivo()));
    }

    /**
     * Envía la pieza a la audiencia, una sola vez por su clave; devuelve a
     * cuántos les llegó (0 si ya había salido).
     */
    public function enviar(MensajePredeterminado $m, Pieza $pieza): int
    {
        $registrado = $this->db->executeStatement(
            "INSERT INTO envio_mensaje_predeterminado (mensaje_id, clave, enviado_en, destinatarios)
             VALUES (:mensaje, :clave, :ahora, 0) ON CONFLICT (mensaje_id, clave) DO NOTHING",
            ["mensaje" => $m->getId(), "clave" => $pieza->clave, "ahora" => new \DateTimeImmutable()],
            ["ahora" => Types::DATETIME_IMMUTABLE],
        );
        if ($registrado === 0) {
            return 0;
        }
        try {
            $destinatarios = $this->audiencia($m);
            $this->entregar($m, $pieza, $destinatarios);
            $this->db->executeStatement(
                "UPDATE envio_mensaje_predeterminado SET destinatarios = :n WHERE mensaje_id = :mensaje AND clave = :clave",
                ["n" => count($destinatarios), "mensaje" => $m->getId(), "clave" => $pieza->clave],
            );

            return count($destinatarios);
        } catch (\Throwable $e) {
            $this->db->executeStatement("DELETE FROM envio_mensaje_predeterminado WHERE mensaje_id = :mensaje AND clave = :clave", ["mensaje" => $m->getId(), "clave" => $pieza->clave]);

            throw $e;
        }
    }

    public function yaSalio(MensajePredeterminado $m, string $clave): bool
    {
        return $this->db->fetchOne("SELECT 1 FROM envio_mensaje_predeterminado WHERE mensaje_id = :mensaje AND clave = :clave", ["mensaje" => $m->getId(), "clave" => $clave]) !== false;
    }

    /** Una prueba: solo a `$usuario` y sin constancia (no cuenta como envío). */
    public function probar(MensajePredeterminado $m, Pieza $pieza, Usuario $usuario): void
    {
        $this->entregar($m, $pieza, [$usuario]);
    }

    public static function texto(?string $propio, string $predeterminado): string
    {
        $texto = trim(implode("\n\n", array_filter([trim((string) $propio), trim($predeterminado)], static fn(string $t) => $t !== "")));

        return mb_strlen($texto) > Conversaciones::MAX_TEXTO ? mb_substr($texto, 0, Conversaciones::MAX_TEXTO - 1) . "…" : $texto;
    }

    /** @param list<Usuario> $destinatarios */
    private function entregar(MensajePredeterminado $m, Pieza $pieza, array $destinatarios): void
    {
        if ($destinatarios === []) {
            return;
        }
        $archivos = [
            ...array_map(function (ChatArchivo $a): ChatArchivo {
                $copia = $a->copiar(null);
                $this->em->persist($copia);

                return $copia;
            }, $m->getArchivos()->toArray()),
            ...array_map(fn(array $g) => $this->archivos->guardarGenerado($g["contenido"], $g["nombre"]), $pieza->generados),
        ];
        $this->chat->avisarSistema($destinatarios, self::texto($m->getTexto(), $pieza->texto), $pieza->tarjetas, $archivos);
    }
}
