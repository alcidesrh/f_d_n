/** Transporte del chat interno (`/api/chat/*`, ADR-026). */
import { config } from '@/core/config'
import { HttpError, http } from '@/core/http'
import { useSessionStore } from '@/core/auth/session'
import type { Archivo, Bandeja, Canal, Destinos, Mensaje, Perfil, Recurso, Referencia } from './types'

const BASE = '/chat'
const fondo = { silent: true } as const

export const fetchBandeja = (silencioso = false) => http.get<Bandeja>(`${BASE}/canales`, silencioso ? fondo : undefined)
export const fetchContactos = () => http.get<Perfil[]>(`${BASE}/contactos`, fondo)
export const fetchRecursos = () => http.get<Recurso[]>(`${BASE}/recursos`, fondo)
export const fetchToken = () => http.get<{ token: string; topico: string }>(`${BASE}/token`, fondo)

export const abrirDirecto = (usuario: number) => http.post<Canal>(`${BASE}/canales/directo`, { usuario })
export const crearGrupo = (nombre: string, miembros: number[]) => http.post<Canal>(`${BASE}/canales/grupo`, { nombre, miembros })

/** Los últimos; con `antes` los anteriores a ese id; con `despues`, los nuevos. */
export function fetchMensajes(canal: number, cursor: { antes?: number; despues?: number } = {}) {
  const q = new URLSearchParams()
  if (cursor.antes) q.set('antes', String(cursor.antes))
  if (cursor.despues) q.set('despues', String(cursor.despues))
  const qs = q.toString()
  return http.get<Mensaje[]>(`${BASE}/canales/${canal}/mensajes${qs ? `?${qs}` : ''}`, fondo)
}

export interface Envio {
  texto: string
  adjuntos?: Referencia[]
  /** Ids de `subirArchivo`. */
  archivos?: number[]
  respuestaA?: number | null
}

export const escribir = (canal: number, envio: Envio) => http.post<Mensaje>(`${BASE}/canales/${canal}/mensajes`, envio, fondo)

/** URL absoluta de un archivo (las de la API son relativas y firmadas). */
export const urlArchivo = (a: Archivo, descargar = false) => `${config.restUrl}${a.url}${descargar ? '&descargar=1' : ''}`

/**
 * Sube un archivo (multipart) con progreso 0–1. Por XHR porque `fetch` no
 * informa el avance de la subida. Queda suelto hasta enviarlo en un mensaje.
 */
export function subirArchivo(archivo: Blob, nombre: string, alAvanzar?: (fraccion: number) => void): Promise<Archivo> {
  return new Promise((resolver, rechazar) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', `${config.restUrl}${BASE}/archivos`)
    xhr.setRequestHeader('Accept', 'application/json')
    const token = useSessionStore().token
    if (token) xhr.setRequestHeader('Authorization', `Bearer ${token}`)
    xhr.upload.onprogress = (e) => e.lengthComputable && alAvanzar?.(e.loaded / e.total)
    xhr.onload = () => {
      let cuerpo: unknown = null
      try {
        cuerpo = JSON.parse(xhr.responseText)
      } catch {
        cuerpo = xhr.responseText
      }
      if (xhr.status >= 200 && xhr.status < 300) return resolver(cuerpo as Archivo)
      const mensaje = (cuerpo as { error?: string } | null)?.error ?? (xhr.status === 413 ? 'El archivo es demasiado grande.' : `HTTP ${xhr.status}`)
      rechazar(new HttpError(xhr.status, cuerpo, mensaje))
    }
    xhr.onerror = () => rechazar(new Error('No se pudo subir el archivo (sin conexión).'))
    const datos = new FormData()
    datos.append('archivo', archivo, nombre)
    xhr.send(datos)
  })
}

export const marcarLeido = (canal: number, hasta: number) => http.post(`${BASE}/canales/${canal}/leido`, { hasta }, fondo)

export const compartir = (destinos: Destinos, adjuntos: Referencia[], texto = '') =>
  http.post<{ canales: number[] }>(`${BASE}/compartir`, { ...destinos, texto, adjuntos })
