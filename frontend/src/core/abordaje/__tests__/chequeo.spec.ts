import { describe, expect, it } from 'vitest'
import { acciones, alternar, conteo, etiquetaSalida, marcarTodos, seleccion, vigentes, type Marcas } from '../chequeo'
import type { BoletoAbordaje, PuedeAbordaje } from '../types'

const boleto = (id: number, extra: Partial<BoletoAbordaje> = {}): BoletoAbordaje => ({
  id,
  asiento: id,
  clase: 'A',
  estado: 'emitido',
  documento: null,
  sube: 'Melchor',
  baja: 'Guatemala',
  ordenSube: 0,
  cliente: null,
  chequeable: true,
  ...extra,
})

const todo: PuedeAbordaje = { chequear: true, abordar: true, iniciar: true, finalizar: true, manifiestos: true }

describe('chequeo de boletos', () => {
  it('SI y NO se excluyen; repetir la marca la quita', () => {
    let m: Marcas = {}
    m = alternar(m, 1, 'si')
    expect(m).toEqual({ 1: 'si' })
    m = alternar(m, 1, 'no')
    expect(m).toEqual({ 1: 'no' })
    m = alternar(m, 1, 'no')
    expect(m).toEqual({})
  })

  it('marcar todos solo toca los chequeables', () => {
    const boletos = [boleto(1), boleto(2, { chequeable: false, estado: 'chequeado' }), boleto(3)]
    expect(marcarTodos({ 3: 'no' }, boletos, 'si')).toEqual({ 1: 'si', 3: 'si' })
  })

  it('al recargar se descartan las marcas de boletos que ya no se pueden chequear', () => {
    const boletos = [boleto(1), boleto(2, { estado: 'chequeado', chequeable: false })]
    expect(vigentes({ 1: 'si', 2: 'si', 9: 'no' }, boletos)).toEqual({ 1: 'si' })
  })

  it('arma lo que se envía, ordenado', () => {
    expect(seleccion({ 5: 'si', 2: 'no', 1: 'si' })).toEqual({ si: [1, 5], no: [2] })
    expect(seleccion({})).toEqual({ si: [], no: [] })
  })

  it('cuenta a bordo, pendientes y los del origen que se cancelan al iniciar', () => {
    const boletos = [boleto(1), boleto(2, { ordenSube: 3 }), boleto(3, { estado: 'chequeado' }), boleto(4, { estado: 'transito', ordenSube: 3 })]
    expect(conteo(boletos)).toEqual({ total: 4, abordo: 2, pendientes: 2, pendientesOrigen: 1 })
  })

  it('abordar una programada, iniciar una que aborda, finalizar una iniciada, con permiso', () => {
    const nada = { abordar: false, iniciar: false, finalizar: false }
    expect(acciones({ estado: 'programada' }, todo)).toEqual({ ...nada, abordar: true })
    expect(acciones({ estado: 'abordando' }, todo)).toEqual({ ...nada, iniciar: true })
    expect(acciones({ estado: 'iniciada' }, todo)).toEqual({ ...nada, finalizar: true })
    expect(acciones({ estado: 'finalizada' }, todo)).toEqual(nada)
    expect(acciones({ estado: 'abordando' }, { ...todo, iniciar: false })).toEqual(nada)
    expect(acciones({ estado: 'iniciada' }, { ...todo, finalizar: false })).toEqual(nada)
  })

  it('etiqueta del selector', () => {
    const e = etiquetaSalida({ id: 1, fecha: '2026-10-11T18:00:00-06:00', estado: 'abordando', ruta: 'MELCHOR - GUATE', origenId: 1, destinoId: 2, empresa: 'MAYA DE ORO', bus: '39' })
    expect(e).toContain('MELCHOR - GUATE · MAYA DE ORO · Bus 39')
    expect(e).toMatch(/^\d{2}\/\d{2}\/2026 \d{2}:\d{2} · /)
  })
})
