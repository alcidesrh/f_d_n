import { describe, expect, it } from 'vitest'
import { boletosParaAnular, disponibilidadCompra, estadoCompra, viajesReasignables } from '../acciones'
import type { BoletoCompra, CompraWeb, VentaCompra } from '../types'

const ahora = new Date('2026-10-15T12:00:00-06:00')
const manana = '2026-10-16T08:00:00-06:00'
const ayer = '2026-10-14T08:00:00-06:00'
const q = (centavos: number) => ({ centavos, moneda: 'GTQ', texto: `Q${centavos / 100}` })

const boleto = (id: number, estado: BoletoCompra['estado'], salida = manana): BoletoCompra => ({ id, estado, asiento: id, salidaId: 1, salida })
const venta = (id: number, boletos: BoletoCompra[], extra: Partial<VentaCompra> = {}): VentaCompra => ({
  id,
  codigo: String(id).padStart(8, '0'),
  salidaId: 1,
  salida: boletos[0]?.salida ?? manana,
  origen: 'Guatemala',
  destino: 'Flores',
  empresa: 'FDN',
  asientos: boletos.map((b) => b.asiento).join(', '),
  cantidad: boletos.length,
  total: q(10000),
  facturacion: 'certificada',
  errorFacturacion: null,
  factura: { serie: 'A', numero: 1, uuid: 'u', urlPdf: null, anulada: false },
  correoEnviado: null,
  boletos,
  ...extra,
})
const compra = (ventas: VentaCompra[], extra: Partial<CompraWeb> = {}): CompraWeb => ({
  id: 9,
  token: 't',
  estado: 'completado',
  creado: ayer,
  actualizado: ayer,
  monto: q(10000),
  recargoPorciento: '0',
  viajes: ventas.length,
  tarjeta: { marca: 'visa', ultimos4: '1111' },
  autorizacion: null,
  referencia: 'ref',
  mensaje: null,
  empresa: { id: 1, nombre: 'FDN' },
  comprador: { nombre: 'Ana', nombres: 'Ana', apellido: null, email: 'a@b.c', telefono: null, nit: 'CF' },
  ventas,
  ...extra,
})
const todos = { anular: true, reasignar: true }

describe('estado de una compra web', () => {
  it('sin completar, el del pago', () => {
    expect(estadoCompra(compra([], { estado: 'rechazado' }))).toBe('rechazado')
  })

  it('completada: anulada, pospuesta o completada según sus boletos', () => {
    expect(estadoCompra(compra([venta(1, [boleto(1, 'anulado'), boleto(2, 'anulado')])]))).toBe('anulada')
    expect(estadoCompra(compra([venta(1, [boleto(1, 'pospuesto'), boleto(2, 'anulado')])]))).toBe('pospuesta')
    // Ida ya viajada, regreso pospuesto: sigue completada.
    expect(estadoCompra(compra([venta(1, [boleto(1, 'finalizado', ayer)]), venta(2, [boleto(2, 'pospuesto')])]))).toBe('completado')
  })
})

describe('acciones sobre una compra web', () => {
  it('sin completar solo se ve', () => {
    const d = disponibilidadCompra(compra([], { estado: 'autenticacion' }), todos, ahora)
    expect(d.ver).toBeNull()
    expect(Object.entries(d).filter(([, m]) => m === null).map(([a]) => a)).toEqual(['ver'])
  })

  it('emitido antes de su salida: todo', () => {
    const d = disponibilidadCompra(compra([venta(1, [boleto(1, 'emitido')])]), todos, ahora)
    expect(Object.values(d).every((m) => m === null)).toBe(true)
  })

  it('pospuesto: se reasigna o anula aunque su salida haya pasado, pero no se vuelve a posponer', () => {
    const c = compra([venta(1, [boleto(1, 'pospuesto', ayer)])])
    const d = disponibilidadCompra(c, todos, ahora)
    expect(d.reasignar).toBeNull()
    expect(d.anular).toBeNull()
    expect(d.posponer).not.toBeNull()
    expect(boletosParaAnular(c, ahora)).toEqual([1])
  })

  it('emitido con la salida ya pasada: ni reasignar, ni posponer, ni anular', () => {
    const d = disponibilidadCompra(compra([venta(1, [boleto(1, 'emitido', ayer)])]), todos, ahora)
    expect([d.reasignar, d.posponer, d.anular].every((m) => m !== null)).toBe(true)
  })

  it('sin permiso no se anula ni reasigna', () => {
    const d = disponibilidadCompra(compra([venta(1, [boleto(1, 'emitido')])]), { anular: false, reasignar: false }, ahora)
    expect(d.anular).toBe('Sin permiso')
    expect(d.reasignar).toBe('Sin permiso')
  })

  it('crear factura: solo con viajes facturables y boletos sin anular', () => {
    expect(disponibilidadCompra(compra([venta(1, [boleto(1, 'emitido')], { facturacion: 'no_aplica', factura: null })]), todos, ahora).factura).not.toBeNull()
    expect(disponibilidadCompra(compra([venta(1, [boleto(1, 'anulado')])]), todos, ahora).factura).not.toBeNull()
    expect(disponibilidadCompra(compra([venta(1, [boleto(1, 'pospuesto')], { facturacion: 'pendiente', factura: null })]), todos, ahora).factura).toBeNull()
  })

  it('ida y vuelta: se reasigna cada viaje con sus boletos', () => {
    const c = compra([venta(1, [boleto(1, 'emitido'), boleto(2, 'anulado')]), venta(2, [boleto(3, 'pospuesto', ayer)])])
    expect(viajesReasignables(c, ahora).map((v) => [v.etiqueta, v.boletos])).toEqual([
      ['Ida', [1]],
      ['Regreso', [3]],
    ])
  })
})
