import { describe, expect, it } from 'vitest'
import { ESTADOS_CALENDARIO, aQueryCalendario, ahoraEn, horaMinuto, leyenda, matriz, nivel, textoOcupacion, tonoRuta, totales, ventana } from '../calendario'
import type { RutaCalendario, SalidaCalendario } from '../types'

const salida = (id: number, fecha: string, extra: Partial<SalidaCalendario> = {}): SalidaCalendario => ({
  id,
  fecha,
  llegada: null,
  estado: 'programada',
  atrasada: false,
  rutaId: 1,
  busId: 1,
  empresaId: 1,
  vendidos: 0,
  capacidad: 40,
  ...extra,
})

const ruta = (id: number, nombre: string): RutaCalendario => ({ id, nombre, origen: 'Guatemala', destino: 'Flores', origenAlias: 'GUA', destinoAlias: 'FLO', paradas: 2 })

describe('calendario de salidas', () => {
  it('la ventana empieza en la fecha elegida', () => {
    expect(ventana('2026-10-22', 1)).toEqual(['2026-10-22'])
    expect(ventana('2026-10-22', 7)).toEqual(['2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27', '2026-10-28'])
    const quince = ventana('2026-10-31', 15)
    expect(quince).toHaveLength(15)
    expect(quince[0]).toBe('2026-10-31')
    expect(quince[14]).toBe('2026-11-14')
  })

  it('arma la query con el rango de la ventana; sin estados pide todos', () => {
    const f = { fecha: '2026-10-22', dias: 7 as const, estados: [...ESTADOS_CALENDARIO], empresa: 2, ruta: null, bus: null }
    const q = new URLSearchParams(aQueryCalendario(f))
    expect(q.get('desde')).toBe('2026-10-22')
    expect(q.get('hasta')).toBe('2026-10-28')
    expect(q.get('empresa')).toBe('2')
    expect(q.get('estado')).toBe('programada,abordando,iniciada,finalizada')
    expect(new URLSearchParams(aQueryCalendario({ ...f, estados: [] })).get('estado')).toBe('')
  })

  it('por defecto deja fuera las anuladas', () => {
    expect(ESTADOS_CALENDARIO).not.toContain('cancelada')
  })

  it('muestra asientos o porciento; sin capacidad, los asientos', () => {
    expect(textoOcupacion({ vendidos: 23, capacidad: 46 }, 'asientos')).toBe('23')
    expect(textoOcupacion({ vendidos: 23, capacidad: 46 }, 'porciento')).toBe('50%')
    expect(textoOcupacion({ vendidos: 5, capacidad: null }, 'porciento')).toBe('5')
    expect(nivel({ vendidos: 46, capacidad: 46 })).toBe('llena')
    expect(nivel({ vendidos: 40, capacidad: 46 })).toBe('alta')
    expect(nivel({ vendidos: 5, capacidad: 46 })).toBe('vacia')
    expect(nivel({ vendidos: 5, capacidad: null })).toBeNull()
  })

  it('reparte por día y hora local, ordenadas por minuto y ruta', () => {
    const local = (d: number, h: number, min: number) => new Date(2026, 9, d, h, min).toISOString()
    const m = matriz(
      [salida(3, local(22, 6, 30), { rutaId: 2 }), salida(1, local(22, 6, 30), { rutaId: 1 }), salida(2, local(22, 6, 5)), salida(5, local(23, 0, 15)), salida(4, local(30, 6, 0))],
      ['2026-10-22', '2026-10-23'],
    )
    expect([...m.keys()]).toEqual(['2026-10-22', '2026-10-23'])
    expect(m.get('2026-10-22')![6]!.map((s) => s.id)).toEqual([2, 1, 3])
    expect(m.get('2026-10-23')![0]!.map((s) => s.id)).toEqual([5])
    // Fuera de la ventana no aparece.
    expect([...m.values()].flat(2).some((s) => s.id === 4)).toBe(false)
  })

  it('totaliza sin contar la capacidad de las anuladas', () => {
    expect(totales([salida(1, '2026-10-22T06:00:00-06:00', { vendidos: 10 }), salida(2, '2026-10-22T07:00:00-06:00', { vendidos: 0, estado: 'cancelada' })])).toEqual({
      salidas: 2,
      vendidos: 10,
      capacidad: 40,
    })
  })

  it('la leyenda trae solo las rutas con salidas, de más a menos', () => {
    const l = leyenda([salida(1, 'x', { rutaId: 2 }), salida(2, 'x', { rutaId: 2 }), salida(3, 'x', { rutaId: 1 })], [ruta(1, 'A'), ruta(2, 'B'), ruta(3, 'C')])
    expect(l.map((e) => [e.ruta.id, e.salidas])).toEqual([
      [2, 2],
      [1, 1],
    ])
  })

  it('el tono de una ruta es estable y separa ids vecinos', () => {
    expect(tonoRuta(5)).toBe(tonoRuta(5))
    expect(Math.abs(tonoRuta(1) - tonoRuta(2))).toBeGreaterThan(90)
  })

  it('lee la hora local de una salida como HH:MM', () => {
    expect(horaMinuto(new Date(2026, 9, 22, 6, 30).toISOString())).toBe('06:30')
    expect(horaMinuto(new Date(2026, 9, 22, 23, 5).toISOString())).toBe('23:05')
  })

  it('ubica el ahora', () => {
    expect(ahoraEn(new Date(2026, 9, 22, 14, 30))).toEqual({ dia: '2026-10-22', hora: 14, fraccion: 0.5 })
  })
})
