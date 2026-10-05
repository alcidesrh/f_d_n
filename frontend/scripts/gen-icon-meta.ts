/**
 * Genera `src/shared/icons/iconMeta.ts`: categoría + tags de cada símbolo de
 * Google Material Symbols, para el buscador de `IconPicker`.
 *
 * - Categorías: `metadata.json` de `@iconify-json/material-symbols` (viene en
 *   el paquete, 18 categorías ya agrupadas; incluye todas las variantes).
 * - Tags: el catálogo público de Google Fonts
 *   (`fonts.google.com/metadata/icons`), que Iconify no trae.
 *
 * Se compacta a `{ version, categories[], icons: { símbolo: [índiceCategoría, "tags"] } }`
 * con una entrada por símbolo base (sin sufijos de variante: `home`, no
 * `home-outline-rounded`). Los nombres son los de Iconify (kebab-case).
 *
 * Es un módulo `.ts` (no `.json`) a propósito: el Caddyfile del backend no
 * proxea al dev server de Vite las rutas `*.json*`, y un `import('x.json')`
 * terminaría en Symfony (404). `JSON.parse` de un string además evita que
 * TypeScript infiera el tipo de un literal con miles de claves.
 *
 * Uso: `npm run icons:meta` (re-ejecutar tras actualizar `@iconify-json/material-symbols`).
 */
import { readFile, writeFile } from 'node:fs/promises'

const root = new URL('../', import.meta.url)
const pkgDir = new URL('node_modules/@iconify-json/material-symbols/', root)
const readJson = async <T>(file: string) => JSON.parse(await readFile(new URL(file, pkgDir), 'utf8')) as T

const { version } = await readJson<{ version: string }>('package.json')
const { icons: iconSet } = await readJson<{ icons: Record<string, unknown> }>('icons.json')
const { categories: byCategory } = await readJson<{ categories: Record<string, string[]> }>('metadata.json')

/** Mismo criterio que `splitVariant` de `iconCatalog.ts`. */
const SUFFIXES = ['-outline-rounded', '-outline-sharp', '-rounded', '-sharp', '-outline']
function baseOf(name: string): string {
  const suffix = SUFFIXES.find((s) => name.endsWith(s) && name.slice(0, -s.length) in iconSet)
  return suffix ? name.slice(0, -suffix.length) : name
}

const categories = Object.keys(byCategory).sort()
const icons: Record<string, [number, string]> = {}
for (const [index, category] of categories.entries()) {
  for (const name of byCategory[category]) {
    const base = baseOf(name)
    if (base in iconSet && !(base in icons)) icons[base] = [index, '']
  }
}

const url = 'https://fonts.google.com/metadata/icons?key=material_symbols&incomplete=true'
const response = await fetch(url)
if (!response.ok) throw new Error(`GET ${url} → ${response.status}`)
const upstream = JSON.parse((await response.text()).replace(/^\)\]\}'/, '')) as {
  icons: Array<{ name: string; tags?: string[] }>
}

/** Tags que describen el trazo o la maqueta y no el significado del símbolo. */
const NOISE = new Set([
  'filled', 'outline', 'outlined', 'solid', 'stroke', 'rounded', 'sharp', 'rounded corners',
  'shape', 'geometry', 'components', 'interface', 'ui', 'ux', 'app', 'application', 'screen',
  'web', 'website', 'window', 'layout', 'button', 'design', 'material', 'symbol', 'icon',
])

let tagged = 0
for (const { name, tags } of upstream.icons) {
  const entry = icons[name.replace(/_/g, '-')]
  if (!entry || !tags?.length) continue
  const own = new Set(name.split('_'))
  entry[1] = [...new Set(tags.map((tag) => tag.toLowerCase().replace(/--/g, ' ')))]
    .filter((tag) => !NOISE.has(tag) && !own.has(tag))
    .join(' ')
  tagged++
}

const json = JSON.stringify({ version, categories, icons })
const source = `// Generado por scripts/gen-icon-meta.ts (\`npm run icons:meta\`) — no editar.
import type { IconMetaFile } from './iconCatalog'

const meta: IconMetaFile = JSON.parse(${JSON.stringify(json)})

export default meta
`
await writeFile(new URL('src/shared/icons/iconMeta.ts', root), source)
console.log(
  `iconMeta.ts: ${Object.keys(icons).length} símbolos (${tagged} con tags), ${categories.length} categorías (v${version})`,
)
