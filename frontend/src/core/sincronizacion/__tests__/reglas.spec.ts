import { describe, expect, it } from 'vitest'
import { avisos, cicloDetenido, nivel, visible, type EstadoSincronizacion } from '../reglas'

const ahora = new Date('2026-10-25T12:00:00Z')
const base: EstadoSincronizacion = {
  activo: true,
  cambiadoEn: null,
  cambiadoPor: null,
  ultimoCicloEn: '2026-10-25T11:59:30Z',
  ultimoCiclo: null,
  ultimoError: null,
  pendientes: 0,
}

describe('indicador de sincronización', () => {
  it('encendida se ve para todos; apagada solo para quien puede encenderla', () => {
    expect(visible(base, false)).toBe(true)
    expect(visible({ ...base, activo: false }, false)).toBe(false)
    expect(visible({ ...base, activo: false }, true)).toBe(true)
    expect(visible(null, true)).toBe(false)
  })

  it('el nivel refleja error, pendientes y ciclo detenido', () => {
    expect(nivel(base, ahora)).toBe('bien')
    expect(nivel({ ...base, activo: false, ultimoError: 'x' }, ahora)).toBe('apagada')
    expect(nivel({ ...base, ultimoError: 'sin conexión' }, ahora)).toBe('error')
    expect(nivel({ ...base, pendientes: 2 }, ahora)).toBe('atencion')
    expect(nivel({ ...base, ultimoCicloEn: '2026-10-25T11:50:00Z' }, ahora)).toBe('atencion')
  })

  it('sin ciclo todavía cuenta como detenido', () => {
    expect(cicloDetenido({ ...base, ultimoCicloEn: null }, ahora)).toBe(true)
  })

  it('los avisos dicen qué pasa', () => {
    expect(avisos(base, ahora)).toEqual([])
    expect(avisos({ ...base, pendientes: 1 }, ahora)).toEqual([
      '1 cambio espera llegar al sistema anterior.',
    ])
    expect(avisos({ ...base, activo: false, pendientes: 3 }, ahora)).toEqual([])
  })
})

describe('solo trae', () => {
  it('lo avisa en el detalle', () => {
    expect(avisos({ ...base, soloTraer: true }, ahora)).toEqual([
      expect.stringContaining('Solo trae'),
    ])
  })
})
