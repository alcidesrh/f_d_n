/** Transporte del correo de la empresa (`/api/correo/*`, ADR-048). */
import { config } from '@/core/config'
import { HttpError, http, request } from '@/core/http'
import { useSessionStore } from '@/core/auth/session'
import { consulta } from './modelo'
import type { Accion, Adjunto, Contacto, Correo, Estado, Filtros, ModoRespuesta, Redaccion, Resumen, Vista } from './types'

const BASE = '/correo'
const fondo = { silent: true } as const

export const fetchEstado = (silencioso = false) => http.get<Estado>(BASE, silencioso ? fondo : undefined)

export const fetchCorreos = (vista: Vista, filtros: Filtros, antes?: number, silencioso = false) =>
  http.get<{ correos: Resumen[]; mas: boolean }>(`${BASE}/correos?${consulta(vista, filtros, antes)}`, silencioso ? fondo : undefined)

/** El correo completo; `leer` además lo marca leído. */
export const fetchCorreo = (id: number, leer = false) => http.get<Correo>(`${BASE}/correos/${id}${leer ? '?leer=1' : ''}`, fondo)

export const aplicar = (ids: number[], accion: Accion) => http.post<{ n: number }>(`${BASE}/correos/acciones`, { ids, accion }, fondo)
export const vaciarPapelera = () => http.post<{ n: number }>(`${BASE}/papelera/vaciar`, {})
export const reintentar = (id: number) => http.post<Correo>(`${BASE}/correos/${id}/reintentar`, {})

export const fetchContactos = (q: string) => http.get<Contacto[]>(`${BASE}/contactos?q=${encodeURIComponent(q)}`, fondo)

/** Borrador nuevo; con `origen` y `modo`, la respuesta o el reenvío ya armado. */
export const crearBorrador = (datos: Partial<Redaccion> & { origen?: number; modo?: ModoRespuesta } = {}) => http.post<Correo>(`${BASE}/borradores`, datos, fondo)
export const guardarBorrador = (id: number, datos: Partial<Redaccion>) => request<{ id: number; fecha: string }>(`${BASE}/borradores/${id}`, { method: 'PUT', body: datos, silent: true })
export const descartarBorrador = (id: number) => http.delete(`${BASE}/borradores/${id}`, fondo)
export const enviarBorrador = (id: number, datos: Partial<Redaccion>) => http.post<Correo>(`${BASE}/borradores/${id}/enviar`, datos)
export const quitarAdjunto = (borrador: number, adjunto: number) => http.delete(`${BASE}/borradores/${borrador}/adjuntos/${adjunto}`, fondo)

/**
 * Guarda lo último aunque la página se esté cerrando (`keepalive`): lo que
 * se escribió no se pierde al cerrar la pestaña.
 */
export function guardarAlSalir(id: number, datos: Partial<Redaccion>) {
  const token = useSessionStore().token
  return fetch(`${config.restUrl}${BASE}/borradores/${id}`, {
    method: 'PUT',
    keepalive: true,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: JSON.stringify(datos),
  }).catch(() => undefined)
}

/** URL absoluta de un adjunto (las de la API son relativas y firmadas). */
export const urlAdjunto = (a: Adjunto, descargar = false) => `${config.restUrl}${a.url}${descargar ? '&descargar=1' : ''}`

/** El HTML con las URL de las imágenes incrustadas completas. */
export const htmlConBase = (html: string) => html.replaceAll('{api}', config.restUrl)

/** Sube un archivo al borrador (multipart) con progreso 0–1, por XHR (fetch no informa el avance). */
export function subirAdjunto(borrador: number, archivo: File, alAvanzar?: (fraccion: number) => void): Promise<Adjunto> {
  return new Promise((resolver, rechazar) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', `${config.restUrl}${BASE}/borradores/${borrador}/adjuntos`)
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
      if (xhr.status >= 200 && xhr.status < 300) return resolver(cuerpo as Adjunto)
      const mensaje = (cuerpo as { error?: string } | null)?.error ?? (xhr.status === 413 ? 'El archivo es demasiado grande.' : `HTTP ${xhr.status}`)
      rechazar(new HttpError(xhr.status, cuerpo, mensaje))
    }
    xhr.onerror = () => rechazar(new Error('No se pudo subir el archivo (sin conexión).'))
    const datos = new FormData()
    datos.append('archivo', archivo, archivo.name)
    xhr.send(datos)
  })
}
