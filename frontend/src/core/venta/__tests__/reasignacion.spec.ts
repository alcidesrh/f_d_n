import { describe, expect, it } from 'vitest'
import { agruparParaTicket, emparejar, listaParaReasignar, motivoNoOperable, salidasReasignables, tramoDelBoleto } from '../reasignacion'
import type { BoletoOperable, Importe, SalidaResumen } from '../types'

const q = (centavos: number): Importe => ({ centavos, moneda: 'GTQ', texto: `Q ${(centavos / 100).toFixed(2)}` })

const boleto = (id: number, extra: Partial<BoletoOperable> = {}, venta: Partial<BoletoOperable['venta']> = {}): BoletoOperable => ({
  id,
  estado: 'emitido',
  asiento: { id: id * 10, numero: id, clase: 'A' },
  pasajero: 'Ana',
  precio: q(10000),
  observacion: null,
  trayecto: { id: 5, origenId: 1, destinoId: 3, origen: 'A', destino: 'B' },
  salida: { id: 100, fecha: '2026-10-11T08:00:00-06:00', estado: 'programada', empresa: { id: 1, nombre: 'Maya' }, bus: '42' },
  venta: { id: 7, canal: 'estacion', cortesia: false, estadoFacturacion: 'certificada', cliente: null, ...venta },
  operable: true,
  motivo: null,
  ...extra,
})

const salida = (id: number, fecha: string, empresa: number | null, estado: SalidaResumen['estado'] = 'programada'): SalidaResumen =>
  ({ id, salida: fecha, salidaEstacion: null, estado, empresa: empresa === null ? null : { id: empresa, nombre: `E${empresa}` }, bus: null, trayecto: { id: 5, origen: { id: 1, nombre: 'A' }, destino: { id: 2, nombre: 'B' } }, vendidos: 0, capacidad: 40 }) as SalidaResumen

describe('salidas a las que se puede reasignar', () => {
  const ahora = new Date('2026-10-10T12:00:00-06:00')
  const salidas = [
    salida(1, '2026-10-11T08:00:00-06:00', 1),
    salida(2, '2026-10-11T09:00:00-06:00', 2),
    salida(3, '2026-10-10T11:00:00-06:00', 1),
    salida(4, '2026-10-11T10:00:00-06:00', 1, 'iniciada'),
    salida(5, '2026-10-11T11:00:00-06:00', 1, 'abordando'),
  ]

  it('solo las de la misma empresa, futuras y programadas o abordando', () => {
    expect(salidasReasignables(salidas, [boleto(1)], ahora).map((s) => s.id)).toEqual([1, 5])
  })

  it('lo vendido en la página puede ir a otra empresa', () => {
    expect(salidasReasignables(salidas, [boleto(1, {}, { canal: 'web' })], ahora).map((s) => s.id)).toEqual([1, 2, 5])
  })

  it('boletos de empresas distintas (venta por la web) solo van a otra salida si todos son web', () => {
    const mezcla = [boleto(1), boleto(2, { salida: { ...boleto(2).salida, empresa: { id: 2, nombre: 'E2' } } })]
    expect(salidasReasignables(salidas, mezcla, ahora)).toEqual([])
  })
})

describe('parejas boleto → asiento', () => {
  const boletos = [boleto(1), boleto(2, { precio: q(12000) })]

  it('cada boleto toma el asiento de su posición y se compara el precio', () => {
    const p = emparejar(boletos, [31, 32], new Map([[31, q(10000)], [32, q(12000)]]))
    expect(p.map((x) => [x.asiento, x.igual])).toEqual([[31, true], [32, true]])
    expect(listaParaReasignar(p)).toBe(true)
  })

  it('falta un asiento o el precio no coincide: no está lista', () => {
    expect(listaParaReasignar(emparejar(boletos, [31], new Map([[31, q(10000)]])))).toBe(false)
    const distinto = emparejar(boletos, [31, 32], new Map([[31, q(10000)], [32, q(9000)]]))
    expect(distinto[1]?.igual).toBe(false)
    expect(listaParaReasignar(distinto)).toBe(false)
  })

  it('sin cotizar todavía no se sabe', () => {
    expect(emparejar(boletos, [31, 32], new Map())[0]?.igual).toBeNull()
    expect(listaParaReasignar([])).toBe(false)
  })

  it('otra moneda no es el mismo precio', () => {
    const p = emparejar([boleto(1)], [31], new Map([[31, { ...q(10000), moneda: 'USD' }]]))
    expect(p[0]?.igual).toBe(false)
  })
})

describe('tickets a reimprimir', () => {
  it('uno por venta y viaje', () => {
    const otraSalida = boleto(3, { salida: { ...boleto(3).salida, id: 200 } })
    const otraVenta = boleto(4, {}, { id: 8 })
    expect(agruparParaTicket([boleto(1), boleto(2), otraSalida, otraVenta])).toEqual([
      { venta: 7, boletos: [1, 2] },
      { venta: 7, boletos: [3] },
      { venta: 8, boletos: [4] },
    ])
  })
})

describe('motivo de no operar', () => {
  it('nombra el primer boleto que no se puede operar', () => {
    expect(motivoNoOperable([boleto(1), boleto(2, { operable: false, motivo: 'La salida ya empezó.' })])).toBe('Asiento 2: La salida ya empezó.')
    expect(motivoNoOperable([boleto(1)])).toBeNull()
  })
})

describe('tramo del boleto en otra salida', () => {
  const trayectos = [
    { id: 10, origen: 1, destino: 3, completo: true, clases: ['A' as const] },
    { id: 11, origen: 1, destino: 2, completo: false, clases: ['A' as const] },
  ]

  it('si la salida ofrece el mismo tramo, se sube y baja donde antes', () => {
    expect(tramoDelBoleto({ trayectos }, boleto(1))).toEqual({ sube: 1, baja: 3 })
  })

  it('si no lo ofrece, no se propone nada', () => {
    expect(tramoDelBoleto({ trayectos: [trayectos[1]!] }, boleto(1))).toBeNull()
  })
})
