/** Transporte del chat interno (`/api/chat/*`, ADR-026). */
import { config } from '@/core/config'
import { HttpError, http, request } from '@/core/http'
import { useSessionStore } from '@/core/auth/session'
import type { Archivo, Bandeja, Canal, Destinos, Mensaje, ModoGrupo, Perfil, Recurso, Referencia } from './types'

const BASE = '/chat'
const fondo = { silent: true } as const

export const fetchBandeja = (silencioso = false) => http.get<Bandeja>(`${BASE}/canales`, silencioso ? fondo : undefined)
export const fetchContactos = () => http.get<Perfil[]>(`${BASE}/contactos`, fondo)
export const fetchRecursos = () => http.get<Recurso[]>(`${BASE}/recursos`, fondo)
export const fetchToken = () => http.get<{ token: string; topico: string }>(`${BASE}/token`, fondo)

/** Latido de presencia; devuelve, de `ids`, quiénes están en línea. */
export const latir = (conexion: string, ids: number[]) => http.post<{ enLinea: number[] }>(`${BASE}/presencia`, { conexion, ids }, fondo)

/** Esta pestaña se va. Con `keepalive` sale aunque la página se esté cerrando. */
export function salirDeLinea(conexion: string) {
  const token = useSessionStore().token
  return fetch(`${config.restUrl}${BASE}/presencia?conexion=${encodeURIComponent(conexion)}`, {
    method: 'DELETE',
    keepalive: true,
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  }).catch(() => undefined)
}

export const abrirDirecto = (usuario: number) => http.post<Canal>(`${BASE}/canales/directo`, { usuario })
export const crearGrupo = (nombre: string, miembros: number[], modo: ModoGrupo = 'abierto') => http.post<Canal>(`${BASE}/canales/grupo`, { nombre, miembros, modo })

/** El estado completo del grupo tal como queda (solo su dueño). */
export interface EdicionGrupo {
  nombre: string
  modo: ModoGrupo
  /** Miembros por sí mismos. */
  miembros: number[]
  /** Los que escriben en un dirigido. */
  emisores: number[]
  subgrupos: { id: number; emisor: boolean }[]
}

export const editarGrupo = (canal: number, edicion: EdicionGrupo) => request<Canal>(`${BASE}/canales/${canal}`, { method: 'PUT', body: edicion })

/** Dejar el grupo; `sigue`: sigue en él por un grupo miembro. */
export const salirDeGrupo = (canal: number) => http.post<{ sigue: boolean }>(`${BASE}/canales/${canal}/salir`, {})

/** Quita la foto del grupo. */
export const quitarFotoGrupo = (canal: number) => http.delete<Canal>(`${BASE}/canales/${canal}/foto`, fondo)

/** Sube la foto del grupo (multipart, campo `foto`). */
export async function subirFotoGrupo(canal: number, imagen: Blob, nombre: string): Promise<Canal> {
  const datos = new FormData()
  datos.append('foto', imagen, nombre)
  const token = useSessionStore().token
  const respuesta = await fetch(`${config.restUrl}${BASE}/canales/${canal}/foto`, {
    method: 'POST',
    headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: datos,
  })
  const cuerpo = await respuesta.json().catch(() => null)
  if (!respuesta.ok) {
    const mensaje = (cuerpo as { error?: string } | null)?.error ?? (respuesta.status === 413 ? 'La imagen es demasiado grande.' : `HTTP ${respuesta.status}`)
    throw new HttpError(respuesta.status, cuerpo, mensaje)
  }
  return cuerpo as Canal
}

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
  /** Corrige este mensaje propio: el nuevo sale como respuesta a él, "editado". */
  edita?: number | null
  /** Archivos de `edita` que siguen en la versión editada. */
  conservar?: number[]
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

export const reenviar = (mensaje: number, destinos: Destinos) => http.post<{ canales: number[] }>(`${BASE}/mensajes/${mensaje}/reenviar`, destinos)

/** Elimina el mensaje para todos (el propio, cualquiera si administro el grupo o si soy SUPER_ADMIN). */
export const eliminarMensaje = (mensaje: number) => http.delete<Mensaje>(`${BASE}/mensajes/${mensaje}`)

export const marcarLeido = (canal: number, hasta: number) => http.post(`${BASE}/canales/${canal}/leido`, { hasta }, fondo)

export const compartir = (destinos: Destinos, adjuntos: Referencia[], texto = '') =>
  http.post<{ canales: number[] }>(`${BASE}/compartir`, { ...destinos, texto, adjuntos })
