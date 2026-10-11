import { describe, expect, it } from 'vitest'
import { avisos, duracion, errores, formDe, formVacio, mover, nuevaParada, payload } from '../editor'
import type { RutaDetalle } from '../types'

const detalle: RutaDetalle = {
  id: 1,
  nombre: 'Guatemala - Flores',
  kilometros: 480,
  activo: true,
  codigo: 'RUT001',
  paradas: [
    { enclaveId: 1, nombre: 'Guatemala', minutos: null, km: null, permiteSubir: true, permiteBajar: true, tiempos: [] },
    { enclaveId: 12, nombre: 'Río Dulce', minutos: 300, km: 270, permiteSubir: true, permiteBajar: false, tiempos: [{ claseId: 1, minutos: 360 }] },
    { enclaveId: 3, nombre: 'Flores', minutos: 600, km: 480, permiteSubir: true, permiteBajar: true, tiempos: [] },
  ],
  salidasFuturas: 4,
  salidasConBoletos: 1,
  avisos: {},
}

describe('editor de rutas', () => {
  it('arma el formulario y el payload de vuelta', () => {
    const f = formDe(detalle)
    expect(new Set(f.paradas.map((p) => p.key)).size).toBe(3)
    expect(payload(f)).toEqual({
      nombre: 'Guatemala - Flores',
      kilometros: 480,
      activo: true,
      paradas: [
        { enclaveId: 1, minutos: null, km: null, permiteSubir: true, permiteBajar: true, tiempos: [] },
        { enclaveId: 12, minutos: 300, km: 270, permiteSubir: true, permiteBajar: false, tiempos: [{ claseId: 1, minutos: 360 }] },
        { enclaveId: 3, minutos: 600, km: 480, permiteSubir: true, permiteBajar: true, tiempos: [] },
      ],
    })
  })

  it('el origen nunca lleva minutos ni tiempos', () => {
    const f = formDe(detalle)
    f.paradas[0]!.minutos = 15
    f.paradas[0]!.tiempos = [{ claseId: 1, minutos: 20 }]
    expect(payload(f).paradas[0]).toMatchObject({ minutos: null, tiempos: [] })
  })

  it('mueve paradas sin salirse de la lista', () => {
    expect(mover(['a', 'b', 'c'], 2, -1)).toEqual(['a', 'c', 'b'])
    expect(mover(['a', 'b', 'c'], 0, -1)).toEqual(['a', 'b', 'c'])
    expect(mover(['a', 'b', 'c'], 0, 5)).toEqual(['b', 'c', 'a'])
  })

  it('impide guardar sin nombre, sin lugar o con un lugar repetido', () => {
    expect(errores(formVacio())).toEqual(['Escriba el nombre de la ruta.', 'Elija el lugar de la parada 1.', 'Elija el lugar de la parada 2.'])
    const f = formDe(detalle)
    f.paradas.push(nuevaParada(12))
    expect(errores(f)).toEqual(['Un lugar aparece dos veces (paradas 2 y 4).'])
  })

  it('avisa (sin impedir) los minutos que retroceden', () => {
    expect(avisos([{ minutos: null }, { minutos: 350 }, { minutos: 300 }, { minutos: 600 }])).toEqual({ 2: expect.stringContaining('300 min') })
    expect(errores(formDe(detalle))).toEqual([])
  })

  it('formatea la duración', () => {
    expect(duracion(290)).toBe('4 h 50 min')
    expect(duracion(120)).toBe('2 h')
    expect(duracion(45)).toBe('45 min')
    expect(duracion(null)).toBe('—')
  })
})
