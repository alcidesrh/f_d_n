# Operativo — Ventas de Transporte (FDN)

Contexto que modela la operación de salidas de buses y la venta de pasajes:
geografía (enclaves y trayectos), flota (buses y asientos) y ventas (itinerarios y boletos).
IAM/seguridad y configuración de menús son contextos separados.

## Geografía

**Enclave**:
Un punto geográfico por donde pasa, para o sale un bus.
_Avoid_: lugar, punto

**Estacion**:
Un enclave donde los pasajeros abordan; es terminal de salida y de venta.
_Avoid_: terminal, estación rural

**Parada**:
Un enclave de parada intermedia dentro de un trayecto.
_Avoid_: punto de parada

**Trayecto**:
Un recorrido geográfico vectorial entre dos enclaves (origen → destino).
Es único por par de enclaves y puede estar compuesto de subtrayectos.
_Avoid_: ruta, recorrido, tramo

**Subtrayecto**:
Un segmento de un trayecto compuesto: un trayecto hijo con posición dentro de su trayecto padre.
_Avoid_: tramo, subrecorrido, subruta

## Flota

**Bus**:
Un vehículo de la flota, perteneciente a una empresa, con una disposición de asientos por clase.
_Avoid_: unidad, vehículo

**Asiento**:
Una plaza física de un bus, con una clase determinada.
_Avoid_: puesto

**Piloto**:
El conductor asignado a un bus en un itinerario.
_Avoid_: conductor, chofer

## Ventas

**Itinerario**:
La salida concreta de un bus: un trayecto, una fecha, un bus y un piloto.
Cada itinerario fija su ruta al crearse, resolviendo el trayecto de sus enclaves de origen y destino.
_Avoid_: salida, servicio, viaje, recorrido

**BoletoAsiento**:
Un asiento vendido dentro de un itinerario, para un cliente y un trayecto,
con precio y estado propios.
_Avoid_: boleto, ticket

**BoletoVenta**:
Una venta que agrupa uno o más boletos de asiento y puede emitir una factura.
_Avoid_: venta, tiquetera

**BoletoTarifa**:
El precio de referencia aplicable a un trayecto y una clase de asiento,
con hora y bus opcionales.
_Avoid_: tarifa, precio

**Factura**:
El documento fiscal de una venta, con un snapshot inmutable de emisor y receptor.
_Avoid_: recibo, comprobante

**Cliente**:
La persona que compra un boleto de asiento.
_Avoid_: pasajero, comprador

**Empresa**:
La línea transportista dueña de la operación: buses, pilotos, itinerarios y tarifas.
_Avoid_: compañía, operador