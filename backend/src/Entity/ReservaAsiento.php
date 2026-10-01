<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Asiento apartado en la página web mientras el cliente completa la compra
 * (la "precompra" del legado). Aparta el asiento para su tramo igual que un
 * `BoletoAsiento`: en los croquis de venta sale como `reservado`.
 *
 * Las reservas de un mismo carrito comparten `token`. Vencen solas
 * (`expiraEn`): la disponibilidad ignora las vencidas, así que no dependen
 * de ningún proceso de limpieza para liberar el asiento; `app:venta:purgar`
 * solo borra las filas. `expiraEn` nunca pasa de
 * `ReglasVenta::LIBERACION_MINUTOS` (30) antes de la primera salida del
 * carrito, y todas las del carrito vencen juntas (ADR-023).
 */
#[ORM\Entity]
#[ORM\Index(columns: ["salida_id", "expira_en"], name: "idx_reserva_asiento_salida_expira")]
#[ORM\Index(columns: ["token"], name: "idx_reserva_asiento_token")]
#[ORM\UniqueConstraint(name: "uq_reserva_asiento_token_asiento", columns: ["token", "asiento_id"])]
class ReservaAsiento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "uuid")]
    private Uuid $token;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Salida $salida;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Asiento $asiento;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Trayecto $trayecto;

    #[ORM\Column]
    private \DateTimeImmutable $expiraEn;

    #[ORM\Column]
    private \DateTimeImmutable $creadaEn;

    public function __construct(
        Uuid $token,
        Salida $salida,
        Asiento $asiento,
        Trayecto $trayecto,
        \DateTimeImmutable $expiraEn,
    ) {
        $this->token = $token;
        $this->salida = $salida;
        $this->asiento = $asiento;
        $this->trayecto = $trayecto;
        $this->expiraEn = $expiraEn;
        $this->creadaEn = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): Uuid
    {
        return $this->token;
    }

    public function getSalida(): Salida
    {
        return $this->salida;
    }

    public function getAsiento(): Asiento
    {
        return $this->asiento;
    }

    public function getTrayecto(): Trayecto
    {
        return $this->trayecto;
    }

    public function getExpiraEn(): \DateTimeImmutable
    {
        return $this->expiraEn;
    }

    public function extenderHasta(\DateTimeImmutable $expiraEn): void
    {
        $this->expiraEn = $expiraEn;
    }

    public function getCreadaEn(): \DateTimeImmutable
    {
        return $this->creadaEn;
    }

    public function vigente(\DateTimeImmutable $ahora): bool
    {
        return $this->expiraEn > $ahora;
    }
}
