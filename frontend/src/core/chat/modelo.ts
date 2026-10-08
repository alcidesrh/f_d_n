/**
 * Reglas puras del chat: agrupar mensajes por día y por racha de autor,
 * fechas relativas, búsqueda sin acentos, aplicar avisos a la bandeja y
 * partir el texto en enlaces.
 */
import type { Aviso, Canal, Mensaje, Perfil } from './types'

/** Último elemento (sin `Array.at`: el código compila con `"lib": []`). */
export const ultimo = <T>(lista: readonly T[]): T | undefined => lista[lista.length - 1]

/** Mensajes seguidos del mismo autor con menos de esto entre sí van juntos. */
const RACHA_MS = 5 * 60_000
const DIA_MS = 86_400_000

export interface Racha {
  autor: Perfil | null
  mio: boolean
  mensajes: Mensaje[]
}

export interface Bloque {
  /** `yyyy-mm-dd` local */
  dia: string
  etiqueta: string
  rachas: Racha[]
}

const pad = (n: number) => String(n).padStart(2, '0')
const claveDia = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
const inicioDia = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()
const diasEntre = (a: Date, b: Date) => Math.round((inicioDia(b) - inicioDia(a)) / DIA_MS)

export function agrupar(mensajes: Mensaje[], yo: number | null, ahora = new Date()): Bloque[] {
  const bloques: Bloque[] = []
  let previo: Mensaje | null = null
  for (const m of mensajes) {
    const fecha = new Date(m.fecha)
    const dia = claveDia(fecha)
    let bloque = bloques[bloques.length - 1]
    if (!bloque || bloque.dia !== dia) {
      bloque = { dia, etiqueta: etiquetaDia(fecha, ahora), rachas: [] }
      bloques.push(bloque)
      previo = null
    }
    const racha = bloque.rachas[bloque.rachas.length - 1]
    const sigue = racha && previo && (previo.autor?.id ?? null) === (m.autor?.id ?? null) && fecha.getTime() - new Date(previo.fecha).getTime() < RACHA_MS
    if (sigue) racha.mensajes.push(m)
    else bloque.rachas.push({ autor: m.autor, mio: m.autor !== null && m.autor.id === yo, mensajes: [m] })
    previo = m
  }
  return bloques
}

export function etiquetaDia(fecha: Date, ahora = new Date()): string {
  const dias = diasEntre(fecha, ahora)
  if (dias === 0) return 'Hoy'
  if (dias === 1) return 'Ayer'
  if (dias > 1 && dias < 7) return capital(new Intl.DateTimeFormat('es-GT', { weekday: 'long' }).format(fecha))
  return new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'long', year: fecha.getFullYear() === ahora.getFullYear() ? undefined : 'numeric' }).format(fecha)
}

export const horaCorta = (iso: string) => new Intl.DateTimeFormat('es-GT', { hour: 'numeric', minute: '2-digit' }).format(new Date(iso))

/** Fecha compacta para la bandeja: hora hoy, "Ayer", día de la semana o d/m/aa. */
export function fechaBandeja(iso: string, ahora = new Date()): string {
  const fecha = new Date(iso)
  const dias = diasEntre(fecha, ahora)
  if (dias <= 0) return horaCorta(iso)
  if (dias === 1) return 'Ayer'
  if (dias < 7) return capital(new Intl.DateTimeFormat('es-GT', { weekday: 'short' }).format(fecha)).replace('.', '')
  return new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'numeric', year: '2-digit' }).format(fecha)
}

const capital = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)

export function iniciales(nombre: string): string {
  const partes = nombre.trim().split(/\s+/).filter(Boolean)
  return ((partes[0]?.[0] ?? '?') + (partes.length > 1 ? (partes[1]?.[0] ?? '') : '')).toUpperCase()
}

/** Tono estable (0–7) para el avatar de una persona o grupo. */
export const tono = (id: number) => Math.abs(id) % 8

export const normalizar = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/** Todas las palabras de la búsqueda aparecen (sin acentos ni mayúsculas). */
export function coincide(texto: string, busqueda: string): boolean {
  const t = normalizar(texto)
  return normalizar(busqueda).split(/\s+/).filter(Boolean).every((p) => t.includes(p))
}

export const ordenarCanales = (canales: Canal[]) => [...canales].sort((a, b) => b.actividad.localeCompare(a.actividad))

export const totalNoLeidos = (canales: Canal[]) => canales.reduce((n, c) => n + c.noLeidos, 0)

/**
 * Bandeja tras un aviso. Devuelve null si el canal no está en la bandeja
 * (hay que recargarla). `leyendo`: el canal está abierto y visible, así que
 * el mensaje no cuenta como no leído.
 */
export function aplicarAviso(canales: Canal[], aviso: Aviso, yo: number | null, leyendo: number | null): Canal[] | null {
  const canal = canales.find((c) => c.id === aviso.canal)
  if (!canal) return aviso.tipo === 'leido' ? canales : null
  if (aviso.tipo === 'canal') return canales
  if (aviso.tipo === 'leido') {
    if (aviso.usuario === yo) {
      return canales.map((c) => (c.id === aviso.canal ? { ...c, noLeidos: 0, leidoHasta: Math.max(c.leidoHasta, aviso.hasta) } : c))
    }
    // Otro miembro leyó: actualiza su "visto".
    return canales.map((c) =>
      c.id === aviso.canal
        ? { ...c, lecturas: [...c.lecturas.filter((l) => l.usuario !== aviso.usuario), { usuario: aviso.usuario, hasta: aviso.hasta }] }
        : c,
    )
  }
  if (canal.ultimo && canal.ultimo.id >= aviso.mensaje) return canales
  const ajeno = aviso.autor?.id !== yo
  const actualizado: Canal = {
    ...canal,
    actividad: new Date().toISOString(),
    noLeidos: ajeno && leyendo !== canal.id ? canal.noLeidos + 1 : canal.noLeidos,
    ultimo: { id: aviso.mensaje, autor: aviso.autor?.id ?? null, extracto: aviso.extracto, fecha: new Date().toISOString() },
  }
  return [actualizado, ...canales.filter((c) => c.id !== canal.id)]
}

/** Une páginas de mensajes sin duplicados, en orden cronológico (por id). */
export function unir(actuales: Mensaje[], nuevos: Mensaje[]): Mensaje[] {
  const porId = new Map(actuales.map((m) => [m.id, m]))
  for (const m of nuevos) porId.set(m.id, m)
  return [...porId.values()].sort((a, b) => a.id - b.id)
}

export type Segmento = { texto: string } | { url: string }

/** Parte el texto en trozos y enlaces `http(s)://…` (para pintarlos sin `v-html`). */
export function segmentos(texto: string): Segmento[] {
  const salida: Segmento[] = []
  let ultimo = 0
  for (const m of texto.matchAll(/https?:\/\/[^\s<>"]+[^\s<>".,;:!?)\]]/g)) {
    if (m.index > ultimo) salida.push({ texto: texto.slice(ultimo, m.index) })
    salida.push({ url: m[0] })
    ultimo = m.index + m[0].length
  }
  if (ultimo < texto.length) salida.push({ texto: texto.slice(ultimo) })
  return salida
}

/**
 * Quiénes de los demás ya vieron el mensaje `id` (los que leyeron hasta él o
 * más allá). `todos`: no queda nadie por verlo.
 */
export function vistoPor(canal: Canal, id: number): { todos: boolean; quienes: Perfil[] } {
  const hasta = new Map(canal.lecturas.map((l) => [l.usuario, l.hasta]))
  const otros = canal.miembros.filter((m) => hasta.has(m.id))
  const quienes = otros.filter((m) => (hasta.get(m.id) ?? 0) >= id)
  return { todos: otros.length > 0 && quienes.length === otros.length, quienes }
}

/** `1536` → `1.5 KB`. */
export function tamanoLegible(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  const kb = bytes / 1024
  return kb < 1024 ? `${kb < 10 ? kb.toFixed(1) : Math.round(kb)} KB` : `${(kb / 1024).toFixed(1)} MB`
}

/** Medidas para reducir una foto a `max` px por el lado mayor, sin agrandarla. */
export function medidasReducidas(ancho: number, alto: number, max: number): { ancho: number; alto: number } {
  const factor = Math.min(1, max / Math.max(ancho, alto, 1))
  return { ancho: Math.round(ancho * factor), alto: Math.round(alto * factor) }
}
