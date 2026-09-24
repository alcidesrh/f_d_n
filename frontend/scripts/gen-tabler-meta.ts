/**
 * Genera `src/lib/icons/tabler-meta.json`: categoría + tags de cada ícono de
 * Tabler, para el buscador de `IconPicker`.
 *
 * El set de Iconify (`@iconify-json/tabler`) trae los SVG pero no las
 * categorías ni los tags; esos solo viven en el `icons.json` del paquete
 * oficial `@tabler/icons`. Se descarga la misma versión que declara
 * `@iconify-json/tabler` (`iconSetVersion`) y se compacta a
 * `{ version, categories[], icons: { nombre: [índiceCategoría, "tags"] } }`.
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
const source = (await response.json()) as Record<string, TablerIconMeta>

const categories: string[] = []
const icons: Record<string, [number, string]> = {}
for (const meta of Object.values(source).sort((a, b) => a.name.localeCompare(b.name))) {
  const category = meta.category || 'Otros'
  let index = categories.indexOf(category)
  if (index === -1) index = categories.push(category) - 1
  icons[meta.name] = [index, (meta.tags ?? []).map(String).join(' ')]
}

const target = new URL('src/lib/icons/tabler-meta.json', root)
await writeFile(target, JSON.stringify({ version, categories, icons }))
console.log(`tabler-meta.json: ${Object.keys(icons).length} íconos, ${categories.length} categorías (v${version})`)
