import { describe, expect, it } from 'vitest'
import {
  buildCatalog,
  loadIconCatalog,
  resolveVariant,
  searchIcons,
  splitVariant,
  UNCATEGORIZED,
} from '@/shared/icons/iconCatalog'

const meta = {
  version: '0.0.0',
  categories: ['Transit', 'UI Actions'],
  icons: {
    'directions-bus': [0, 'vehicle journey passenger public transport'] as [number, string],
    'bus-alert': [0, 'warning transport'] as [number, string],
    'airport-shuttle': [0, 'transport travel'] as [number, string],
    settings: [1, 'cog gear preferences'] as [number, string],
  },
}

const names = [
  'settings',
  'settings-outline',
  'directions-bus',
  'directions-bus-outline',
  'directions-bus-rounded',
  'bus-alert',
  'airport-shuttle',
  'brand-x',
]
const catalog = buildCatalog(names, meta)
const found = (opts: Parameters<typeof searchIcons>[1]) =>
  searchIcons(catalog.icons, opts).map((i) => i.name)

describe('splitVariant', () => {
  const exists = (name: string) => names.includes(name)

  it('separa el sufijo de estilo solo si el símbolo base existe', () => {
    expect(splitVariant('directions-bus-outline', exists)).toEqual({
      base: 'directions-bus',
      style: 'outline',
    })
    expect(splitVariant('directions-bus', exists)).toEqual({ base: 'directions-bus', style: 'regular' })
    expect(splitVariant('x-outline', exists)).toEqual({ base: 'x-outline', style: 'regular' })
  })
})

describe('buildCatalog', () => {
  it('agrupa las variantes bajo el símbolo base y ordena por nombre', () => {
    expect(catalog.icons.map((i) => i.name)).toEqual([
      'airport-shuttle',
      'brand-x',
      'bus-alert',
      'directions-bus',
      'settings',
    ])
    const bus = catalog.icons.find((i) => i.name === 'directions-bus')
    expect(bus?.variants).toEqual({
      regular: 'directions-bus',
      outline: 'directions-bus-outline',
      rounded: 'directions-bus-rounded',
    })
    expect(bus?.haystack).toContain('journey')
  })

  it('agrupa sin metadata en "Otros" y ordena categorías por etiqueta en español', () => {
    expect(catalog.icons.find((i) => i.name === 'brand-x')?.category).toBe(UNCATEGORIZED)
    expect(catalog.categories).toEqual(['UI Actions', 'Otros', 'Transit'])
  })
})

describe('resolveVariant', () => {
  const bus = catalog.icons.find((i) => i.name === 'directions-bus')!
  const alert = catalog.icons.find((i) => i.name === 'bus-alert')!

  it('devuelve la variante pedida o el relleno si el símbolo no la tiene', () => {
    expect(resolveVariant(bus, 'outline')).toBe('directions-bus-outline')
    expect(resolveVariant(alert, 'outline')).toBe('bus-alert')
    expect(resolveVariant(bus, 'sharp')).toBe('directions-bus')
  })
})

describe('searchIcons', () => {
  it('prioriza nombre exacto > prefijo > contiene > tags', () => {
    expect(found({ query: 'bus' })).toEqual(['bus-alert', 'directions-bus'])
    expect(found({ query: 'gear' })).toEqual(['settings'])
  })

  it('busca por categoría en inglés o español y exige todas las palabras', () => {
    expect(found({ query: 'transporte' })).toEqual(['airport-shuttle', 'bus-alert', 'directions-bus'])
    expect(found({ query: 'transit' })).toEqual(['airport-shuttle', 'bus-alert', 'directions-bus'])
    expect(found({ query: 'public transport' })).toEqual(['directions-bus'])
  })

  it('filtra por categoría', () => {
    expect(found({ category: 'UI Actions' })).toEqual(['settings'])
  })
})

describe('loadIconCatalog', () => {
  it('cruza el set real de Iconify con la metadata generada', async () => {
    const real = await loadIconCatalog()
    expect(real.icons.length).toBeGreaterThan(3000)
    expect(real.categories).toContain('Transit')
    const uncategorized = real.icons.filter((i) => i.category === UNCATEGORIZED).length
    // Quedan en "Otros" solo los símbolos que Iconify no categoriza (android-*, auto-*…).
    expect(uncategorized / real.icons.length).toBeLessThan(0.1)
    const home = searchIcons(real.icons, { query: 'home' })[0]
    expect(home?.name).toBe('home')
    expect(home?.variants.outline).toBe('home-outline')
  }, 60_000)
})
