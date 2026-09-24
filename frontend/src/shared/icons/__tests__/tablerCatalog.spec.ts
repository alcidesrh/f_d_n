import { describe, expect, it } from 'vitest'
import { buildCatalog, loadTablerCatalog, searchIcons, UNCATEGORIZED } from '@/shared/icons/tablerCatalog'

const meta = {
  version: '0.0.0',
  categories: ['Vehicles', 'System'],
  icons: {
    bus: [0, 'vehicle drive driver engine motor journey trip'] as [number, string],
    'bus-stop': [0, 'station public transport'] as [number, string],
    'car-bus': [0, 'transport travel'] as [number, string],
    settings: [1, 'cog gear preferences'] as [number, string],
  },
}

const catalog = buildCatalog(['settings', 'bus', 'bus-filled', 'bus-stop', 'car-bus', 'brand-x'], meta)
const names = (opts: Parameters<typeof searchIcons>[1]) => searchIcons(catalog.icons, opts).map((i) => i.name)

describe('buildCatalog', () => {
  it('ordena por nombre y hereda la metadata outline en las variantes -filled', () => {
    expect(catalog.icons.map((i) => i.name)).toEqual(['brand-x', 'bus', 'bus-filled', 'bus-stop', 'car-bus', 'settings'])
    const filled = catalog.icons.find((i) => i.name === 'bus-filled')
    expect(filled).toMatchObject({ category: 'Vehicles', style: 'filled' })
    expect(filled?.haystack).toContain('journey')
  })

  it('agrupa sin metadata en "Otros" y ordena categorías por etiqueta en español', () => {
    expect(catalog.icons.find((i) => i.name === 'brand-x')?.category).toBe(UNCATEGORIZED)
    expect(catalog.categories).toEqual(['Otros', 'System', 'Vehicles'])
  })
})

describe('searchIcons', () => {
  it('prioriza nombre exacto > prefijo > contiene > tags', () => {
    expect(names({ query: 'bus' })).toEqual(['bus', 'bus-filled', 'bus-stop', 'car-bus'])
    expect(names({ query: 'gear' })).toEqual(['settings'])
  })

  it('busca por categoría en inglés o español y exige todas las palabras', () => {
    expect(names({ query: 'vehículos', style: 'outline' })).toEqual(['bus', 'bus-stop', 'car-bus'])
    expect(names({ query: 'system' })).toEqual(['settings'])
    expect(names({ query: 'bus stop' })).toEqual(['bus-stop'])
  })

  it('filtra por categoría y estilo', () => {
    expect(names({ category: 'Vehicles', style: 'filled' })).toEqual(['bus-filled'])
    expect(names({ category: 'System' })).toEqual(['settings'])
  })
})

describe('loadTablerCatalog', () => {
  it('cruza el set real de Iconify con la metadata generada', async () => {
    const real = await loadTablerCatalog()
    expect(real.icons.length).toBeGreaterThan(5000)
    expect(real.categories).toContain('Vehicles')
    const uncategorized = real.icons.filter((i) => i.category === UNCATEGORIZED).length
    // Solo alias/variantes sin contraparte en @tabler/icons deberían quedar fuera.
    expect(uncategorized / real.icons.length).toBeLessThan(0.05)
    expect(searchIcons(real.icons, { query: 'bus' })[0]?.name).toBe('bus')
  }, 30_000)
})
