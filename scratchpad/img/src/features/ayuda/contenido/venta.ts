/** Venta de boletos en taquilla, por agencia, cortesías y factura. */
import type { Guia } from '@/core/ayuda/tipos'
import { CORTESIA, REASIGNAR, SIN_FACTURA, VENTA } from './audiencias'

export const guiasVenta: Guia[] = [
  {
    id: 'vender-boleto',
    tema: 'venta',
    titulo: 'Vender boletos en taquilla',
    resumen: 'Del cliente al ticket impreso, en la pantalla de Venta.',
    para: [VENTA],
    antes:
      'Se vendía desde la pantalla de salidas del sistema anterior, con otra secuencia de pasos para el cliente y para el asiento.',
    pasos: [
      'Abra «Venta» en el menú. Arriba aparecen las salidas de tres días: la elegida queda al centro, el día anterior y el siguiente a los lados.',
      'Elija la salida. El croquis del bus muestra los asientos libres, vendidos y apartados.',
      'Indique el cliente: busque por documento, NIT o nombre, o registre uno nuevo.',
      'Elija en qué parada sube y en cuál baja. El precio sale de la tarifa del tramo.',
      'Seleccione el asiento en el croquis. Puede elegir varios para el mismo cliente.',
      'Elija el tipo de pago y la moneda, y pulse «Facturar». El sistema emite la factura y guarda los boletos.',
      'Pulse «Imprimir ticket» para el ticket de 80 mm, o «PDF».',
    ],
    ojo: [
      'Un asiento que aparece en colores distintos no siempre está libre: vea «Qué significan los colores del croquis».',
      'El tramo es lo que ocupa el asiento: vea [tramo](ayuda:concepto-tramo). Si el cliente viaja solo una parte, el resto queda libre para venderlo a otro.',
      'Si la factura no se emite, la venta no se pierde: vea «Cuando la factura no se emite». La factura es la de la [factura electrónica](ayuda:concepto-factura-electronica).',
    ],
    abrir: { etiqueta: 'Abrir Venta', destino: { name: 'venta' } },
    pantallas: ['venta'],
    palabras: ['taquilla', 'pasaje', 'ticket', 'vender', 'cobrar', 'cliente', 'boleto nuevo'],
  },
  {
    id: 'colores-del-croquis',
    tema: 'venta',
    titulo: 'Qué significan los colores del croquis',
    resumen: 'Cada color dice quién vendió el asiento o si conviene venderlo.',
    para: [VENTA],
    pasos: [
      'Pizarra: libre (A y B; B es el de mejor confort y más caro).',
      'Libre recomendado: el asiento encaja con el tramo y no parte el bus en pedazos inútiles. Alterna con el color de la venta del asiento vecino que lo recomienda.',
      'Azul: vendido en esta estación.',
      'Ámbar: vendido en la página web.',
      'Esmeralda: vendido por una agencia.',
      'Gris apagado con punto esmeralda: cortesía. Con punto rojo: voucher (boleto que no se cobra, de antes del sistema nuevo).',
      'Contorno naranja punteado: apartado en la página web mientras el cliente paga.',
    ],
    ojo: [
      'Un asiento apartado vuelve a libre solo si el cliente no paga: el apartado dura 15 minutos y se extiende mientras paga.',
      'Un asiento vendido por otra estación no se puede vender aquí.',
    ],
    palabras: [
      'color',
      'croquis',
      'asiento',
      'libre',
      'recomendado',
      'vendido',
      'preventa',
      'apartado',
    ],
  },
  {
    id: 'vender-por-agencia',
    tema: 'venta',
    titulo: 'Vender como agencia',
    resumen: 'Las ventas de una agencia descuentan de su saldo.',
    para: [VENTA],
    antes: 'En el sistema anterior, las agencias vendían con su bono; el saldo se llevaba aparte.',
    pasos: [
      'Entre con el usuario de la agencia: la venta sale ya con la agencia elegida.',
      'Elija salida, cliente, tramo y asiento como en taquilla.',
      'El botón dice «Vender» (no «Facturar»): la venta descuenta el importe del saldo de la agencia.',
    ],
    ojo: [
      'Si el saldo no alcanza, el sistema no deja vender. Un depósito de la agencia se acredita en la pantalla de Agencias.',
      'Las ventas de agencia no llevan factura electrónica por separado.',
    ],
    palabras: ['agencia', 'saldo', 'comision', 'bono', 'vender agencia'],
  },
  {
    id: 'cuando-la-factura-no-se-emite',
    tema: 'venta',
    titulo: 'Cuando la factura no se emite',
    resumen: 'La venta queda pendiente y la factura se reintenta.',
    para: [VENTA],
    pasos: [
      'Si el certificador de factura electrónica no responde, el sistema muestra el aviso «No se pudo emitir la factura».',
      'Pulse «Reintentar» cuando vuelva a tener conexión.',
      'Si no puede reintentar y la venta debe seguir, use «Cancelar» (la venta no queda hecha) o, si tiene el permiso, «Vender sin factura».',
    ],
    ojo: [
      'Una venta sin factura queda marcada y la emite el sistema después: no la vuelva a cobrar.',
      'Sin conexión no se emiten facturas; el cliente puede llevar su ticket sin factura solo en contingencia.',
    ],
    palabras: ['factura fallida', 'dte', 'certificador', 'reintentar', 'sin conexion', 'factura'],
  },
  {
    id: 'venta-sin-factura',
    tema: 'venta',
    titulo: 'Vender sin factura (contingencia)',
    resumen: 'Para cuando no hay forma de emitir la factura en el momento.',
    para: [SIN_FACTURA],
    pasos: [
      'Cuando el aviso de factura fallida aparece y no puede reintentar, pulse «Vender sin factura».',
      'Entregue el ticket y anote el motivo si el sistema lo pide.',
      'No hace falta hacer nada más: el sistema emite la factura pendiente cuando el certificador vuelve.',
    ],
    ojo: [
      'Úselo solo en contingencia. Vender sin factura todos los días es un problema de control.',
    ],
    palabras: ['contingencia', 'sin factura', 'caida', 'sistema caido'],
  },
  {
    id: 'emitir-cortesia',
    tema: 'venta',
    titulo: 'Emitir una cortesía',
    resumen: 'Un boleto sin cobro ni factura, para cuando la empresa lo decide.',
    para: [CORTESIA],
    antes:
      'Se llamaba voucher. Los vouchers de antes se conservan como voucher; los nuevos son cortesías.',
    pasos: [
      'En la venta, elija la salida, el cliente y el asiento como siempre.',
      'En el pago, elija «Cortesía» en lugar de cobrar.',
      'Pulse «Emitir». El boleto sale con la marca de cortesía en el croquis y en los reportes. Qué es una [cortesía](ayuda:concepto-cortesia-y-voucher) y en qué se diferencia del voucher: vea el glosario.',
    ],
    ojo: [
      'Si tiene un límite de cortesías por día y lo alcanzó, el sistema pide autorización (vea «Pedir una autorización»).',
      'La cortesía libera el asiento como cualquier boleto; si el cliente no viaja, sigue siendo una cortesía en el cuadre.',
    ],
    palabras: ['voucher', 'gratis', 'sin cobro', 'invitacion', 'cortesia'],
  },
  {
    id: 'reasignar-desde-la-venta',
    tema: 'venta',
    titulo: 'Reasignar un boleto desde la venta',
    resumen: 'Cambiar de asiento o de salida al cliente, al mismo precio.',
    para: [REASIGNAR],
    pasos: [
      'Desde la lista de boletos, elija el boleto y pulse «Reasignar»: la venta abre con el cliente y el precio del boleto.',
      'Elija la nueva salida y el asiento. El precio debe ser el mismo.',
      'Pulse «Reasignar». El boleto original queda como reasignado y el nuevo queda en la misma venta.',
      'Para dejarlo, pulse «Cancelar reasignación».',
    ],
    ojo: ['Solo puede reasignar a salidas de la misma empresa, y antes de la hora de salida.'],
    palabras: ['cambiar asiento', 'cambiar de bus', 'cambiar de salida', 'reasignacion'],
  },
]
