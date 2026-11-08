import { describe, expect, it } from 'vitest'
import { aLngLat, dentroDe, limites, linea, lineas, punto } from '../geo'

describe('geo', () => {
  it('voltea [latitud, longitud] a [longitud, latitud]', () => {
    expect(aLngLat([14.6, -90.5])).toEqual([-90.5, 14.6])
    expect(punto([14.6, -90.5], { n: 1 })).toEqual({ type: 'Feature', geometry: { type: 'Point', coordinates: [-90.5, 14.6] }, properties: { n: 1 } })
  })

  it('una línea necesita dos puntos; lineas descarta las que no lo son', () => {
    expect(linea([[14.6, -90.5]])).toBeNull()
    expect(linea([[14.6, -90.5], [15.5, -90.3]])?.geometry.coordinates).toEqual([[-90.5, 14.6], [-90.3, 15.5]])
    expect(lineas([linea([[14.6, -90.5]]), linea([[14.6, -90.5], [15.5, -90.3]], { color: '#f00' })]).features).toHaveLength(1)
  })

  it('la caja de los puntos va de suroeste a noreste; sin puntos no hay caja', () => {
    expect(limites([[14.6, -90.5], [16.9, -89.9], [15.5, -91.2]])).toEqual([[-91.2, 14.6], [-89.9, 16.9]])
    expect(limites([])).toBeNull()
  })

  it('dentroDe recorta la caja por cada lado con el margen', () => {
    const caja: [[number, number], [number, number]] = [[-91, 14], [-89, 16]]
    expect(dentroDe(caja, [15, -90])).toBe(true)
    expect(dentroDe(caja, [14.1, -90])).toBe(true)
    expect(dentroDe(caja, [14.1, -90], 0.15)).toBe(false)
    expect(dentroDe(caja, [17, -90])).toBe(false)
  })
})
