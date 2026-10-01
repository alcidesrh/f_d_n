/**
 * Metadatos de cada página para buscadores (ADR-023): título, descripción,
 * URL canónica y versiones en otros idiomas (`hreflang`). El cliente los
 * aplica al navegar; `scripts/prerender.mjs` los escribe en el HTML.
 */
import { IDIOMAS, IDIOMA_DEFECTO, type Idioma } from './i18n'

export type PaginaSeo = 'inicio' | 'servicios' | 'estaciones' | 'nosotros' | 'politicas' | 'contacto' | 'pago' | 'compra'

export interface Head {
  idioma: Idioma
  titulo: string
  descripcion: string
  canonica: string
  alternas: Array<{ idioma: Idioma | 'x-default'; url: string }>
  indexable: boolean
}

export const MARCA = 'Transportes Fuente del Norte'

/** `ruta` sin idioma (`''`, `'servicios'`…) → URL absoluta de la página en `idioma`. */
export const urlPagina = (origen: string, idioma: Idioma, ruta: string) =>
  `${origen.replace(/\/$/, '')}/pagina/${idioma}/${ruta ? `${ruta}` : ''}`

export function head(
  t: (clave: string) => string,
  opciones: { idioma: Idioma; pagina: PaginaSeo; ruta: string; origen: string; indexable: boolean },
): Head {
  const titulo = t(`seo.${opciones.pagina}.titulo`)
  return {
    idioma: opciones.idioma,
    titulo: opciones.pagina === 'inicio' ? `${titulo} | ${MARCA}` : `${titulo} · ${MARCA}`,
    descripcion: t(`seo.${opciones.pagina}.descripcion`),
    canonica: urlPagina(opciones.origen, opciones.idioma, opciones.ruta),
    alternas: [
      ...IDIOMAS.map((i) => ({ idioma: i, url: urlPagina(opciones.origen, i, opciones.ruta) })),
      { idioma: 'x-default' as const, url: urlPagina(opciones.origen, IDIOMA_DEFECTO, opciones.ruta) },
    ],
    indexable: opciones.indexable,
  }
}

const escapar = (s: string) => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

/** Etiquetas del `<head>` (prerender). */
export function headHtml(h: Head): string {
  return [
    `<title>${escapar(h.titulo)}</title>`,
    `<meta name="description" content="${escapar(h.descripcion)}">`,
    h.indexable ? '' : '<meta name="robots" content="noindex">',
    `<link rel="canonical" href="${escapar(h.canonica)}">`,
    ...h.alternas.map((a) => `<link rel="alternate" hreflang="${a.idioma}" href="${escapar(a.url)}">`),
    `<meta property="og:type" content="website">`,
    `<meta property="og:site_name" content="${MARCA}">`,
    `<meta property="og:title" content="${escapar(h.titulo)}">`,
    `<meta property="og:description" content="${escapar(h.descripcion)}">`,
    `<meta property="og:url" content="${escapar(h.canonica)}">`,
    `<meta property="og:locale" content="${h.idioma}">`,
  ]
    .filter(Boolean)
    .join('\n    ')
}

/** Aplica el head en el navegador (al navegar dentro de la SPA). */
export function aplicarHead(h: Head) {
  document.documentElement.lang = h.idioma
  document.title = h.titulo
  const meta = (selector: string, crear: () => HTMLElement) => document.head.querySelector(selector) ?? document.head.appendChild(crear())
  ;(meta('meta[name="description"]', () => Object.assign(document.createElement('meta'), { name: 'description' })) as HTMLMetaElement).content = h.descripcion
  ;(meta('link[rel="canonical"]', () => Object.assign(document.createElement('link'), { rel: 'canonical' })) as HTMLLinkElement).href = h.canonica
  document.head.querySelectorAll('link[rel="alternate"][hreflang]').forEach((l) => l.remove())
  for (const a of h.alternas) {
    document.head.appendChild(Object.assign(document.createElement('link'), { rel: 'alternate', hreflang: a.idioma, href: a.url }))
  }
  const robots = document.head.querySelector('meta[name="robots"]')
  if (h.indexable) robots?.remove()
  else if (!robots) document.head.appendChild(Object.assign(document.createElement('meta'), { name: 'robots', content: 'noindex' }))
}
