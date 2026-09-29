import { describe, expect, it } from 'vitest'
import type { Comprobante } from '@/core/venta/types'
import { ticketHtml } from '../ticket'

const base: Comprobante = {
  id: 7,
  token: null,
  canal: 'estacion',
  estado: 'confirmada',
  estadoFacturacion: 'certificada',
  cortesia: false,
  creada: '2026-09-27T09:52:00-06:00',
  codigoBarras: '00000007',
  empresa: {
    nombre: 'Autobuses Maya De Oro, S.A.',
    nit: '43977006',
    direccion: null,
    telefono: null,
  },
  estacion: { nombre: 'Aguilar Batres', direccion: 'Calz. Aguilar Batres 7-55, zona 12' },
  agencia: null,
  vendedor: 'taquilla',
  factura: {
    numero: 935022181,
    serie: '6BF69EA1',
    uuid: '6BF69EA1-37BB-4E65-B134-DD64F114EAD7',
    fechaCertificacion: '2026-09-27T09:52:00-06:00',
    certificador: 'Formularios Continuos de Centro America, S.A.',
    certificadorNit: '4150686',
    receptorNit: '28119266',
    receptorNombre: 'BAUTISTA OROZCO, JENNER OSWALDO',
  },
  cliente: { nombre: 'Jenner <b>', nit: '28119266', email: null },
  recorrido: { id: 1, salida: '2026-09-27T10:45:00-06:00', bus: 'TPB060B' },
  origen: { nombre: 'Aguilar Batres', direccion: null },
  destino: { nombre: 'San Marcos', direccion: null },
  boletos: [
    {
      id: 1,
      asiento: 31,
      clase: 'A',
      pasajero: null,
      precio: { centavos: 10000, moneda: 'GTQ', texto: 'Q 100.00' },
      observacion: null,
      estado: 'emitido',
    },
  ],
  total: { centavos: 10000, moneda: 'GTQ', texto: 'Q 100.00' },
  tipoPago: 'Efectivo',
}

describe('ticketHtml', () => {
  it('lleva los datos del DTE, del viaje y el código de barras', () => {
    const html = ticketHtml(base)
    expect(html).toContain('Número DTE: 935022181 | Serie DTE: 6BF69EA1')
    expect(html).toContain('6BF69EA1-37BB-4E65-B134-DD64F114EAD7')
    expect(html).toContain('10:45')
    expect(html).toContain('San Marcos')
    expect(html).toContain('<svg')
    expect(html).toContain('Formularios Continuos de Centro America')
  })

  it('escapa el HTML de los datos', () => {
    expect(ticketHtml(base)).toContain('Jenner &lt;b&gt;')
  })

  it('sin factura: cortesía, agencia o pendiente', () => {
    expect(
      ticketHtml({ ...base, factura: null, cortesia: true, estadoFacturacion: 'no_aplica' }),
    ).toContain('CORTESÍA')
    expect(ticketHtml({ ...base, factura: null, estadoFacturacion: 'pendiente' })).toContain(
      'PENDIENTE DE CERTIFICAR',
    )
  })
})
