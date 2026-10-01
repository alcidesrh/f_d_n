/**
 * Prerender de las páginas públicas (ADR-023): después de `vite build`
 * (cliente, en `backend/public/pagina`) y `vite build --ssr` (en `.ssr/`),
 * escribe `<idioma>/<ruta>/index.html` con el contenido ya pintado, el
 * `<head>` de cada página y `sitemap.xml`. El backend (`PaginaController`)
 * entrega esos archivos; el navegador monta la SPA encima.
 *
 * Variables:
 * - `PAGINA_ORIGEN`: dominio público para las URL canónicas y el sitemap
 *   (por defecto https://transportesfuentedelnorte.com).
 * - `PAGINA_BACKEND`: si responde al compilar, la página de estaciones sale
 *   con el directorio ya incluido (si no, se carga en el navegador).
 */
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..')
const salida = join(raiz, '../backend/public/pagina')
const ssr = join(raiz, '.ssr/entry-server.js')
const origen = (process.env.PAGINA_ORIGEN ?? 'https://transportesfuentedelnorte.com').replace(/\/$/, '')
const backend = process.env.PAGINA_BACKEND ?? 'http://localhost'

const { render, ESTATICAS, IDIOMAS } = await import(pathToFileURL(ssr).href)
const plantilla = readFileSync(join(salida, 'index.html'), 'utf8')

let directorio = null
try {
  const r = await fetch(`${backend}/api/publico/estaciones/directorio`, { signal: AbortSignal.timeout(5000) })
  if (r.ok) directorio = await r.json()
} catch {
  // Sin backend al compilar: las estaciones se cargan en el navegador.
}
console.log(`prerender: directorio de estaciones ${directorio ? `(${directorio.length})` : 'no disponible'}`)

const paginas = []
for (const idioma of IDIOMAS) {
  for (const { ruta } of ESTATICAS) {
    const r = await render(idioma, ruta, { origen, directorio })
    const html = plantilla
      .replace(/<html lang="[^"]*">/, `<html lang="${idioma}">`)
      .replace(/<title>[\s\S]*?<\/title>/, '')
      .replace(/<meta name="description"[^>]*>/, '')
      .replace('</head>', `    ${r.head}\n    ${r.estilos}\n  </head>`)
      .replace('<div id="app"></div>', `<div id="app">${r.html}</div>`)
    const destino = join(salida, idioma, ruta, 'index.html')
    mkdirSync(dirname(destino), { recursive: true })
    writeFileSync(destino, html)
    paginas.push({ idioma, ruta })
  }
}

const url = (idioma, ruta) => `${origen}/pagina/${idioma}/${ruta}`
const sitemap = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
${paginas
  .map(
    ({ idioma, ruta }) => `  <url>
    <loc>${url(idioma, ruta)}</loc>
${IDIOMAS.map((i) => `    <xhtml:link rel="alternate" hreflang="${i}" href="${url(i, ruta)}"/>`).join('\n')}
    <xhtml:link rel="alternate" hreflang="x-default" href="${url('es', ruta)}"/>
  </url>`,
  )
  .join('\n')}
</urlset>
`
writeFileSync(join(salida, 'sitemap.xml'), sitemap)
writeFileSync(join(salida, 'robots.txt'), `User-agent: *\nDisallow: /pagina/*/pago\nDisallow: /pagina/*/compra/\nSitemap: ${origen}/pagina/sitemap.xml\n`)
rmSync(join(raiz, '.ssr'), { recursive: true, force: true })
console.log(`prerender: ${paginas.length} páginas, sitemap.xml y robots.txt en ${salida}`)
