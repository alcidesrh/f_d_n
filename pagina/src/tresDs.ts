/**
 * Pasos de 3-D Secure que hace el navegador (ADR-021). Los datos de la
 * tarjeta no pasan por aquí: solo los JWT que entrega la pasarela.
 */
import { deOrigen } from './modelo'
import type { DatosNavegador } from './tipos'

/** Envía `campos` por POST a `url`, dentro del iframe `destino`. */
export function enviarFormulario(url: string, campos: Record<string, string>, destino: string) {
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = url
  form.target = destino
  form.style.display = 'none'
  for (const [nombre, valor] of Object.entries(campos)) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = nombre
    input.value = valor
    form.appendChild(input)
  }
  document.body.appendChild(form)
  form.submit()
  form.remove()
}

/**
 * Recolección de datos del dispositivo: iframe oculto; termina con el aviso
 * del banco o a los `limiteMs` (el pago sigue igual: el banco decide si pide
 * desafío).
 */
export function recolectarDispositivo(url: string, campos: Record<string, string>, origenes: string[], limiteMs = 10000): Promise<void> {
  return new Promise((resolve) => {
    const nombre = `ddc-${Date.now()}`
    const iframe = document.createElement('iframe')
    iframe.name = nombre
    iframe.title = 'Verificación del dispositivo'
    iframe.setAttribute('aria-hidden', 'true')
    iframe.style.cssText = 'position:absolute;width:1px;height:1px;border:0;left:-9999px'
    document.body.appendChild(iframe)

    let listo = false
    const terminar = () => {
      if (listo) return
      listo = true
      window.removeEventListener('message', alRecibir)
      window.clearTimeout(reloj)
      iframe.remove()
      resolve()
    }
    const alRecibir = (e: MessageEvent) => {
      if (deOrigen(e.origin, origenes)) terminar()
    }
    window.addEventListener('message', alRecibir)
    const reloj = window.setTimeout(terminar, limiteMs)
    enviarFormulario(url, campos, nombre)
  })
}

export function datosNavegador(): DatosNavegador {
  return {
    anchoPantalla: window.screen?.width ?? 0,
    altoPantalla: window.screen?.height ?? 0,
    profundidadColor: window.screen?.colorDepth ?? 24,
    diferenciaHoraria: new Date().getTimezoneOffset(),
    idioma: navigator.language ?? 'es-GT',
  }
}

/** Carga (una vez) el script de huella del dispositivo del antifraude. */
export function cargarHuella(script: string) {
  if (document.querySelector(`script[data-huella]`)) return
  const s = document.createElement('script')
  s.src = script
  s.async = true
  s.dataset.huella = '1'
  document.head.appendChild(s)
}
