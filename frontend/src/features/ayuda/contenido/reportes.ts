/** Tablero de inicio y los reportes de venta y de encomiendas (PDF y Excel). */
import type { Guia } from '@/core/ayuda/tipos'
import { ENCOMIENDAS, REPORTE_ENCOMIENDAS, REPORTE_VENTAS } from './audiencias'

export const guiasReportes: Guia[] = [
  {
    id: 'tablero-de-inicio',
    tema: 'reportes',
    titulo: 'El tablero de inicio',
    resumen: 'Lo que pide atención hoy y cómo va la venta frente al periodo anterior.',
    para: [REPORTE_VENTAS],
    pasos: [
      'La portada muestra la venta del periodo elegido: hoy, 7, 30 o 90 días. Cambie el periodo arriba.',
      'Los indicadores comparan con el periodo anterior y la tendencia separa los canales (taquilla, agencia y página web).',
      '«Alertas» pide acción: facturas sin certificar, tarjetas de operación vencidas, salidas sin cerrar, agencias con saldo para menos de 3 días, caída de ingresos, anulaciones altas, y salidas casi llenas o casi vacías.',
      '«Ocupación por día y hora» y «Rendimiento por ruta» muestran dónde se vende y dónde no.',
    ],
    ojo: [
      'La venta cuenta por la fecha en que se vendió (caja). La ocupación cuenta por la fecha de salida, y solo las que ya salieron.',
      'Las ventas de agencia siempre son ingreso, aunque traigan voucher.',
    ],
    abrir: { etiqueta: 'Abrir el tablero', destino: { name: 'dashboard' } },
    pantallas: ['dashboard'],
    palabras: ['inicio', 'portada', 'indicadores', 'alertas', 'resumen del dia', 'tendencia'],
  },
  {
    id: 'cuadre-de-venta',
    tema: 'reportes',
    titulo: 'Cuadre de venta de boletos',
    resumen: 'Cuánto se vendió, por usuario, bus, salida, tarjeta y anulados.',
    para: [REPORTE_VENTAS],
    pasos: [
      'Abra «Cuadre venta boletos» en el menú de reportes.',
      'Elija el rango de fechas y, si quiere, la estación o la empresa.',
      'Pulse «Generar vista previa» para ver las cifras antes de imprimir.',
      'Pulse «Generar PDF» para el cuadre completo.',
    ],
    ojo: [
      'Quien tiene una estación o empresa asignada solo ve la suya.',
      'El PDF incluye también los boletos anulados del periodo.',
    ],
    abrir: { etiqueta: 'Abrir Cuadre de venta', destino: { name: 'reporte-cuadre-venta-boletos' } },
    pantallas: ['reporte-cuadre-venta-boletos'],
    palabras: ['cierre de caja', 'corte', 'caja del dia', 'totales', 'cuadre'],
  },
  {
    id: 'detalle-de-factura',
    tema: 'reportes',
    titulo: 'Detalle de facturas de boletos',
    resumen: 'Un renglón por boleto, para contabilidad.',
    para: [REPORTE_VENTAS],
    pasos: [
      'Abra «Detalle factura boletos» en el menú de reportes.',
      'Elija el rango y pulse «Generar vista previa» o directamente el formato.',
      'Elija «Generar PDF» o «Generar Excel».',
    ],
    ojo: [
      'El Excel trae un renglón por boleto, con su factura, su venta y su estado: sirve para revisar factura por factura.',
    ],
    abrir: {
      etiqueta: 'Abrir Detalle de factura',
      destino: { name: 'reporte-detalle-factura-boletos' },
    },
    pantallas: ['reporte-detalle-factura-boletos'],
    palabras: ['excel', 'xlsx', 'factura', 'contabilidad', 'boletos por factura'],
  },
  {
    id: 'cuadre-de-vouchers',
    tema: 'reportes',
    titulo: 'Cuadre de vouchers y cortesías',
    resumen: 'Los boletos que no se cobraron, con su motivo.',
    para: [REPORTE_VENTAS],
    pasos: [
      'Abra «Cuadre voucher» en el menú de reportes.',
      'Elija el rango y pulse «Generar PDF».',
    ],
    ojo: [
      'Muestra los boletos que no se cobraron, con su motivo, para cuadrarlos contra la venta.',
    ],
    abrir: { etiqueta: 'Abrir Cuadre de voucher', destino: { name: 'reporte-cuadre-voucher' } },
    pantallas: ['reporte-cuadre-voucher'],
    palabras: ['voucher', 'cortesia', 'sin cobro', 'gratis'],
  },
  {
    id: 'reportes-de-encomiendas',
    tema: 'reportes',
    titulo: 'Reportes de encomiendas',
    resumen: 'Manifiesto de carga, cuadre de caja, detalle de facturas y pendientes.',
    para: [REPORTE_ENCOMIENDAS, ENCOMIENDAS],
    pasos: [
      'Abra el reporte que necesita desde el menú de encomiendas: manifiesto de carga, cuadre, detalle de factura, detalle general, guías internas o pendientes de entregar.',
      'Elija fecha, estación o salida según el reporte.',
      'Pulse «Generar vista previa» para ver las cifras, o «Generar PDF» / «Generar Excel» para el archivo.',
    ],
    ojo: [
      'Los reportes de caja (cuadre y detalle de factura) requieren permiso de reportes de encomiendas. El manifiesto y el pendiente de entrega también los ve quien opera encomiendas.',
      'El manifiesto de carga es el que acompaña cada salida con encomiendas.',
    ],
    palabras: ['manifiesto', 'cuadre encomiendas', 'pendientes de entregar', 'guias internas'],
  },
]
