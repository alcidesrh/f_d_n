import { describe, expect, it } from 'vitest'
import { agrupar, duracion, esError, hora, lineas, milisegundos, resumir, titulo } from '../formato'
import type { EntradaActividad, EntradaNegocio } from '../types'

const actividad = (p: Partial<EntradaActividad>): EntradaActividad => ({
  origen: 'actividad',
  tipo: 'peticion',
  fecha: '2026-11-05T09:15:02.120-06:00',
  accion: null,
  metodo: 'GET',
  ruta: '/api/salidas',
  estado: 200,
  duracionMs: 40,
  ip: '10.0.0.1',
  agente: null,
  entidad: 'salidas',
  registroId: null,
  detalle: null,
  requestId: null,
  ...p,
})

const negocio = (p: Partial<EntradaNegocio>): EntradaNegocio => ({
  origen: 'negocio',
  tipo: 'negocio',
  fecha: '2026-11-05T09:15:02.300-06:00',
  accion: 'modificado',
  etiqueta: 'Modificado',
  entidad: 'cambio',
  registroId: 7,
  detalle: null,
  requestId: null,
  ...p,
})

describe('formato de la bitácora diaria', () => {
  it('dice las duraciones y la hora', () => {
    expect(duracion(85)).toBe('1 h 25 min')
    expect(duracion(40)).toBe('40 min')
    expect(duracion(120)).toBe('2 h')
    expect(milisegundos(850)).toBe('850 ms')
    expect(milisegundos(190_000)).toBe('3 min 10 s')
    expect(hora('2026-11-05T09:15:02.120-06:00')).toBe('09:15:02')
  })

  it('titula peticiones REST y GraphQL', () => {
    expect(titulo(actividad({ metodo: 'PATCH', entidad: 'buses', registroId: 12 }))).toBe('Modificó buses #12')
    expect(titulo(actividad({ accion: 'mutation createBus', metodo: 'POST' }))).toBe('Ejecutó createBus')
    expect(titulo(actividad({ accion: 'query Salidas', metodo: 'POST' }))).toBe('Consultó Salidas')
  })

  it('titula sesiones, pantallas y sondeos', () => {
    expect(titulo(actividad({ tipo: 'sesion', accion: 'inicio' }))).toBe('Inició sesión')
    expect(titulo(actividad({ tipo: 'pantalla', accion: 'navegar', detalle: { titulo: 'Venta' } }))).toBe('Abrió Venta')
    expect(titulo(actividad({ tipo: 'sondeo', accion: 'consultas', detalle: { cantidad: 30 } }))).toBe('Consultas automáticas (30)')
  })

  it('describe un cambio de entidad campo a campo', () => {
    const e = negocio({ detalle: { clase: 'Bus', id: 7, etiqueta: 'P-123', cambios: { placa: { de: 'P-12', a: 'P-123' } } } })
    expect(titulo(e)).toBe('Modificó Bus: P-123')
    expect(lineas(e)).toEqual(['placa: P-12 → P-123'])
  })

  it('muestra filtros, recorte y errores', () => {
    const e = actividad({ estado: 422, detalle: { consulta: { page: 2 }, recortado: true, bytes: 9000 } })
    expect(lineas(e)).toEqual(['GET /api/salidas', 'Filtros: page=2', 'Detalle recortado (9000 bytes)'])
    expect(esError(e)).toBe(true)
    expect(esError(actividad({ tipo: 'sesion', accion: 'fallo', estado: null }))).toBe(true)
    expect(esError(actividad({}))).toBe(false)
  })

  it('acota el resumen de un objeto', () => {
    expect(resumir({ a: 1, b: { c: 2 } })).toBe('a=1, b={"c":2}')
    expect(resumir({ a: 'x'.repeat(50) }, 10)).toHaveLength(10)
  })

  it('junta los cambios de negocio con su petición', () => {
    const peticion = actividad({ metodo: 'PATCH', requestId: 'r1' })
    const cambio = negocio({ requestId: 'r1' })
    const suelto = negocio({ requestId: 'r2' })
    const grupos = agrupar([peticion, cambio, actividad({}), suelto])
    expect(grupos).toHaveLength(3)
    expect(grupos[0]!.principal).toBe(peticion)
    expect(grupos[0]!.efectos).toEqual([cambio])
    expect(grupos[2]!.principal).toBe(suelto)
  })
})

describe('fechas del selector', () => {
  it('ida y vuelta AAAA-MM-DD', async () => {
    const { diaIso, desdeDiaIso } = await import('../formato')
    expect(diaIso(new Date(2026, 0, 5))).toBe('2026-01-05')
    expect(diaIso(desdeDiaIso('2026-11-04')!)).toBe('2026-11-04')
    expect(desdeDiaIso('ayer')).toBeNull()
  })
})
