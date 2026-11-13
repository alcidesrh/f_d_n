/**
 * Notificaciones del chat en la app móvil (ADR-060): dentro del shell de
 * Capacitor no hay Web Push, así que el teléfono se registra en Firebase
 * (FCM) y le da su token al backend (`/chat/push/dispositivo`). Android
 * muestra la notificación con la app cerrada o en segundo plano; con la app
 * a la vista no se muestra (el chat ya avisa dentro). El plugin solo se baja
 * dentro de la app. `push.ts` decide cuándo usar esto.
 */
import type { PluginListenerHandle } from '@capacitor/core'
import type { PushNotificationsPlugin } from '@capacitor/push-notifications'
import { http, request } from '@/core/http'
import type { EstadoPush } from './push'

const BASE = '/chat/push/dispositivo'
const fondo = { silent: true } as const
/** El token es de esta instalación de la app, no de un usuario. */
const CLAVE_TOKEN = 'fdn.push.dispositivo'
/** Canal de Android de las notificaciones del chat (`App\Chat\Push\Fcm::CANAL`). */
const CANAL = 'chat'
const ESPERA_TOKEN_MS = 15_000

interface PuenteCapacitor {
  isNativePlatform?: () => boolean
  getPlatform?: () => string
}

const puente = (): PuenteCapacitor | undefined => (globalThis as { Capacitor?: PuenteCapacitor }).Capacitor

/** La app corre dentro del shell nativo (y no en un navegador o como PWA). */
export function esAppNativa(): boolean {
  return puente()?.isNativePlatform?.() === true
}

export type Permiso = 'prompt' | 'prompt-with-rationale' | 'granted' | 'denied'

/** Lo que se ve en «Mi cuenta» según lo que dicen el backend y el teléfono. */
export function estadoNativo(e: { disponible: boolean; permiso: Permiso; token: string | null; mio: boolean }): EstadoPush {
  if (!e.disponible) return 'no-disponible'
  if (e.permiso === 'denied') return 'bloqueadas'
  return e.permiso === 'granted' && e.token !== null && e.mio ? 'activas' : 'apagadas'
}

/** A dónde lleva tocar una notificación: solo rutas de la app (`/chat/7`), nunca otro sitio. */
export function rutaDeAviso(datos: unknown): string | null {
  const url = (datos as { url?: unknown } | null | undefined)?.url
  return typeof url === 'string' && /^\/(?!\/)/.test(url) && !url.includes('\\') ? url : null
}

function leerToken(): string | null {
  try {
    return localStorage.getItem(CLAVE_TOKEN)
  } catch {
    return null
  }
}

function guardarToken(token: string | null): void {
  try {
    if (token === null) localStorage.removeItem(CLAVE_TOKEN)
    else localStorage.setItem(CLAVE_TOKEN, token)
  } catch {
    // Sin almacenamiento: se registrará de nuevo la próxima vez.
  }
}

let plugin: Promise<PushNotificationsPlugin> | null = null
const cargar = () => (plugin ??= import('@capacitor/push-notifications').then((m) => m.PushNotifications))

const quitar = (token: string) => request(BASE, { method: 'DELETE', body: { token }, silent: true }).catch(() => undefined)

const registrar = (token: string) => http.post(BASE, { token, plataforma: puente()?.getPlatform?.() ?? 'android' }, fondo)

/** Registra el teléfono en Firebase y espera su token. */
async function pedirToken(p: PushNotificationsPlugin): Promise<string> {
  const escuchas: PluginListenerHandle[] = []
  try {
    return await new Promise<string>((resolve, reject) => {
      const espera = setTimeout(() => reject(new Error('Firebase no respondió.')), ESPERA_TOKEN_MS)
      const terminar = () => clearTimeout(espera)
      Promise.all([
        p.addListener('registration', (t) => (terminar(), resolve(t.value))),
        p.addListener('registrationError', (e) => (terminar(), reject(new Error(e.error)))),
      ])
        .then((manejadores) => {
          escuchas.push(...manejadores)
          return p.register()
        })
        .catch((e: unknown) => (terminar(), reject(e instanceof Error ? e : new Error(String(e)))))
    })
  } finally {
    for (const e of escuchas) void e.remove()
  }
}

let renovado = false

/** Una vez por arranque: Firebase puede haber cambiado el token de esta instalación. */
async function renovar(p: PushNotificationsPlugin, anterior: string): Promise<void> {
  if (renovado) return
  renovado = true
  const token = await pedirToken(p)
  if (token === anterior) return
  await registrar(token)
  guardarToken(token)
  await quitar(anterior)
}

/**
 * Al entrar (y al abrir «Mi cuenta»): averigua el estado y, si este teléfono
 * quedó registrado por otro usuario, lo da de baja.
 */
export async function revisarNativo(): Promise<EstadoPush> {
  let token = leerToken()
  const { disponible, mio } = await http.get<{ disponible: boolean; mio: boolean }>(
    `${BASE}${token ? `?token=${encodeURIComponent(token)}` : ''}`,
    fondo,
  )
  if (token && !mio) {
    await quitar(token)
    guardarToken((token = null))
  }
  // Sin Firebase configurado no se toca el plugin: no tendría con qué registrarse.
  if (!disponible) return 'no-disponible'
  const p = await cargar()
  const permiso = (await p.checkPermissions()).receive
  const estado = estadoNativo({ disponible, permiso, token, mio })
  if (estado === 'activas' && token) void renovar(p, token).catch(() => undefined)
  return estado
}

export async function activarNativo(): Promise<EstadoPush> {
  const { disponible } = await http.get<{ disponible: boolean }>(BASE, fondo)
  if (!disponible) return 'no-disponible'
  const p = await cargar()
  const permiso = (await p.requestPermissions()).receive
  if (permiso !== 'granted') return permiso === 'denied' ? 'bloqueadas' : 'apagadas'
  // Importancia alta: suena y asoma arriba, como una app de mensajería.
  await p.createChannel({ id: CANAL, name: 'Mensajes del chat', description: 'Quién escribe y el inicio del mensaje', importance: 4 })
  const token = await pedirToken(p)
  await registrar(token)
  const anterior = leerToken()
  guardarToken(token)
  if (anterior && anterior !== token) await quitar(anterior)
  renovado = true
  return 'activas'
}

/** Desactivar en este teléfono; también al cerrar sesión (antes del logout). */
export async function desactivarNativo(): Promise<void> {
  const token = leerToken()
  if (!token) return
  await quitar(token)
  guardarToken(null)
}

/** Tocaron una notificación (con la app abierta, en segundo plano o cerrada): `abrir` recibe la ruta. */
export async function alTocarAviso(abrir: (ruta: string) => void): Promise<void> {
  if (!esAppNativa()) return
  const p = await cargar()
  await p.addListener('pushNotificationActionPerformed', (accion) => {
    const ruta = rutaDeAviso(accion.notification.data)
    if (ruta) abrir(ruta)
  })
}
