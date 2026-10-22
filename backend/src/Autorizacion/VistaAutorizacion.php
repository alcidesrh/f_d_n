<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;

/** Cómo se ve una autorización en `/api/autorizaciones` y en el chat (ADR-040). */
final class VistaAutorizacion
{
    /**
     * @param array{aprobar: bool, retirar: bool} $acciones lo que puede hacer con ella quien mira
     *
     * @return array<string, mixed>
     */
    public static function fila(SolicitudAutorizacion $s, \DateTimeImmutable $ahora, array $acciones): array
    {
        $estado = $s->estadoEn($ahora);

        return [
            "id" => $s->getId(),
            "capacidad" => $s->getCapacidad(),
            "operacion" => ReglasAutorizacion::operacion($s->getCapacidad()),
            "dispensa" => $s->getDispensa()->value,
            "dispensaNombre" => $s->getDispensa()->etiqueta(),
            "sujeto" => $s->getSujeto(),
            "ids" => $s->getSujetoIds(),
            "cantidad" => $s->getCantidad(),
            "detalle" => $s->getDetalle(),
            "motivo" => $s->getMotivo(),
            "estado" => $estado->value,
            "estadoNombre" => $estado->etiqueta(),
            "solicitante" => self::usuario($s->getSolicitante()),
            "empresa" => $s->getEmpresa() === null ? null : ["id" => $s->getEmpresa()->getId(), "nombre" => $s->getEmpresa()->getNombreCorto()],
            "estacion" => $s->getEstacion() === null ? null : ["id" => $s->getEstacion()->getId(), "nombre" => $s->getEstacion()->getNombre()],
            "creadaEn" => $s->getCreadaEn()->format(DATE_ATOM),
            "venceEn" => $s->getVenceEn()->format(DATE_ATOM),
            "resueltaPor" => $s->getResueltaPor() === null ? null : self::usuario($s->getResueltaPor()),
            "resueltaEn" => $s->getResueltaEn()?->format(DATE_ATOM),
            "respuesta" => $s->getRespuesta(),
            "usadaEn" => $s->getUsadaEn()?->format(DATE_ATOM),
            "acciones" => $acciones,
        ];
    }

    /** «Anular boletos (fuera de hora): 2 boletos de la venta 123». */
    public static function titulo(SolicitudAutorizacion $s): string
    {
        $que = $s->getSujeto() === SolicitudAutorizacion::SUJETO_SALIDA
            ? sprintf("%d asiento(s) de la salida %d", $s->getCantidad(), $s->getSujetoIds()[0] ?? 0)
            : sprintf("%d boleto(s)%s", $s->getCantidad(), isset($s->getDetalle()["venta"]) ? sprintf(" de la venta %d", $s->getDetalle()["venta"]) : "");

        return sprintf("%s (%s): %s", ucfirst(ReglasAutorizacion::operacion($s->getCapacidad())), mb_strtolower($s->getDispensa()->etiqueta()), $que);
    }

    /** @return array{id: ?int, nombre: string} */
    private static function usuario(Usuario $u): array
    {
        return ["id" => $u->getId(), "nombre" => (string) ($u->getFullName() ?: $u->getUsername())];
    }
}
