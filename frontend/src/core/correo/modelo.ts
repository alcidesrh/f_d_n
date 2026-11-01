/** Reglas puras del correo (ADR-048): consulta del listado, direcciones, fechas, el documento aislado del HTML y los cambios locales. */
import type { Accion, Direccion, Filtros, Redaccion, Resumen, Vista } from './types'

export const SIN_FILTROS: Filtros = { noLeidos: false, adjuntos: false, origen: null, q: '' }

export const VISTAS: { vista: Vista; nombre: string; icono: string }[] = [
  { vista: 'entrada', nombre: 'Recibidos', icono: 'inbox-outline' },
  { vista: 'destacados', nombre: 'Destacados', icono: 'star-outline' },
  { vista: 'enviados', nombre: 'Enviados', icono: 'send-outline' },
  { vista: 'borradores', nombre: 'Borradores', icono: 'draft-outline' },
  { vista: 'papelera', nombre: 'Papelera', icono: 'delete-outline' },
]

export function consulta(vista: Vista, f: Filtros, antes?: number): string {
  const q = new URLSearchParams({ carpeta: vista })
  if (f.noLeidos) q.set('noLeidos', '1')
  if (f.adjuntos) q.set('adjuntos', '1')
  if (f.origen) q.set('origen', f.origen)
  if (f.q.trim()) q.set('q', f.q.trim())
  if (antes) q.set('antes', String(antes))
  return q.toString()
}

export const hayFiltros = (f: Filtros) => f.noLeidos || f.adjuntos || f.origen !== null || f.q.trim() !== ''

const CORREO = /^[^\s@<>(),;:"]+@[^\s@<>(),;:"]+\.[^\s@<>(),;:"]{2,}$/

export const esValida = (direccion: string) => CORREO.test(direccion.trim())

/** `Ana <ana@x.com>, juan@y.com` → direcciones; lo inválido se queda en `resto`. */
export function leerDirecciones(texto: string): { direcciones: Direccion[]; resto: string } {
  const direcciones: Direccion[] = []
  const resto: string[] = []
  for (const parte of texto.split(/[,;\n]+/)) {
    const p = parte.trim()
    if (!p) continue
    const m = /^(.*)<([^<>]+)>$/.exec(p)
    const direccion = (m ? m[2]! : p).trim().replace(/^["']|["']$/g, '').toLowerCase()
    const nombre = m ? m[1]!.trim().replace(/^["']|["']$/g, '') || null : null
    if (esValida(direccion)) {
      if (!direcciones.some((d) => d.direccion === direccion)) direcciones.push({ direccion, nombre })
    } else resto.push(p)
  }
  return { direcciones, resto: resto.join(', ') }
}

export const etiqueta = (d: Direccion) => d.nombre?.trim() || d.direccion

/** Solo el nombre de pila (o la parte local) para listas cortas: "Ana, Juan, beto". */
export function corto(d: Direccion): string {
  if (d.nombre?.trim()) return d.nombre.trim().split(/\s+/)[0]!
  return d.direccion.split('@')[0]!
}

/** Quién se muestra en la fila: el remitente o, en lo que uno envía, a quién va. */
export function quien(c: Pick<Resumen, 'carpeta' | 'de' | 'para'>): string {
  if (c.carpeta === 'enviados' || c.carpeta === 'borradores') {
    const para = c.para.map(corto)
    if (!para.length) return c.carpeta === 'borradores' ? '(sin destinatarios)' : '(sin destinatarios)'
    return `Para: ${para.length > 3 ? `${para.slice(0, 3).join(', ')} +${para.length - 3}` : para.join(', ')}`
  }
  return etiqueta(c.de)
}

const mismoDia = (a: Date, b: Date) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()

/** Hoy la hora; este año "3 nov"; si no "3/11/25". */
export function fechaLista(iso: string, ahora = new Date()): string {
  const f = new Date(iso)
  if (mismoDia(f, ahora)) return f.toLocaleTimeString('es-GT', { hour: 'numeric', minute: '2-digit' })
  const ayer = new Date(ahora)
  ayer.setDate(ahora.getDate() - 1)
  if (mismoDia(f, ayer)) return 'Ayer'
  if (f.getFullYear() === ahora.getFullYear()) return f.toLocaleDateString('es-GT', { day: 'numeric', month: 'short' }).replace('.', '')
  return f.toLocaleDateString('es-GT', { day: 'numeric', month: 'numeric', year: '2-digit' })
}

export const fechaCompleta = (iso: string) =>
  new Date(iso).toLocaleString('es-GT', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' })

export function tamano(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`
}

export const redaccionVacia = (r: Redaccion) => !r.para.length && !r.cc.length && !r.cco.length && !r.asunto.trim() && !r.texto.trim()

export const mismaRedaccion = (a: Redaccion, b: Redaccion) => JSON.stringify(a) === JSON.stringify(b)

/**
 * La lista tras una acción, sin esperar al servidor: lo marcado cambia y lo
 * que deja de pertenecer a la vista sale (a la papelera, restaurado,
 * eliminado, quitado de destacados o leído con el filtro de no leídos).
 */
export function aplicarLocal(lista: Resumen[], ids: number[], accion: Accion, vista: Vista, filtros: Filtros): Resumen[] {
  const en = new Set(ids)
  const sale = (c: Resumen) => {
    if (!en.has(c.id)) return false
    if (accion === 'papelera' || accion === 'eliminar') return true
    if (accion === 'restaurar') return vista === 'papelera'
    if (accion === 'quitarDestacado') return vista === 'destacados'
    if (accion === 'leido') return filtros.noLeidos
    return false
  }
  return lista
    .filter((c) => !sale(c))
    .map((c) => {
      if (!en.has(c.id)) return c
      if (accion === 'leido' || accion === 'noLeido') return { ...c, leido: accion === 'leido' }
      if (accion === 'destacar' || accion === 'quitarDestacado') return { ...c, destacado: accion === 'destacar' }
      return c
    })
}

/**
 * El HTML de un correo como documento aislado para un `<iframe sandbox>` sin
 * scripts: la CSP del propio documento no deja cargar nada de fuera salvo,
 * si se pide, las imágenes (las remotas pueden avisar al remitente que se
 * abrió). Los enlaces abren en otra pestaña.
 */
export function documentoAislado(html: string, imagenesRemotas: boolean, base: string): string {
  const img = imagenesRemotas ? `img-src ${base} data: https: http:` : `img-src ${base} data:`
  const csp = `default-src 'none'; ${img}; style-src 'unsafe-inline'; font-src data:`
  const cabeza = `<meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${csp}"><base target="_blank"><style>html,body{margin:0;padding:0;background:#fff;color:#0f172a;font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;overflow-wrap:anywhere}body{padding:2px}img{max-width:100%;height:auto}table{max-width:100%}pre{white-space:pre-wrap}</style>`
  return `<!doctype html><html><head>${cabeza}</head><body>${html}</body></html>`
}

/** El HTML trae imágenes de fuera (para ofrecer "Mostrar imágenes"). */
export const tieneImagenesRemotas = (html: string) => /<img[^>]+src\s*=\s*["']?\s*https?:/i.test(html) || /url\(\s*["']?https?:/i.test(html)
