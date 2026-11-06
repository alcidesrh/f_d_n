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

describe('salidas sin traer', () => {
  const sinTraer = {
    faltan: 3,
    conBoletos: [
      { id: 8620178, fecha: '2026-11-06 07:00:00', boletos: 21, motivo: 'salida_sin_bus_inferible' },
      { id: 8620182, fecha: '2026-11-06 10:30:00', boletos: 10, motivo: null },
    ],
  }

  it('las que tienen boletos piden atención y dicen la próxima', () => {
    expect(nivel({ ...base, salidasSinTraer: sinTraer }, ahora)).toBe('atencion')
    expect(avisos({ ...base, salidasSinTraer: sinTraer }, ahora)).toEqual([
      '2 salidas con boletos del sistema anterior sin traer; la próxima es 8620178, del 2026-11-06 07:00:00.',
    ])
  })

  it('las que no tienen boletos no avisan', () => {
    const sinBoletos = { faltan: 3, conBoletos: [] }
    expect(nivel({ ...base, salidasSinTraer: sinBoletos }, ahora)).toBe('bien')
    expect(avisos({ ...base, salidasSinTraer: null }, ahora)).toEqual([])
  })
})

describe('solo trae', () => {
  it('lo avisa en el detalle', () => {
    expect(avisos({ ...base, soloTraer: true }, ahora)).toEqual([
      expect.stringContaining('Solo trae'),
    ])
  })
})
