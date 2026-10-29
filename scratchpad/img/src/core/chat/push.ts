/**
 * Notificaciones del chat con la app cerrada (Web Push): quién escribe y un
 * fragmento del mensaje. Se activan por navegador desde «Mi cuenta» y las
 * muestra el service worker (`public/push-sw.js`), que las calla si la app
 * está a la vista. Una taquilla es de varios usuarios: al cerrar sesión se
 * quitan, y al entrar otro usuario se quita la del anterior.
 */
import { ref } from 'vue'
import { http, request } from '@/core/http'

const BASE = '/chat/push'
const fondo = { silent: true } as const

export type EstadoPush = 'no-disponible' | 'bloqueadas' | 'apagadas' | 'activas'

/** Lo que se ve en «Mi cuenta». */
export const estadoPush = ref<EstadoPush>('no-disponible')

function soportado(): boolean {
  return typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

/** El registro del service worker, si la app lo tiene (en `npm run dev` no hay). */
async function registro(): Promise<ServiceWorkerRegistration | null> {
  if (!soportado()) return null
  return (await navigator.serviceWorker.getRegistration()) ?? null
}

/** La clave VAPID llega en base64url; `subscribe()` la quiere en bytes. */
export function claveEnBytes(base64url: string): Uint8Array<ArrayBuffer> {
  const base64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/')
  return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0))
}

const quitar = (endpoint: string) => request(BASE, { method: 'DELETE', body: { endpoint }, silent: true }).catch(() => undefined)

/**
 * Al entrar (y al abrir «Mi cuenta»): averigua el estado y, si este
 * navegador quedó suscrito por otro usuario, lo da de baja.
 */
export async function revisarPush(): Promise<EstadoPush> {
  const reg = await registro()
  if (!reg) return (estadoPush.value = 'no-disponible')
  const actual = await reg.pushManager.getSubscription()
  const { clave, mia } = await http.get<{ clave: string | null; mia: boolean }>(
    `${BASE}${actual ? `?endpoint=${encodeURIComponent(actual.endpoint)}` : ''}`,
    fondo,
  )
  if (actual && !mia) {
    await quitar(actual.endpoint)
    await actual.unsubscribe()
  }
  if (!clave) return (estadoPush.value = 'no-disponible')
  if (Notification.permission === 'denied') return (estadoPush.value = 'bloqueadas')
  return (estadoPush.value = actual && mia ? 'activas' : 'apagadas')
}

export async function activarPush(): Promise<EstadoPush> {
  const reg = await registro()
  if (!reg) return (estadoPush.value = 'no-disponible')
  if ((await Notification.requestPermission()) !== 'granted') {
    return (estadoPush.value = Notification.permission === 'denied' ? 'bloqueadas' : 'apagadas')
  }
  const { clave } = await http.get<{ clave: string | null }>(BASE, fondo)
  if (!clave) return (estadoPush.value = 'no-disponible')
  let suscripcion = await reg.pushManager.getSubscription()
  // Con otra clave (se regeneraron las VAPID) la vieja ya no sirve.
  if (suscripcion && !mismaClave(suscripcion, clave)) {
    await suscripcion.unsubscribe()
    suscripcion = null
  }
  suscripcion ??= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: claveEnBytes(clave) })
  await http.post(BASE, suscripcion.toJSON(), fondo)
  return (estadoPush.value = 'activas')
}

function mismaClave(s: PushSubscription, clave: string): boolean {
  const actual = s.options.applicationServerKey
  if (!actual) return true
  const a = new Uint8Array(actual)
  const b = claveEnBytes(clave)
  return a.length === b.length && a.every((x, i) => x === b[i])
}

/** Desactivar en este navegador; también al cerrar sesión (antes del logout: después ya no hay token). */
export async function desactivarPush(): Promise<void> {
  const actual = await (await registro())?.pushManager.getSubscription()
  if (actual) {
    await quitar(actual.endpoint)
    await actual.unsubscribe().catch(() => undefined)
  }
  if (estadoPush.value === 'activas') estadoPush.value = 'apagadas'
}
