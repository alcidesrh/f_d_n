/**
 * Genera `src/shared/icons/tablerMeta.ts`: categoría + tags de cada ícono de
 * Tabler, para el buscador de `IconPicker`.
 *
 * El set de Iconify (`@iconify-json/tabler`) trae los SVG pero no las
 * categorías ni los tags; esos solo viven en el `icons.json` del paquete
 * oficial `@tabler/icons`. Se descarga la misma versión que declara
 * `@iconify-json/tabler` (`iconSetVersion`) y se compacta a
 * `{ version, categories[], icons: { nombre: [índiceCategoría, "tags"] } }`.
 *
 * Es un módulo `.ts` (no `.json`) a propósito: el Caddyfile del backend no
 * proxea al dev server de Vite las rutas `*.json*`, y un `import('x.json')`
 * terminaría en Symfony (404). `JSON.parse` de un string además evita que
 * TypeScript infiera el tipo de un literal con miles de claves.
 *
 * Uso: `npm run icons:meta` (re-ejecutar tras actualizar `@iconify-json/tabler`).
 */
import { readFile, writeFile } from 'node:fs/promises'

interface TablerIconMeta {
  name: string
  category?: string
  tags?: Array<string | number>
}

const root = new URL('../', import.meta.url)
const pkg = JSON.parse(
  await readFile(new URL('node_modules/@iconify-json/tabler/package.json', root), 'utf8'),
) as { iconSetVersion: string }
const version = pkg.iconSetVersion

const url = `https://cdn.jsdelivr.net/npm/@tabler/icons@${version}/icons.json`
const response = await fetch(url)
if (!response.ok) throw new Error(`GET ${url} → ${response.status}`)
const upstream = (await response.json()) as Record<string, TablerIconMeta>

const categories: string[] = []
const icons: Record<string, [number, string]> = {}
for (const meta of Object.values(upstream).sort((a, b) => a.name.localeCompare(b.name))) {
  const category = meta.category || 'Otros'
  let index = categories.indexOf(category)
  if (index === -1) index = categories.push(category) - 1
  icons[meta.name] = [index, (meta.tags ?? []).map(String).join(' ')]
}

const json = JSON.stringify({ version, categories, icons })
const source = `// Generado por scripts/gen-tabler-meta.ts (\`npm run icons:meta\`) — no editar.
import type { TablerMetaFile } from "./tablerCatalog";

const meta: TablerMetaFile = JSON.parse(${JSON.stringify(json)});

export default meta;
`
await writeFile(new URL('src/shared/icons/tablerMeta.ts', root), source)
console.log(`tablerMeta.ts: ${Object.keys(icons).length} íconos, ${categories.length} categorías (v${version})`)
