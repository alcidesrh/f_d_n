<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Acceso\Autorizacion;
use App\Entity\SuscripcionReporte;
use App\Entity\Usuario;
use App\Reporte\ReporteRechazado;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Las suscripciones a los reportes programados (ADR-044): qué puede recibir
 * un usuario (los reportes cuya capacidad tiene con un alcance que sirve a
 * su nivel), cómo los quiere (chat, correo) y, al enviar, quiénes los
 * reciben ahora. Suscribirse no da nada: un permiso que se pierde corta el
 * envío desde el siguiente.
 */
final class Suscripciones
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Autorizacion $autorizacion,
    ) {}

    /** @return list<array<string, mixed>> cada reporte con lo que tiene elegido y si puede recibirlo */
    public function de(Usuario $usuario): array
    {
        $propias = [];
        foreach ($this->em->getRepository(SuscripcionReporte::class)->findBy(["usuario" => $usuario]) as $s) {
            $propias[$s->getReporte()] = $s;
        }
        $permisos = $this->autorizacion->de($usuario);

        return array_map(static function (ReporteProgramado $r) use ($propias, $permisos): array {
            $s = $propias[$r->value] ?? null;
            $celdas = Celdas::de($permisos->alcance($r->capacidad()->value), $r->nivel());

            return [
                "codigo" => $r->value,
                "nombre" => $r->nombre(),
                "descripcion" => $r->descripcion(),
                "diario" => $r->diario(),
                "puede" => $celdas !== [],
                "motivo" => $celdas !== [] ? null : self::motivo($r),
                "suscrito" => $s !== null && ($s->isPorChat() || $s->isPorCorreo()),
                "porChat" => $s?->isPorChat() ?? false,
                "porCorreo" => $s?->isPorCorreo() ?? false,
            ];
        }, ReporteProgramado::cases());
    }

    /**
     * Cambia cómo recibe un reporte; sin chat ni correo, deja de recibirlo.
     *
     * @throws ReporteRechazado
     */
    public function cambiar(Usuario $usuario, string $codigo, bool $porChat, bool $porCorreo): void
    {
        $reporte = ReporteProgramado::tryFrom($codigo)
            ?? throw new ReporteRechazado("Ese reporte no existe.", "reporte_inexistente", 404);
        $repo = $this->em->getRepository(SuscripcionReporte::class);
        $s = $repo->findOneBy(["usuario" => $usuario, "reporte" => $reporte->value]);
        if (!$porChat && !$porCorreo) {
            if ($s !== null) {
                $this->em->remove($s);
                $this->em->flush();
            }

            return;
        }
        if (Celdas::de($this->autorizacion->de($usuario)->alcance($reporte->capacidad()->value), $reporte->nivel()) === []) {
            throw new ReporteRechazado(self::motivo($reporte), "alcance", 403);
        }
        if ($porCorreo && !$usuario->getEmail()) {
            throw new ReporteRechazado("Para recibirlo por correo, registre su correo en Mi cuenta.", "sin_correo");
        }
        if ($s === null) {
            $s = new SuscripcionReporte($usuario, $reporte->value);
            $this->em->persist($s);
        }
        $s->cambiar($porChat, $porCorreo);
        $this->em->flush();
    }

    /**
     * Quiénes lo reciben ahora (activos, con la capacidad) y en qué piezas.
     *
     * @return list<array{usuario: Usuario, suscripcion: SuscripcionReporte, celdas: list<array{empresa: ?int, estacion: ?int}>}>
     */
    public function destinatarios(ReporteProgramado $reporte): array
    {
        /** @var list<SuscripcionReporte> $suscripciones */
        $suscripciones = $this->em->createQueryBuilder()
            ->select("s", "u")
            ->from(SuscripcionReporte::class, "s")
            ->join("s.usuario", "u")
            ->where("s.reporte = :reporte AND u.activo = true AND (s.porChat = true OR s.porCorreo = true)")
            ->setParameter("reporte", $reporte->value)
            ->orderBy("s.id")
            ->getQuery()
            ->getResult();

        $resultado = [];
        foreach ($suscripciones as $s) {
            $celdas = Celdas::de($this->autorizacion->de($s->getUsuario())->alcance($reporte->capacidad()->value), $reporte->nivel());
            if ($celdas !== []) {
                $resultado[] = ["usuario" => $s->getUsuario(), "suscripcion" => $s, "celdas" => $celdas];
            }
        }

        return $resultado;
    }

    /**
     * La de un usuario, si sigue suscrito y con alcance para recibirlo.
     *
     * @return array{suscripcion: SuscripcionReporte, celdas: list<array{empresa: ?int, estacion: ?int}>}|null
     */
    public function vigente(Usuario $usuario, ReporteProgramado $reporte): ?array
    {
        $s = $this->em->getRepository(SuscripcionReporte::class)->findOneBy(["usuario" => $usuario, "reporte" => $reporte->value]);
        if ($s === null || !$usuario->isActivo() || (!$s->isPorChat() && !$s->isPorCorreo())) {
            return null;
        }
        $celdas = Celdas::de($this->autorizacion->de($usuario)->alcance($reporte->capacidad()->value), $reporte->nivel());

        return $celdas === [] ? null : ["suscripcion" => $s, "celdas" => $celdas];
    }

    private static function motivo(ReporteProgramado $r): string
    {
        return $r->nivel() === ReporteProgramado::EMPRESA
            ? sprintf("Necesita «%s» sobre una empresa entera.", $r->capacidad()->nombre())
            : sprintf("Necesita «%s».", $r->capacidad()->nombre());
    }
}
