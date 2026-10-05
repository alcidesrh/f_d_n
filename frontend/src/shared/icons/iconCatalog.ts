/**
 * Catálogo de Google Material Symbols para el buscador (`IconPicker`).
 *
 * Carga diferida (dos chunks aparte, solo cuando se abre el buscador):
 *  - `@iconify-json/material-symbols`: SVGs. Se registran con `addCollection`
 *    para que `<icon>` los pinte sin pedirlos a api.iconify.design.
 *  - `./iconMeta.ts`: categoría + tags (ver `scripts/gen-icon-meta.ts`).
 *
 * Cada símbolo del catálogo es un nombre base (`home`) con sus variantes de
 * estilo (`home-outline`, `home-rounded`…). Los nombres son los de Iconify sin
 * prefijo, el mismo formato que persiste `Icon.icon` y que recibe `<icon name>`.
 */
import { addCollection } from '@iconify/vue'

/** Estilos de Material Symbols; `regular` es el relleno por defecto del set. */
export type IconStyle =
  | 'regular'
  | 'outline'
  | 'rounded'
  | 'outline-rounded'
  | 'sharp'
  | 'outline-sharp'

export interface IconEntry {
  /** Nombre base del símbolo (`home`). */
  name: string
  category: string
  /** Texto de búsqueda en minúsculas: nombre, tags y categoría (en y es). */
  haystack: string
  /** Nombre Iconify de cada variante que existe (`{ regular: 'home', outline: 'home-outline' }`). */
  variants: Partial<Record<IconStyle, string>>
}

export interface IconCatalog {
  version: string
  /** Categorías en inglés (clave de Iconify), ordenadas por su etiqueta. */
  categories: string[]
  icons: IconEntry[]
}

export interface IconMetaFile {
  version: string
  categories: string[]
  icons: Record<string, [number, string]>
}

export const UNCATEGORIZED = 'Otros'

/** Sufijo de nombre de cada estilo (el orden importa: el más largo primero). */
const STYLE_SUFFIXES: Array<[IconStyle, string]> = [
  ['outline-rounded', '-outline-rounded'],
  ['outline-sharp', '-outline-sharp'],
  ['rounded', '-rounded'],
  ['sharp', '-sharp'],
  ['outline', '-outline'],
]

export const STYLE_LABELS: Record<IconStyle, string> = {
  regular: 'Relleno',
  outline: 'Contorno',
  rounded: 'Relleno redondeado',
  'outline-rounded': 'Contorno redondeado',
  sharp: 'Relleno afilado',
  'outline-sharp': 'Contorno afilado',
}

/** Etiquetas en español de las categorías de Material Symbols. */
export const CATEGORY_LABELS: Record<string, string> = {
  Actions: 'Acciones',
  Activities: 'Actividades',
  Android: 'Android',
  'Audio & Video': 'Audio y video',
  Brand: 'Marcas',
  Business: 'Negocios',
  Communicate: 'Comunicación',
  Hardware: 'Hardware',
  Home: 'Hogar',
  Household: 'Electrodomésticos',
  Images: 'Imágenes',
  Maps: 'Mapas',
  Privacy: 'Privacidad',
  Social: 'Social',
  Text: 'Texto',
  Transit: 'Transporte',
  Travel: 'Viajes',
  'UI Actions': 'Interfaz',
}

export function categoryLabel(category: string): string {
  return CATEGORY_LABELS[category] ?? category
}

/** Separa un nombre en símbolo base + estilo (`home-outline` → `home`, `outline`); sin sufijo es `regular`. */
export function splitVariant(
  name: string,
  exists: (candidate: string) => boolean,
): { base: string; style: IconStyle } {
  for (const [style, suffix] of STYLE_SUFFIXES) {
    if (name.endsWith(suffix) && exists(name.slice(0, -suffix.length))) {
      return { base: name.slice(0, -suffix.length), style }
    }
  }
  return { base: name, style: 'regular' }
}

/** Nombre del ícono para un estilo; si el símbolo no lo tiene, el relleno o la primera variante. */
export function resolveVariant(entry: IconEntry, style: IconStyle | null): string {
  return (
    (style && entry.variants[style]) ||
    entry.variants.regular ||
    Object.values(entry.variants)[0] ||
    entry.name
  )
}

/** Construye el catálogo cruzando los nombres del set Iconify con la metadata (categoría + tags). */
export function buildCatalog(iconNames: string[], meta: IconMetaFile): IconCatalog {
  const available = new Set(iconNames)
  const exists = (candidate: string) => available.has(candidate)
  const bases = new Map<string, IconEntry['variants']>()
  for (const name of iconNames) {
    const { base, style } = splitVariant(name, exists)
    const variants = bases.get(base) ?? bases.set(base, {}).get(base)!
    variants[style] = name
  }

  const used = new Set<string>()
  const icons = [...bases]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([name, variants]): IconEntry => {
      const [categoryIndex, tags] = meta.icons[name] ?? [-1, '']
      const category = meta.categories[categoryIndex] ?? UNCATEGORIZED
      used.add(category)
      const haystack = [name.replace(/-/g, ' '), tags, category, categoryLabel(category)]
        .join(' ')
        .toLowerCase()
      return { name, category, haystack, variants }
    })
  const categories = [...used].sort((a, b) => categoryLabel(a).localeCompare(categoryLabel(b)))
  return { version: meta.version, categories, icons }
}

export interface IconSearchOptions {
  query?: string
  category?: string | null
}

/**
 * Filtra y ordena por relevancia: nombre exacto > nombre empieza por >
 * nombre contiene > coincidencia en tags/categoría. Con varias palabras, todas
 * deben aparecer en el texto de búsqueda.
 */
export function searchIcons(icons: IconEntry[], opts: IconSearchOptions = {}): IconEntry[] {
  const query = (opts.query ?? '').trim().toLowerCase()
  const slug = query.replace(/\s+/g, '-')
  const words = query.split(/\s+/).filter(Boolean)

  const filtered = icons.filter(
    (icon) =>
      (!opts.category || icon.category === opts.category) &&
      words.every((word) => icon.haystack.includes(word)),
  )
  if (!slug) return filtered

  const rank = (icon: IconEntry) => {
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

let catalogPromise: Promise<IconCatalog> | null = null

/** Carga (una sola vez) el set de íconos y su metadata. */
export function loadIconCatalog(): Promise<IconCatalog> {
  catalogPromise ??= Promise.all([import('@iconify-json/material-symbols'), import('./iconMeta')])
    .then(([{ icons: set }, meta]) => {
      addCollection(set)
      return buildCatalog(Object.keys(set.icons), meta.default)
    })
    .catch((error: unknown) => {
      catalogPromise = null
      throw error
    })
  return catalogPromise
}
