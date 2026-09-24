/**
 * Catálogo de íconos Tabler para el buscador (`IconPicker`).
 *
 * Carga diferida (dos chunks aparte, solo cuando se abre el buscador):
 *  - `@iconify-json/tabler`: SVGs. Se registran con `addCollection`
 *    para que `<icon>` los pinte sin pedirlos a api.iconify.design.
 *  - `./tabler-meta.json`: categoría + tags (ver `scripts/gen-tabler-meta.ts`).
 *
 * Los nombres del catálogo son los de Iconify sin prefijo (`bus`, `bus-filled`),
 * el mismo formato que persiste `Icon.icon` y que recibe `<icon name>`.
 */
import { addCollection } from '@iconify/vue'

export type TablerIconStyle = 'outline' | 'filled'

export interface TablerIconEntry {
  name: string
  category: string
  /** Texto de búsqueda en minúsculas: nombre, tags y categoría (en y es). */
  haystack: string
  style: TablerIconStyle
}

export interface TablerCatalog {
  version: string
  /** Categorías en inglés (clave de Tabler), ordenadas por su etiqueta. */
  categories: string[]
  icons: TablerIconEntry[]
}

interface TablerMetaFile {
  version: string
  categories: string[]
  icons: Record<string, [number, string]>
}

const FILLED_SUFFIX = '-filled'
export const UNCATEGORIZED = 'Otros'

/** Etiquetas en español de las categorías de Tabler. */
export const CATEGORY_LABELS: Record<string, string> = {
  Animals: 'Animales',
  Arrows: 'Flechas',
  Badges: 'Insignias',
  Brand: 'Marcas',
  Buildings: 'Edificios',
  Charts: 'Gráficos',
  Communication: 'Comunicación',
  Computers: 'Computadoras',
  Currencies: 'Monedas',
  Database: 'Base de datos',
  Design: 'Diseño',
  Development: 'Desarrollo',
  Devices: 'Dispositivos',
  Document: 'Documentos',
  'E-commerce': 'Comercio',
  Electrical: 'Electricidad',
  Extensions: 'Extensiones',
  Food: 'Comida',
  Games: 'Juegos',
  Gender: 'Género',
  Gestures: 'Gestos',
  Health: 'Salud',
  Laundry: 'Lavandería',
  Letters: 'Letras',
  Logic: 'Lógica',
  Map: 'Mapa',
  Math: 'Matemáticas',
  Media: 'Multimedia',
  Mood: 'Estados de ánimo',
  Nature: 'Naturaleza',
  Numbers: 'Números',
  Photography: 'Fotografía',
  Shapes: 'Formas',
  Sport: 'Deportes',
  Symbols: 'Símbolos',
  System: 'Sistema',
  Text: 'Texto',
  'Version control': 'Control de versiones',
  Vehicles: 'Vehículos',
  Weather: 'Clima',
  Zodiac: 'Zodiaco',
}

export function categoryLabel(category: string): string {
  return CATEGORY_LABELS[category] ?? category
}

/** Construye el catálogo cruzando los nombres del set Iconify con la metadata de Tabler. */
export function buildCatalog(iconNames: string[], meta: TablerMetaFile): TablerCatalog {
  const used = new Set<string>()
  const icons = iconNames
    .slice()
    .sort((a, b) => a.localeCompare(b))
    .map((name): TablerIconEntry => {
      const style: TablerIconStyle = name.endsWith(FILLED_SUFFIX) ? 'filled' : 'outline'
      // Las variantes `-filled` heredan la metadata de su versión outline.
      const base = style === 'filled' ? name.slice(0, -FILLED_SUFFIX.length) : name
      const [categoryIndex, tags] = meta.icons[name] ?? meta.icons[base] ?? [-1, '']
      const category = meta.categories[categoryIndex] ?? UNCATEGORIZED
      used.add(category)
      const haystack = [name.replace(/-/g, ' '), tags, category, categoryLabel(category)]
        .join(' ')
        .toLowerCase()
      return { name, category, haystack, style }
    })
  const categories = [...used].sort((a, b) => categoryLabel(a).localeCompare(categoryLabel(b)))
  return { version: meta.version, categories, icons }
}

export interface IconSearchOptions {
  query?: string
  category?: string | null
  style?: TablerIconStyle | null
}

/**
 * Filtra y ordena por relevancia: nombre exacto > nombre empieza por >
 * nombre contiene > coincidencia en tags/categoría. Con varias palabras, todas
 * deben aparecer en el texto de búsqueda.
 */
export function searchIcons(icons: TablerIconEntry[], opts: IconSearchOptions = {}): TablerIconEntry[] {
  const query = (opts.query ?? '').trim().toLowerCase()
  const slug = query.replace(/\s+/g, '-')
  const words = query.split(/\s+/).filter(Boolean)

  const filtered = icons.filter(
    (icon) =>
      (!opts.category || icon.category === opts.category) &&
      (!opts.style || icon.style === opts.style) &&
      words.every((word) => icon.haystack.includes(word)),
  )
  if (!slug) return filtered

  const rank = (icon: TablerIconEntry) => {
    if (icon.name === slug) return 0
    if (icon.name.startsWith(slug)) return 1
    if (icon.name.includes(slug)) return 2
    return 3
  }
  // `sort` es estable: dentro de cada rango se conserva el orden alfabético.
  return filtered
    .map((icon) => ({ icon, rank: rank(icon) }))
    .sort((a, b) => a.rank - b.rank)
    .map(({ icon }) => icon)
}

let catalogPromise: Promise<TablerCatalog> | null = null

/** Carga (una sola vez) el set de íconos y su metadata. */
export function loadTablerCatalog(): Promise<TablerCatalog> {
  catalogPromise ??= Promise.all([
    import('@iconify-json/tabler'),
    import('./tabler-meta.json'),
  ])
    .then(([{ icons: set }, meta]) => {
      addCollection(set)
      return buildCatalog(Object.keys(set.icons), meta.default as unknown as TablerMetaFile)
    })
    .catch((error: unknown) => {
      catalogPromise = null
      throw error
    })
  return catalogPromise
}
