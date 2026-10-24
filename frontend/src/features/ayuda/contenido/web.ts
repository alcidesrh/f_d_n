/** Venta en línea: las compras de la página web y su configuración. */
import type { Guia } from '@/core/ayuda/tipos'
import { PAGINA } from './audiencias'

export const guiasWeb: Guia[] = [
  {
    id: 'compras-en-linea',
    tema: 'web',
    titulo: 'Revisar las compras de la página web',
    resumen: 'Cada compra del sitio, con sus boletos y sus acciones.',
    para: [PAGINA],
    pasos: [
      'Abra «Venta en línea» en el menú.',
      'Filtre las compras por fecha, estado o comprador. Los totales de lo filtrado aparecen arriba.',
      'Pulse «Ver» para el detalle y «Ver boleto (PDF)» para el boleto del cliente.',
      'Desde la compra: «Editar» (los datos del comprador), «Reasignar», «Posponer», «Anular», «Crear factura» y «Reenviar boleto».',
    ],
    ojo: [
      'Cada boleto ofrece solo las acciones que se pueden hacer: si una falta, el panel dice por qué.',
      '«Crear factura» anula la vigente en el certificador y emite otra con los datos actuales. No envía nada al cliente.',
      '«Reenviar boleto» vuelve a mandar el boleto al correo del comprador.',
    ],
    abrir: { etiqueta: 'Abrir Venta en línea', destino: { name: 'venta-en-linea' } },
    pantallas: ['venta-en-linea'],
    palabras: ['pagina web', 'compra online', 'tarjeta', 'comprador', 'pagina'],
  },
  {
    id: 'posponer-compra-en-linea',
    tema: 'web',
    titulo: 'Posponer un boleto de la página web',
    resumen: 'Libera el asiento y guarda la venta para reanudarla después.',
    para: [PAGINA],
    antes:
      'Un cliente que no podía viajar pedía que le guardaran el dinero; ahora se pospone la compra y queda registrada.',
    pasos: [
      'En la compra, pulse «Posponer» en el boleto.',
      'El asiento queda libre para otro cliente. La venta, su factura y su importe se conservan.',
      'Para reanudarla, reasigne el boleto a otra salida de la misma empresa (aunque la original ya haya pasado).',
      'Si el cliente ya no viaja, anule el boleto en lugar de reanudarlo.',
    ],
    ojo: [
      'Un boleto pospuesto aparece como tal en el listado de boletos hasta que se reasigna o se anula.',
    ],
    palabras: ['aplazar', 'cambiar fecha', 'guardar boleto', 'reanudar'],
  },
  {
    id: 'configurar-venta-en-linea',
    tema: 'web',
    titulo: 'Recargo, venta activa y cierre',
    resumen: 'Lo que la página web cobra de más y hasta cuándo vende.',
    para: [PAGINA],
    pasos: [
      'En «Venta en línea», use la sección de configuración.',
      'Recargo: el porciento que la página suma a la tarifa. Un pago en curso conserva el recargo que tenía al empezar.',
      'Venta activa: apague la venta en línea si necesita cerrarla por completo.',
      'Cierre: minutos antes de la salida en que la página deja de vender. Se guarda con «Guardar».',
    ],
    ojo: ['Cambiar el recargo no altera las compras ya pagadas.'],
    palabras: ['porcentaje', 'recargo', 'cerrar venta', 'apagar pagina', 'minutos antes'],
  },
  {
    id: 'mensajes-de-contacto',
    tema: 'web',
    titulo: 'Mensajes de contacto de la página',
    resumen: 'Lo que escriben los visitantes desde el formulario de contacto.',
    para: [PAGINA],
    pasos: [
      'En «Venta en línea», use la sección de mensajes de contacto.',
      'Pulse un mensaje para leerlo. Quedan marcados como leídos.',
      'Use «Marcar sin leer» si quiere volver a revisarlo.',
    ],
    palabras: ['contacto', 'formulario', 'consulta del cliente', 'mensaje del sitio'],
  },
]
