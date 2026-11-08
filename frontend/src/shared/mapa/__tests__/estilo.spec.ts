import { describe, expect, it } from 'vitest'
import type { StyleSpecification } from 'maplibre-gl'
import { FUENTE_BASE, PREFIJO_BASE, combinarEstilo, esManifiesto, estiloBase } from '../estilo'

const MANIFIESTO = { teselas: 'region-20261101.pmtiles', datos: '2026-11-01' }
const ORIGEN = 'https://fdn.test'

describe('estiloBase', () => {
  it('lee las teselas, fuentes y sprites de /mapas/ con URLs absolutas y en el tema pedido', () => {
    const e = estiloBase(MANIFIESTO, 'dark', ORIGEN)
    expect(e.sources[FUENTE_BASE]).toMatchObject({ type: 'vector', url: 'pmtiles://https://fdn.test/mapas/region-20261101.pmtiles' })
    expect(e.glyphs).toBe('https://fdn.test/mapas/fuentes/{fontstack}/{range}.pbf')
    expect(e.sprite).toBe('https://fdn.test/mapas/sprites/v4/dark')
    expect(e.layers.length).toBeGreaterThan(10)
    expect(e.layers.every((c) => c.id.startsWith(PREFIJO_BASE))).toBe(true)
    // Nombres en español donde OpenStreetMap los tiene.
    expect(JSON.stringify(e.layers)).toContain('name:es')
  })

  it('las fuentes que pide el estilo son las que copia mapas/preparar.sh', () => {
    const familias = new Set<string>()
    for (const c of estiloBase(MANIFIESTO, 'light', ORIGEN).layers) {
      const fuente = (c.layout as Record<string, unknown> | undefined)?.['text-font']
      for (const f of JSON.stringify(fuente ?? []).match(/Noto Sans [A-Za-z ]+/g) ?? []) familias.add(f)
    }
    expect([...familias].sort()).toEqual(['Noto Sans Italic', 'Noto Sans Medium', 'Noto Sans Regular'])
  })

  it('sin manifiesto, un fondo liso: no pide teselas ni fuentes', () => {
    const e = estiloBase(null, 'light', ORIGEN)
    expect(e.sources).toEqual({})
    expect(e.glyphs).toBeUndefined()
    expect(e.layers).toHaveLength(1)
    expect(e.layers[0]).toMatchObject({ id: `${PREFIJO_BASE}fondo`, type: 'background' })
  })
})

describe('combinarEstilo', () => {
  it('cambia el mapa base y conserva encima, en orden, las fuentes y capas de la pantalla', () => {
    const claro = estiloBase(MANIFIESTO, 'light', ORIGEN)
    const anterior: StyleSpecification = {
      ...claro,
      sources: { ...claro.sources, ruta: { type: 'geojson', data: { type: 'FeatureCollection', features: [] } } },
      layers: [...claro.layers, { id: 'ruta-linea', type: 'line', source: 'ruta' }, { id: 'ruta-paradas', type: 'circle', source: 'ruta' }],
    }
    const oscuro = estiloBase(MANIFIESTO, 'dark', ORIGEN)
    const r = combinarEstilo(anterior, oscuro)
    expect(r.sprite).toBe(oscuro.sprite)
    expect(r.sources[FUENTE_BASE]).toEqual(oscuro.sources[FUENTE_BASE])
    expect(r.sources.ruta).toEqual(anterior.sources.ruta)
    expect(r.layers.slice(0, oscuro.layers.length)).toEqual(oscuro.layers)
    expect(r.layers.slice(oscuro.layers.length).map((c) => c.id)).toEqual(['ruta-linea', 'ruta-paradas'])
  })

  it('sin estilo anterior queda el nuevo', () => {
    const nuevo = estiloBase(null, 'dark', ORIGEN)
    expect(combinarEstilo(undefined, nuevo)).toBe(nuevo)
  })
})

describe('esManifiesto', () => {
  it('pide un archivo .pmtiles sin rutas', () => {
    expect(esManifiesto(MANIFIESTO)).toBe(true)
    expect(esManifiesto({ teselas: '../secreto.pmtiles' })).toBe(false)
    expect(esManifiesto({ teselas: 'https://otro.test/x.pmtiles' })).toBe(false)
    expect(esManifiesto({ teselas: 'region.mbtiles' })).toBe(false)
    expect(esManifiesto(null)).toBe(false)
    expect(esManifiesto('<html>')).toBe(false)
  })
})
