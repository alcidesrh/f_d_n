/**
 * La apariencia del usuario (Mi cuenta → Apariencia): lo elegido, aplicarlo a
 * la página y guardarlo. Qué se puede elegir y cómo se traduce a CSS está en
 * `ajustes.ts`; el preset de PrimeVue, en `preset.ts`.
 *
 * Aplicar: el modo como clase de <html> (`light`/`dark`), los parámetros CSS
 * (`--apariencia-*`) en el `style` de <html> —solo los que no son de fábrica—
 * y, si cambió el modo, un color o el estilo, el preset de PrimeVue.
 *
 * Guardar, en dos sitios:
 *  - En el navegador (persistida por usuario, como toda store): es la que se
 *    aplica al arrancar, sin esperar a la red; el modo, incluso antes de cargar
 *    la app (index.html), sin destello.
 *  - En la cuenta (`PUT /me/apariencia`): así lo elegido sigue al usuario en
 *    otro equipo, navegador o en la app móvil. Llega con `GET /me/cuenta`
 *    (`recibirDeLaCuenta`, lo llama el shell) y gana sobre la del navegador,
 *    salvo que aquí haya un cambio sin confirmar (`pendiente`): entonces se
 *    manda este. Un cambio sale al rato (`ESPERA_MS`) y, si falla, al cambiar
 *    otra cosa, al volver a cargar la cuenta o antes de cerrar sesión.
 *    Suplantando a otro usuario no se manda nada: lo que cambie quien suplanta
 *    queda en este navegador.
 *
 * `permanente`: la usa la app entera; al entrar otro usuario se recarga con lo
 * suyo (core/auth/alcanceSesion.ts).
 */
import { defineStore } from 'pinia'
import { usePreset } from '@primeuix/themes'
import { claveDeUsuario } from '@/core/auth/alcanceSesion'
import { antesDeCerrarSesion, useSessionStore } from '@/core/auth/session'
import { guardarApariencia } from '@/core/cuenta/api'
import {
  AJUSTES,
  CLAVES_DEL_PRESET,
  deFabrica,
  desdeAnterior,
  iguales,
  normalizar,
  variablesCss,
  type Ajustes,
  type ClaveAjuste,
  type Modo,
} from './ajustes'
import { presetCompleto } from './preset'

/** Lo que guardaba la store `ui` antes (`app/ui.ts`): se lee una vez y se borra. */
const CLAVE_ANTERIOR = 'ui'
/** Un cambio se manda a la cuenta tras este rato sin otro cambio (un usuario probando opciones manda uno). */
const ESPERA_MS = 1200
/** Color de la barra del navegador (meta `theme-color`): el de la cabecera. */
const COLOR_DE_LA_BARRA: Record<Modo, string> = { light: '#f8fafc', dark: '#000000' }

/** Primera visita: el modo del sistema operativo. */
function modoDelSistema(): Modo {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return 'light'
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function leerAnterior(): unknown {
  try {
    return JSON.parse(localStorage.getItem(claveDeUsuario(CLAVE_ANTERIOR)) ?? 'null')
  } catch {
    return null
  }
}

export interface EstadoApariencia {
  ajustes: Ajustes
  /** Hay un cambio que la cuenta todavía no confirmó. */
  pendiente: boolean
}

/** Lo que hay en el navegador al crear la store (antes de hidratarla con lo persistido). */
function estadoInicial(): EstadoApariencia {
  const fabrica = deFabrica(modoDelSistema())
  const anterior = desdeAnterior(leerAnterior())
  const ajustes = normalizar(anterior, fabrica)
  // Lo elegido con la versión anterior sube a la cuenta; el modo, solo si no es el del sistema.
  return { ajustes, pendiente: !iguales(ajustes, fabrica) }
}

let turnoPreset = 0
let presetAplicado: string | null = null
let temporizador: ReturnType<typeof setTimeout> | undefined

const firmaDelPreset = (a: Ajustes) => CLAVES_DEL_PRESET.map((c) => a[c]).join('|')

/** Los parámetros CSS en el `style` de <html>: los que no son de fábrica (los de fábrica, de `apariencia.css`). */
function aplicarVariables(ajustes: Ajustes): void {
  if (typeof document === 'undefined') return
  const estilo = document.documentElement.style
  for (const [nombre, valor] of Object.entries(variablesCss(ajustes))) {
    if (valor === null) estilo.removeProperty(nombre)
    else estilo.setProperty(nombre, valor)
  }
}

/** El modo como clase de <html> (la leen `dark:`, `color-scheme` y el mapa) y la barra del navegador. */
function aplicarModo(modo: Modo): void {
  if (typeof document === 'undefined') return
  document.documentElement.classList.toggle('dark', modo === 'dark')
  document.documentElement.classList.toggle('light', modo !== 'dark')
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', COLOR_DE_LA_BARRA[modo])
}

export const useAparienciaStore = defineStore('apariencia', {
  // La usa la app entera: al entrar otro usuario se recarga con su apariencia.
  permanente: { alRecargar: (): void => void useAparienciaStore().init() },
  persist: {
    pick: ['ajustes', 'pendiente'],
    // Lo guardado puede traer opciones que ya no existen: esas vuelven a la de fábrica.
    afterHydrate: ({ store }) => {
      store.ajustes = normalizar(store.ajustes, deFabrica(modoDelSistema()))
    },
  },
  state: estadoInicial,

  getters: {
    modo: (s): Modo => s.ajustes.modo,
    esDeFabrica: (s): boolean => iguales({ ...s.ajustes, modo: 'light' }, deFabrica('light')),
  },

  actions: {
    /** Cambia un ajuste: se ve al instante y se guarda (en el navegador ya, en la cuenta al rato). */
    cambiar<K extends ClaveAjuste>(clave: K, valor: Ajustes[K]) {
      if (!AJUSTES[clave].opciones.some((o) => o.valor === valor) || this.ajustes[clave] === valor) return
      this.ajustes = { ...this.ajustes, [clave]: valor }
      this.alCambiar()
    },
    /** Todo a la apariencia de fábrica, salvo el modo claro u oscuro. */
    restablecer() {
      const fabrica = deFabrica(this.ajustes.modo)
      if (iguales(this.ajustes, fabrica)) return
      this.ajustes = fabrica
      this.alCambiar()
    },

    alCambiar() {
      void this.aplicar()
      // Suplantando, lo cambiado queda en este navegador: no es una elección del usuario.
      if (useSessionStore().suplantando) return
      this.pendiente = true
      clearTimeout(temporizador)
      temporizador = setTimeout(() => void this.guardarPendiente(), ESPERA_MS)
    },

    /**
     * Aplica `ajustes` a la página. El preset solo se regenera si cambió lo que
     * entra en él; se baja al pedirlo, así que si llegan dos seguidos gana el último.
     */
    async aplicar() {
      const ajustes = this.ajustes
      const turno = ++turnoPreset
      aplicarVariables(ajustes)
      const firma = firmaDelPreset(ajustes)
      if (firma !== presetAplicado) {
        const preset = await presetCompleto(ajustes)
        if (turno !== turnoPreset) return
        usePreset(preset)
        presetAplicado = firma
      }
      // La clase del modo junto con su preset: sin un instante de colores cruzados.
      aplicarModo(ajustes.modo)
    },

    /** Al arrancar y al entrar otro usuario: aplica lo de este navegador y deja la copia vieja en la nueva clave. */
    async init() {
      const anterior = claveDeUsuario(CLAVE_ANTERIOR)
      if (localStorage.getItem(anterior) !== null) {
        this.$persist()
        localStorage.removeItem(anterior)
      }
      await this.aplicar()
    },

    /**
     * La apariencia guardada en la cuenta (`GET /me/cuenta`): si difiere, se
     * aplica. Con un cambio local sin confirmar, en cambio, se manda este.
     */
    recibirDeLaCuenta(remota: unknown) {
      if (this.pendiente) return void this.guardarPendiente()
      if (!remota || typeof remota !== 'object') return
      const ajustes = normalizar(remota, this.ajustes)
      if (iguales(ajustes, this.ajustes)) return
      this.ajustes = ajustes
      void this.aplicar()
    },

    /** Manda a la cuenta el cambio pendiente, si lo hay. */
    async guardarPendiente() {
      clearTimeout(temporizador)
      const session = useSessionStore()
      if (!this.pendiente || !session.user || session.suplantando) return
      const enviados = this.ajustes
      try {
        await guardarApariencia(enviados)
        // Si cambió algo mientras salía, sigue pendiente (lo manda su propio temporizador).
        if (this.ajustes === enviados) this.pendiente = false
      } catch (e) {
        console.warn('[apariencia] no se pudo guardar en la cuenta; se reintenta con el próximo cambio:', e)
      }
    },
  },
})

// Lo cambiado en los últimos segundos sale antes del logout.
antesDeCerrarSesion(() => useAparienciaStore().guardarPendiente())
