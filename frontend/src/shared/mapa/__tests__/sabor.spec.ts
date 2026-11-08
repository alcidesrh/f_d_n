import { describe, expect, it } from 'vitest'
import { layers } from '@protomaps/basemaps'
import { SABOR_OSM, colorearVias, filtroOscuro, leerColor, oscurecer } from '../sabor'

describe('leerColor', () => {
  it('lee hex corto, largo y con alfa, rgb() y rgba()', () => {
    expect(leerColor('#fff')).toEqual([255, 255, 255, 1])
    expect(leerColor('#f2efe9')).toEqual([242, 239, 233, 1])
    expect(leerColor('#00000080')).toEqual([0, 0, 0, 128 / 255])
    expect(leerColor('rgba(210, 239, 207, 0.5)')).toEqual([210, 239, 207, 0.5])
    expect(leerColor('rgb(1,2,3)')).toEqual([1, 2, 3, 1])
    expect(leerColor('kind_detail')).toBeNull()
  })
})

describe('filtroOscuro', () => {
  it('el blanco queda casi negro y el negro casi blanco, como el filtro de Leaflet', () => {
    expect(filtroOscuro('#ffffff')).toBe('rgba(13, 13, 13, 1)')
    expect(filtroOscuro('#000000')).toBe('rgba(231, 231, 231, 1)')
  })
  it('conserva el tono: el agua sigue azulada y la transparencia igual', () => {
    const [r, g, b] = leerColor(filtroOscuro(SABOR_OSM.water))!
    expect(b).toBeGreaterThan(r)
    expect(r + g + b).toBeLessThan(3 * 128)
    expect(filtroOscuro('rgba(255, 255, 255, 0.4)')).toMatch(/, 0\.4\)$/)
  })
  it('deja igual lo que no es un color', () => {
    expect(filtroOscuro('match')).toBe('match')
  })
})

describe('colorearVias', () => {
  const capas = colorearVias(layers('base', SABOR_OSM, { lang: 'es' }))
  const pintura = (id: string) => capas.find((c) => c.id === id)!.paint as Record<string, unknown>
  it('las vías principales toman el color de su categoría de OpenStreetMap', () => {
    const color = JSON.stringify(pintura('roads_major')['line-color'])
    expect(color).toContain('"kind_detail"')
    expect(color).toContain('"trunk","#f9b29c"')
    expect(color).toContain('"primary","#fcd6a4"')
    expect(JSON.stringify(pintura('roads_major_casing_early')['line-color'])).toContain('"trunk","#c84e2f"')
    expect(pintura('roads_major_casing_early')['line-gap-width']).toEqual(pintura('roads_major')['line-width'])
  })
  it('no toca las calles', () => {
    expect(pintura('roads_minor')['line-color']).not.toContain('kind_detail')
  })
})

describe('oscurecer', () => {
  it('pasa por el filtro cada color de las capas, también dentro de expresiones', () => {
    const texto = JSON.stringify(oscurecer(colorearVias(layers('base', SABOR_OSM, { lang: 'es' }))).map((c) => c.paint))
    expect(texto).not.toContain(SABOR_OSM.earth)
    expect(texto).not.toContain('#f9b29c')
    expect(texto).toContain('"kind_detail"')
  })
})
