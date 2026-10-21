/**
 * Reglas puras de `/administracion`: búsqueda, reparto en columnas, orden
 * (mover cajas, ordenar y pasar enlaces de caja) y destinos de navegación.
 * Las cajas y su configuración viven en el servidor (`./api`); aquí solo se
 * calcula sobre ellas, sin mutarlas.
 */
import type { AccesoPayload, Acceso, Destino, Enlace, EnlacePayload, Seccion, SeccionPayload } from './api'

/** Rampas de la paleta (`assets/palette.css`) que puede llevar una caja; espejo de `App\Administracion\Datos::COLORES`. */
export const COLORES = ['blue', 'sky', 'cyan', 'teal', 'emerald', 'green', 'lime', 'yellow', 'amber', 'orange', 'red', 'rose', 'pink', 'fuchsia', 'purple', 'violet', 'indigo', 'slate', 'stone'] as const

/** Orden completo para `PUT /orden`. */
export const ordenDe = (secciones: Seccion[]) => secciones.map((s) => ({ id: s.id, enlaces: s.enlaces.map((e) => e.id) }))

/** Mueve una caja `delta` lugares (−1 antes, +1 después), sin salirse de la lista. */
export function moverSeccion(secciones: Seccion[], id: number, delta: number): Seccion[] {
  const desde = secciones.findIndex((s) => s.id === id)
  if (desde < 0) return secciones
  const hasta = Math.min(Math.max(desde + delta, 0), secciones.length - 1)
  if (hasta === desde) return secciones
  const copia = [...secciones]
  const [movida] = copia.splice(desde, 1)
  copia.splice(hasta, 0, movida!)
  return copia
}

/** Nuevo orden de los enlaces de una caja (los mismos, reordenados); otra lista no cambia nada. */
export function ordenarEnlaces(secciones: Seccion[], seccionId: number, ids: number[]): Seccion[] {
  return secciones.map((s) => {
    if (s.id !== seccionId) return s
    const mismos = ids.length === s.enlaces.length && ids.every((id) => s.enlaces.some((e) => e.id === id))
    return mismos ? { ...s, enlaces: ids.map((id) => s.enlaces.find((e) => e.id === id)!) } : s
  })
}

/** Pasa un enlace al final de otra caja. */
export function moverEnlace(secciones: Seccion[], enlaceId: number, destino: number): Seccion[] {
  const enlace = secciones.flatMap((s) => s.enlaces).find((e) => e.id === enlaceId)
  if (!enlace || !secciones.some((s) => s.id === destino)) return secciones
  return secciones.map((s) => {
    const sin = s.enlaces.filter((e) => e.id !== enlaceId)
    return s.id === destino ? { ...s, enlaces: [...sin, enlace] } : { ...s, enlaces: sin }
  })
}

/**
 * Reparte elementos en `columnas` como un muro (masonry): cada uno, en su
 * orden, va a la columna más baja (a igualdad, la de la izquierda). Así el
 * orden se lee de izquierda a derecha y de arriba abajo, y las columnas
 * quedan parejas en lugar de dejar huecos de una grilla por filas.
 */
export function repartirEnColumnas<T>(elementos: T[], peso: (e: T) => number, columnas: number): T[][] {
  const n = Math.max(1, Math.floor(columnas))
  const cols: T[][] = Array.from({ length: n }, () => [])
  const altos = Array.from({ length: n }, () => 0)
  for (const e of elementos) {
    const i = altos.indexOf(Math.min(...altos))
    cols[i]!.push(e)
    altos[i]! += peso(e)
  }
  return cols
}

const normalizar = (s: string) =>
  s
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()

/** El enlace coincide si cada palabra buscada aparece en su texto, sus claves o el título de su caja. */
export function coincide(e: Pick<Enlace, 'etiqueta' | 'claves'>, texto: string, titulo = ''): boolean {
  const palabras = normalizar(texto).split(/\s+/).filter(Boolean)
  if (!palabras.length) return true
  const pajar = normalizar([e.etiqueta, titulo, ...e.claves].join(' '))
  return palabras.every((p) => pajar.includes(p))
}

/** Lo que se ve fuera de la configuración: activas, con enlaces activos que coinciden; sin enlaces, fuera. */
export function seccionesVisibles(secciones: Seccion[], texto: string): Seccion[] {
  return secciones
    .filter((s) => s.activa)
    .map((s) => ({ ...s, enlaces: s.enlaces.filter((e) => e.activo && coincide(e, texto, s.titulo)) }))
    .filter((s) => s.enlaces.length > 0)
}

// ── Destinos ────────────────────────────────────────────────────────────────

/** A dónde navega un destino: ruta por nombre, path interno o URL externa; `null` si no se puede. */
export type Ubicacion = { tipo: 'ruta'; to: { name: string; params: Record<string, string> } } | { tipo: 'path'; to: string } | { tipo: 'externa'; href: string }

export function ubicacion(d: Destino | null | undefined, existeRuta: (nombre: string) => boolean): Ubicacion | null {
  if (!d) return null
  if (d.ruta) return existeRuta(d.ruta) ? { tipo: 'ruta', to: { name: d.ruta, params: { ...d.parametros } } } : null
  if (d.path) return /^https?:\/\//i.test(d.path) ? { tipo: 'externa', href: d.path } : { tipo: 'path', to: d.path }
  return null
}

/** Parámetros de un path de vue-router: `/form/:entity/:id?` → `[{ nombre: 'entity', opcional: false }, { nombre: 'id', opcional: true }]`. */
export function parametrosDeRuta(path: string): { nombre: string; opcional: boolean }[] {
  return [...path.matchAll(/:([A-Za-z_]\w*)(\([^)]*\))?([?*+])?/g)].map((m) => ({ nombre: m[1]!, opcional: m[3] === '?' || m[3] === '*' }))
}

/** Texto corto del destino para mostrar al configurar. */
export function describirDestino(d: Destino | null): string {
  if (!d) return ''
  if (d.path) return d.path
  const params = Object.entries(d.parametros ?? {})
  return params.length ? `${d.ruta} (${params.map(([k, v]) => `${k}: ${v}`).join(', ')})` : (d.ruta ?? '')
}

// ── Payloads ────────────────────────────────────────────────────────────────

export const restringido = (a: Acceso | undefined) => !!a && (a.roles.length > 0 || a.usuarios.length > 0)

export const accesoPayload = (a: Acceso | undefined): AccesoPayload | undefined => (a ? { roles: a.roles.map((r) => r.id), usuarios: a.usuarios.map((u) => u.id) } : undefined)

/** Lo que se reenvía al cambiar algo de una caja (el acceso, solo si se pasa). */
export const seccionPayload = (s: Seccion, cambios: Partial<SeccionPayload> = {}): SeccionPayload => ({
  titulo: s.titulo,
  icono: s.icono,
  color: s.color,
  activa: s.activa,
  ...cambios,
})

export const enlacePayload = (e: Enlace, cambios: Partial<EnlacePayload> = {}): EnlacePayload => ({
  etiqueta: e.etiqueta,
  icono: e.icono,
  claves: e.claves,
  abrir: e.abrir,
  agregar: e.agregar,
  activo: e.activo,
  ...cambios,
})

/** Un destino que se puede guardar: ruta con sus parámetros obligatorios, o un path interno (`/…`) o http(s) sin espacios. */
export function destinoValido(d: Destino | null, requeridos: string[] = []): boolean {
  if (!d) return false
  if (d.ruta) return requeridos.every((p) => (d.parametros?.[p] ?? '').trim() !== '')
  const path = (d.path ?? '').trim()
  return !/\s/.test(path) && ((path.startsWith('/') && !path.startsWith('//')) || /^https?:\/\/\S+$/i.test(path))
}
