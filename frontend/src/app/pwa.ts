/**
 * Instalar la app (PWA): Chrome/Edge/Android avisan con `beforeinstallprompt`,
 * que se guarda para lanzarlo desde el menú del usuario; en iOS se explica
 * cómo. Estado de toda la app (no de un usuario), por eso no es una store.
 * También: abrir la conversación al tocar una notificación del chat.
 */
import { computed, shallowRef } from 'vue'
import { router } from '@/app/router'
import { notify } from '@/core/notify'
import { GUIA_IOS, pideGuiaIos } from '@/core/pwa/instalacion'

interface AvisoInstalacion extends Event {
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const aviso = shallowRef<AvisoInstalacion | null>(null)
const guiaIos = shallowRef(false)

/** Se puede ofrecer «Instalar app». */
export const puedeInstalar = computed(() => aviso.value !== null || guiaIos.value)

function instalada(): boolean {
  return window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true
}

/** Al arrancar: el aviso del navegador llega una vez y pronto. */
export function escucharInstalacion(): void {
  if (typeof window === 'undefined') return
  guiaIos.value = pideGuiaIos(navigator.userAgent, navigator.maxTouchPoints, instalada())
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    // Abierta desde el ícono: ya está instalada. Chrome sigue avisando cuando la instaló
    // como acceso directo (sin HTTPS público no puede crear la app de Android).
    if (!instalada()) aviso.value = e as AvisoInstalacion
  })
  window.addEventListener('appinstalled', () => {
    aviso.value = null
    guiaIos.value = false
  })
  // `public/push-sw.js`: tocaron una notificación con la app abierta; se navega sin recargarla.
  navigator.serviceWorker?.addEventListener('message', (e: MessageEvent<{ tipo?: string; url?: string }>) => {
    if (e.data?.tipo === 'abrir' && e.data.url?.startsWith('/')) void router.push(e.data.url)
  })
}

export async function instalar(): Promise<void> {
  const actual = aviso.value
  if (!actual) {
    if (guiaIos.value) notify.info(GUIA_IOS)
    return
  }
  await actual.prompt()
  // El aviso sirve una sola vez, se acepte o no.
  aviso.value = null
}
