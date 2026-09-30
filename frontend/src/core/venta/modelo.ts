/**
 * Reglas puras de la pantalla de venta: estado de cada asiento en el mapa,
 * paradas donde se puede subir/bajar y selección de asientos.
 */
import type { AsientoCroquis, EstadoAsiento } from '@/core/croquis/types'
import type { AsientoOcupado, Parada, SalidaDetalle } from './types'

/** Cómo se pinta cada asiento en la taquilla, según lo que lo ocupa. */
export function estadoEnMapa(
  ocupados: readonly AsientoOcupado[],
  seleccion: readonly number[],
): (asiento: AsientoCroquis) => EstadoAsiento {
  const porId = new Map(ocupados.map((o) => [o.asiento, o]))
  const elegidos = new Set(seleccion)
  return (asiento) => {
    const id = asiento.id ?? -1
    const o = porId.get(id)
    if (!o) return elegidos.has(id) ? 'seleccionado' : 'disponible'
    if (o.estado === 'propio') return 'seleccionado'
    if (o.estado === 'reservado') return 'reservado'
    if (o.canal === 'web') return 'ocupado-web'
    if (o.canal === 'agencia') return 'ocupado-agencia'
    return 'ocupado'
  }
}

/** Quita de la selección los asientos que alguien más ocupó. */
export function depurarSeleccion(
  seleccion: readonly number[],
  ocupados: readonly AsientoOcupado[],
): number[] {
  const tomados = new Set(ocupados.filter((o) => o.estado !== 'propio').map((o) => o.asiento))
  return seleccion.filter((id) => !tomados.has(id))
}

/** Agrega o quita un asiento de la selección (respetando un máximo). */
export function alternar(seleccion: readonly number[], id: number, maximo = Infinity): number[] {
  if (seleccion.includes(id)) return seleccion.filter((x) => x !== id)
  return seleccion.length >= maximo ? [...seleccion] : [...seleccion, id]
}

/** Paradas donde se puede subir: las que son origen de algún trayecto vendible. */
export function paradasDeSubida(
  detalle: Pick<SalidaDetalle, 'paradas' | 'trayectos'>,
): Parada[] {
  const origenes = new Set(detalle.trayectos.map((t) => t.origen))
  return detalle.paradas.filter((p) => origenes.has(p.id))
}

/** Paradas donde se puede bajar si se sube en `sube`. */
export function paradasDeBajada(
  detalle: Pick<SalidaDetalle, 'paradas' | 'trayectos'>,
  sube: number | null,
): Parada[] {
  if (sube == null) return []
  const destinos = new Set(detalle.trayectos.filter((t) => t.origen === sube).map((t) => t.destino))
  return detalle.paradas.filter((p) => destinos.has(p.id))
}

/** Id del trayecto vendible entre dos paradas. */
export function trayectoEntre(
  detalle: Pick<SalidaDetalle, 'trayectos'>,
  sube: number | null,
  baja: number | null,
): number | null {
  return detalle.trayectos.find((t) => t.origen === sube && t.destino === baja)?.id ?? null
}

/**
 * Parada de subida por defecto: la estación desde donde se vende, si el
 * salida pasa por ella; si no, el origen.
 */
export function subidaPorDefecto(
  detalle: Pick<SalidaDetalle, 'paradas' | 'trayectos'>,
  estacion: number | null,
): number | null {
  const subidas = paradasDeSubida(detalle)
  return subidas.find((p) => p.id === estacion)?.id ?? subidas[0]?.id ?? null
}

/** Bajada por defecto: el final del salida (o la última posible). */
export function bajadaPorDefecto(
  detalle: Pick<SalidaDetalle, 'paradas' | 'trayectos'>,
  sube: number | null,
): number | null {
  const bajadas = paradasDeBajada(detalle, sube)
  return bajadas[bajadas.length - 1]?.id ?? null
}

const ZONA = 'America/Guatemala'

/** `08:00 p. m.` en la hora de Guatemala. */
export const hora = (iso: string | null | undefined) =>
  iso
    ? new Intl.DateTimeFormat('es-GT', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
        timeZone: ZONA,
      }).format(new Date(iso))
    : '—'

/** `27/09/2026` en la hora de Guatemala. */
export const fecha = (iso: string | null | undefined) =>
  iso
    ? new Intl.DateTimeFormat('es-GT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: ZONA,
      }).format(new Date(iso))
    : '—'

/** Fecha local `AAAA-MM-DD` (lo que espera la API). */
export function diaISO(d: Date): string {
  const dos = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`
}

/** Clave de idempotencia de una venta. */
export function nuevoToken(): string {
  return globalThis.crypto.randomUUID()
}
