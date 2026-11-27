import { describe, expect, it } from 'vitest'
import type { Comprobante } from '@/core/venta/types'
import { ticketHtml, ticketsPorAsiento } from '../ticket'

const base: Comprobante = {
  id: 7,
  token: null,
  canal: 'estacion',
  estado: 'confirmada',
  estadoFacturacion: 'certificada',
  numeroAcceso: null,
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
    urlPdf: null,
  },
  cliente: { nombre: 'Jenner <b>', nit: '28119266', email: null },
  salida: {
    id: 1,
    salida: '2026-09-27T08:00:00-06:00',
    salidaOrigen: '2026-09-27T10:45:00-06:00',
    bus: 'TPB060B',
  },
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
  it('lleva los datos del DTE, del viaje y el número de la venta', () => {
    const html = ticketHtml(base)
    expect(html).toContain('Número DTE: 935022181 | Serie DTE: 6BF69EA1')
    expect(html).toContain('6BF69EA1-37BB-4E65-B134-DD64F114EAD7')
    expect(html).toContain('10:45')
    expect(html).toContain('San Marcos')
    expect(html).toContain('ID 00000007')
    expect(html).not.toContain('<svg')
    expect(html).toContain('Formularios Continuos de Centro America')
  })

  it('marca la hora de subida estimada y, si no se conoce, dice que es la del origen del bus', () => {
    const conSalida = (salidaPrecision: 'exacta' | 'estimada' | 'desconocida') => ticketHtml({ ...base, salida: { ...base.salida!, salidaPrecision, rutaOrigen: 'Guatemala' } })
    expect(conSalida('estimada')).toMatch(/10:45[^<]* aprox\./)
    expect(conSalida('exacta')).not.toContain('aprox.')
    expect(conSalida('desconocida')).toContain('(sale de Guatemala)')
  })

  it('escapa el HTML de los datos', () => {
    expect(ticketHtml(base)).toContain('Jenner &lt;b&gt;')
  })

  it('sin factura: cortesía, agencia o pendiente', () => {
    expect(
      ticketHtml({ ...base, factura: null, cortesia: true, estadoFacturacion: 'no_aplica' }),
    ).toContain('CORTESÍA')
    const contingencia = ticketHtml({
      ...base,
      factura: null,
      estadoFacturacion: 'pendiente',
      numeroAcceso: 290524861,
    })
    expect(contingencia).toContain('CONTINGENCIA')
    expect(contingencia).toContain('Número de acceso: 290524861')
  })

  it('lleva un QR de abordaje por asiento vivo (ADR-063)', () => {
    const boletos = base.boletos.map((b, i) => ({ ...b, codigoAbordaje: `FDN1.${b.id}.abcdefABCDEF`, estado: i === 0 ? b.estado : 'anulado' }))
    const html = ticketHtml({ ...base, boletos })
    expect(html.split('class="qr"').length - 1).toBe(1)
    expect(html).toContain('Al abordar, muestre el código QR de cada asiento.')
    expect(ticketHtml(base)).not.toContain('class="qr"')
  })
})

describe('ticketsPorAsiento', () => {
  it('parte la venta en un comprobante por asiento, con su precio como total', () => {
    const segundo = { ...base.boletos[0]!, id: 2, asiento: 32, precio: { centavos: 8000, moneda: 'GTQ', texto: 'Q 80.00' } }
    const venta = { ...base, boletos: [base.boletos[0]!, segundo], total: { centavos: 18000, moneda: 'GTQ', texto: 'Q 180.00' } }
    const tickets = ticketsPorAsiento(venta)
    expect(tickets.map((t) => t.boletos.map((b) => b.asiento))).toEqual([[31], [32]])
    expect(tickets.map((t) => t.total.texto)).toEqual(['Q 100.00', 'Q 80.00'])
    expect(tickets[1]!.factura).toEqual(base.factura)
  })

  it('con un solo asiento, la venta tal cual', () => {
    expect(ticketsPorAsiento(base)).toEqual([base])
  })
})

describe('página del ticket', () => {
  it('no fija el tamaño: el navegador centraría el ticket en el rollo de la térmica', () => {
    expect(ticketHtml(base)).not.toMatch(/@page\s*{[^}]*\bsize\s*:/)
  })
})
