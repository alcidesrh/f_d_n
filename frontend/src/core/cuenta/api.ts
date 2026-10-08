/** "Mi cuenta": datos, foto y contraseña del usuario con sesión (`MiCuentaController`). */
import { config } from '@/core/config'
import { HttpError, http, request } from '@/core/http'
import { useSessionStore } from '@/core/auth/session'

export interface Cuenta {
  id: number
  username: string
  nombre: string
  apellido: string | null
  email: string | null
  telefono: string | null
  nit: string | null
  direccion: string | null
  /** Ruta relativa de la API (firmada, estable mientras no cambie la foto). */
  foto: string | null
  empresa: string | null
  estacion: string | null
  agencia: string | null
  roles: string[]
}

export type DatosCuenta = Pick<
  Cuenta,
  'nombre' | 'apellido' | 'email' | 'telefono' | 'nit' | 'direccion'
>

/** URL absoluta de la foto (la `<img>` no lleva Bearer: la firma va en la URL). */
export const urlFoto = (foto: string | null | undefined) =>
  foto ? `${config.restUrl}${foto}` : null

export const fetchCuenta = () => http.get<Cuenta>('/me/cuenta')
export const guardarCuenta = (datos: DatosCuenta) =>
  request<Cuenta>('/me/cuenta', { method: 'PUT', body: datos })
export const cambiarPassword = (body: { actual: string; password: string }) =>
  http.post<{ ok: true }>('/me/password', body)
export const quitarFoto = () => http.delete<Cuenta>('/me/foto')

/** Sube la foto (multipart, campo `foto`). */
export async function subirFoto(imagen: Blob, nombre: string): Promise<Cuenta> {
  const datos = new FormData()
  datos.append('foto', imagen, nombre)
  const token = useSessionStore().token
  const respuesta = await fetch(`${config.restUrl}/me/foto`, {
    method: 'POST',
    headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: datos,
  })
  const cuerpo = await respuesta.json().catch(() => null)
  if (!respuesta.ok) {
    const mensaje =
      (cuerpo as { error?: string } | null)?.error ??
      (respuesta.status === 413 ? 'La imagen es demasiado grande.' : `HTTP ${respuesta.status}`)
    throw new HttpError(respuesta.status, cuerpo, mensaje)
  }
  return cuerpo as Cuenta
}
