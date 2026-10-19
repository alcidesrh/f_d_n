<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Cliente;
use App\Entity\Encomienda;
use App\Entity\EncomiendaArticulo;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Factura;
use App\Venta\Boleto\DatosBoleto;

/**
 * Datos de una encomienda para la API y para imprimir el recibo y las guías
 * (ADR-035), con los importes ya formateados.
 */
final class DatosEncomienda
{
    /** @return array<string, mixed> */
    public static function de(Encomienda $e): array
    {
        $empresa = $e->getEmpresa();
        $estacion = $e->getEstacion();
        $salida = $e->getSalida();

        return [
            "id" => $e->getId(),
            "codigo" => $e->getCodigo(),
            "creada" => $e->getCreada()->format(DATE_ATOM),
            "estado" => $e->getEstado()->value,
            "estadoEtiqueta" => $e->getEstado()->etiqueta(),
            "modalidad" => $e->getModalidad()->value,
            "modalidadEtiqueta" => $e->getModalidad()->etiqueta(),
            // El recibo dice «Por cobrar» hasta que la paga quien la retira.
            "porCobrar" => $e->getModalidad() === ModalidadEncomienda::POR_COBRAR && $e->getFactura() === null && $e->getNumeroAcceso() === null,
            "empresa" => $empresa === null ? null : [
                "id" => $empresa->getId(),
                "nombre" => $empresa->getNombre(),
                "nit" => $empresa->getNit(),
                "direccion" => $empresa->getDireccion(),
                "telefono" => self::telefono($empresa->getTelefono()),
            ],
            "estacion" => $estacion === null ? null : ["id" => $estacion->getId(), "nombre" => $estacion->getNombre(), "direccion" => $estacion->getDireccion()],
            "origen" => ["id" => $e->getOrigen()?->getId(), "nombre" => $e->getOrigen()?->getNombre(), "direccion" => $e->getOrigen()?->getDireccion()],
            "destino" => ["id" => $e->getDestino()?->getId(), "nombre" => $e->getDestino()?->getNombre(), "direccion" => $e->getDestino()?->getDireccion(), "alias" => $e->getDestino()?->getAlias()],
            "ruta" => $e->getRuta() === null ? null : ["id" => $e->getRuta()->getId(), "nombre" => $e->getRuta()->getNombre()],
            "ubicacion" => $e->getUbicacion() === null ? null : ["id" => $e->getUbicacion()->getId(), "nombre" => $e->getUbicacion()->getNombre()],
            "salida" => $salida === null ? null : [
                "id" => $salida->getId(),
                "fecha" => $salida->getFecha()?->format(DATE_ATOM),
                "ruta" => $salida->getRuta()?->getNombre(),
                "bus" => $salida->getBus()?->getCodigo(),
                "estado" => $salida->getEstado()->value,
            ],
            "remitente" => self::cliente($e->getRemitente()),
            "destinatario" => self::cliente($e->getDestinatario()),
            "receptor" => self::cliente($e->getReceptor()),
            "entregadaEn" => $e->getEntregadaEn()?->format(DATE_ATOM),
            "articulos" => array_values(array_map(static fn(EncomiendaArticulo $a) => [
                "id" => $a->getId(),
                "tipo" => $a->getTipo()->value,
                "especial" => $a->getEspecial() === null ? null : ["id" => $a->getEspecial()->getId(), "nombre" => $a->getEspecial()->getNombre()],
                "descripcion" => $a->getDescripcion(),
                "cantidad" => $a->getCantidad(),
                "peso" => $a->getPeso(),
                "alto" => $a->getAlto(),
                "ancho" => $a->getAncho(),
                "largo" => $a->getLargo(),
                "resumen" => $a->getResumen(),
                "tarifa" => DatosBoleto::importe($a->getTarifa()),
                "recargo" => DatosBoleto::importe($a->getRecargo()),
                "importe" => DatosBoleto::importe($a->getImporte()),
            ], $e->getArticulos()->toArray())),
            "bultos" => $e->getBultos(),
            "descuento" => DatosBoleto::importe($e->getDescuento()),
            "total" => DatosBoleto::importe($e->getTotal()),
            "estadoFacturacion" => $e->getEstadoFacturacion()->value,
            "numeroAcceso" => $e->getNumeroAcceso(),
            "contingencia" => $e->getEstadoFacturacion() === EstadoFacturacion::PENDIENTE && $e->getNumeroAcceso() !== null,
            "factura" => self::factura($e->getFactura()),
            "clienteFactura" => self::cliente($e->getClienteFactura()),
            "tipoPago" => $e->getTipoPago()?->getNombre(),
            "venta" => $e->getBoletoVenta()?->getId(),
            "boleto" => $e->getBoleto() === null ? null : ["id" => $e->getBoleto()->getId(), "asiento" => $e->getBoleto()->getAsiento()?->getNumero()],
            "usuario" => $e->getUsuario()?->getUsername(),
            "observacion" => $e->getObservacion(),
            "motivoAnulacion" => $e->getMotivoAnulacion(),
        ];
    }

    /**
     * Algunas empresas migradas guardan el teléfono como un arreglo PHP
     * serializado (`a:3:{i:0;s:14:"(502) 2238…"`, a veces truncado): se toma
     * el primero; si no se puede leer, ninguno.
     */
    public static function telefono(?string $t): ?string
    {
        if ($t === null || !str_starts_with($t, "a:")) {
            return $t;
        }
        $lista = @unserialize($t, ["allowed_classes" => false]);
        if (is_array($lista)) {
            $primero = array_values(array_filter($lista, static fn($v) => is_string($v) && trim($v) !== ""))[0] ?? null;

            return $primero !== null ? trim($primero) : null;
        }

        return null;
    }

    /** @return array{id: int, nombre: string, nit: string, telefono: ?string, documento: ?string}|null */
    public static function cliente(?Cliente $c): ?array
    {
        return $c === null ? null : [
            "id" => (int) $c->getId(),
            "nombre" => $c->getNombreCompleto(),
            "nit" => $c->getNit() ?: "CF",
            "telefono" => $c->getTelefono(),
            "documento" => $c->getNumeroDocumento(),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function factura(?Factura $f): ?array
    {
        return $f === null ? null : [
            "numero" => $f->getDte(),
            "serie" => $f->getSerie(),
            "uuid" => strtoupper((string) $f->getUuid()?->toRfc4122()),
            "fechaCertificacion" => ($f->getFechaCertificacion() ?? $f->getFecha())?->format(DATE_ATOM),
            "certificador" => $f->getCertificadorNombre(),
            "certificadorNit" => $f->getCertificadorNit(),
            "receptorNit" => $f->getReceptopNit(),
            "receptorNombre" => $f->getReceptorNombre(),
            "anulada" => $f->isAnulada(),
        ];
    }
}
