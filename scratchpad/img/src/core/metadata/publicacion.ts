/**
 * Publicación de la metadata del cliente (ADR-036), para la pantalla
 * `/configuracion/publicacion`: el modo, lo que falta publicar, las últimas
 * revisiones y publicar a mano (`config.editar`). REST y reglas puras.
 */
import { http } from '@/core/http'

export type ModoPublicacion = 'automatica' | 'manual'
export type OrigenRevision = 'automatica' | 'manual' | 'despliegue'

export interface CambiosMetadata {
  /** Cambió el schema GraphQL. */
  esquema: boolean
  /** Entidades con configuración nueva, modificada o que ya no la tienen. */
  agregadas: string[]
  modificadas: string[]
  eliminadas: string[]
}

export interface RevisionPublicada {
  revision: number
  origen: OrigenRevision
  creadaEn: string
  usuario: string | null
  nota: string | null
  /** Los navegadores ofrecieron «Recargar» con la nota. */
  aviso: boolean
  /** Cada navegador volvió a pedir todo. */
  completa: boolean
  cambios: CambiosMetadata
}

export interface ResumenPublicacion {
  modo: ModoPublicacion
  /** Revisión vigente. */
  revision: number
  pendiente: CambiosMetadata
  historial: RevisionPublicada[]
}

export interface SolicitudPublicacion {
  nota: string | null
  aviso: boolean
  completa: boolean
}

/** Tope de la nota (como el backend). */
export const NOTA_MAXIMA = 500

export const ORIGENES: Record<OrigenRevision, { etiqueta: string; severidad: 'info' | 'success' | 'secondary' }> = {
  automatica: { etiqueta: 'Automática', severidad: 'info' },
  manual: { etiqueta: 'Manual', severidad: 'success' },
  despliegue: { etiqueta: 'Despliegue', severidad: 'secondary' },
}

export function fetchPublicacion(): Promise<ResumenPublicacion> {
  return http.get<ResumenPublicacion>('/metadata/publicacion', { loadingKey: 'metadata:publicacion' })
}

/** Responde como `fetchPublicacion`; sin nada que publicar el backend responde 409. */
export function publicarMetadata(solicitud: SolicitudPublicacion): Promise<ResumenPublicacion> {
  return http.post<ResumenPublicacion>('/metadata/publicacion', solicitud, { loadingKey: 'metadata:publicar' })
}

export function hayCambios(cambios: CambiosMetadata): boolean {
  return cambios.esquema || cambios.agregadas.length > 0 || cambios.modificadas.length > 0 || cambios.eliminadas.length > 0
}

/** Se puede publicar si hay cambios, o para avisar o recargar todo aunque no los haya. */
export function puedePublicar(pendiente: CambiosMetadata, solicitud: Pick<SolicitudPublicacion, 'aviso' | 'completa'>): boolean {
  return hayCambios(pendiente) || solicitud.aviso || solicitud.completa
}

export interface LineaCambio {
  icono: string
  texto: string
}

/** `Bus, Piloto y 3 más`: las primeras `max` entidades. */
function entidades(nombres: string[], max: number): string {
  if (nombres.length <= max) return nombres.join(', ')
  return `${nombres.slice(0, max).join(', ')} y ${nombres.length - max} más`
}

/** Una línea por tipo de cambio, para leer de un vistazo. */
export function describirCambios(cambios: CambiosMetadata, max = 6): LineaCambio[] {
  const lineas: LineaCambio[] = []
  if (cambios.esquema) lineas.push({ icono: 'api', texto: 'Schema de la API' })
  if (cambios.agregadas.length)
    lineas.push({ icono: 'add-circle-outline', texto: `Configuración nueva: ${entidades(cambios.agregadas, max)}` })
  if (cambios.modificadas.length)
    lineas.push({ icono: 'edit-outline', texto: `Configuración modificada: ${entidades(cambios.modificadas, max)}` })
  if (cambios.eliminadas.length)
    lineas.push({ icono: 'do-not-disturb-on-outline', texto: `Sin configuración: ${entidades(cambios.eliminadas, max)}` })
  return lineas
}

/** Nota sin espacios de más; vacía es ninguna. */
export function notaLimpia(nota: string | null | undefined): string | null {
  const limpia = (nota ?? '').trim()
  return limpia === '' ? null : limpia
}

const FORMATO_FECHA = new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' })

export function fechaRevision(iso: string): string {
  const fecha = new Date(iso)
  return Number.isNaN(fecha.getTime()) ? iso : FORMATO_FECHA.format(fecha)
}
