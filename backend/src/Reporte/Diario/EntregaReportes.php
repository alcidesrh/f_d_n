<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Chat\Archivos;
use App\Chat\Conversaciones;
use App\Entity\SuscripcionReporte;
use App\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Hace llegar un reporte programado a un usuario por donde lo pidió
 * (ADR-044): por el chat, como aviso del sistema con los PDF adjuntos; por
 * correo, con el HTML y los mismos PDF. Un reporte diario sale una sola vez
 * por usuario y día (`envio_reporte`): se aparta antes de enviar y, si el
 * envío falla, se suelta para que el reintento lo haga.
 */
final class EntregaReportes
{
    public function __construct(
        private readonly Connection $db,
        private readonly Conversaciones $chat,
        private readonly Archivos $archivos,
        private readonly MailerInterface $mailer,
        private readonly ClockInterface $reloj,
        #[Autowire(env: "default:programador_correo_remitente_defecto:PROGRAMADOR_CORREO_REMITENTE")]
        private readonly string $remitente,
    ) {}

    /**
     * @param ?\DateTimeImmutable $dia el día del reporte (los diarios); null para los avisos, que no se apartan
     *
     * @return array{chat: bool, correo: bool, repetido: bool}
     */
    public function entregar(Usuario $usuario, SuscripcionReporte $s, ReporteProgramado $reporte, ?\DateTimeImmutable $dia, Entrega $e): array
    {
        if ($dia !== null && !$this->apartar($usuario, $reporte, $dia)) {
            return ["chat" => false, "correo" => false, "repetido" => true];
        }
        try {
            $chat = false;
            if ($s->isPorChat()) {
                $archivos = array_map(fn(array $a) => $this->archivos->guardarGenerado($a["contenido"], $a["nombre"]), $e->archivos);
                $this->chat->avisarSistema([$usuario], $e->texto, [], $archivos);
                $chat = true;
            }
            $correo = false;
            if ($s->isPorCorreo() && $usuario->getEmail()) {
                $email = (new Email())
                    ->from($this->remitente)
                    ->to((string) $usuario->getEmail())
                    ->subject($e->asunto)
                    ->html($e->html)
                    ->text($e->texto);
                foreach ($e->archivos as $a) {
                    $email->attach($a["contenido"], $a["nombre"], "application/pdf");
                }
                $this->mailer->send($email);
                $correo = true;
            }
        } catch (\Throwable $ex) {
            if ($dia !== null) {
                $this->soltar($usuario, $reporte, $dia);
            }

            throw $ex;
        }
        $resultado = ["chat" => $chat, "correo" => $correo, "repetido" => false];
        if ($dia !== null) {
            $this->db->executeStatement(
                "UPDATE envio_reporte SET detalle = :detalle WHERE usuario_id = :usuario AND reporte = :reporte AND dia = :dia",
                ["detalle" => json_encode($resultado + ["archivos" => count($e->archivos)]), ...self::clave($usuario, $reporte, $dia)],
            );
        }

        return $resultado;
    }

    private function apartar(Usuario $usuario, ReporteProgramado $reporte, \DateTimeImmutable $dia): bool
    {
        return $this->db->executeStatement(
            "INSERT INTO envio_reporte (usuario_id, reporte, dia, enviado_en) VALUES (:usuario, :reporte, :dia, :ahora) ON CONFLICT DO NOTHING",
            self::clave($usuario, $reporte, $dia) + ["ahora" => $this->reloj->now()->format("Y-m-d H:i:s")],
        ) === 1;
    }

    private function soltar(Usuario $usuario, ReporteProgramado $reporte, \DateTimeImmutable $dia): void
    {
        $this->db->executeStatement("DELETE FROM envio_reporte WHERE usuario_id = :usuario AND reporte = :reporte AND dia = :dia", self::clave($usuario, $reporte, $dia));
    }

    /** @return array{usuario: int, reporte: string, dia: string} */
    private static function clave(Usuario $usuario, ReporteProgramado $reporte, \DateTimeImmutable $dia): array
    {
        return ["usuario" => (int) $usuario->getId(), "reporte" => $reporte->value, "dia" => $dia->format("Y-m-d")];
    }
}
