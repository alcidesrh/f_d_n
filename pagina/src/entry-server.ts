/**
 * Render de servidor para el prerender (ADR-023): `scripts/prerender.mjs`
 * lo usa para escribir el HTML de cada página pública en cada idioma, con
 * su `<head>` (título, descripción, canónica, hreflang) y el CSS del tema
 * de PrimeVue, para que los buscadores lean el contenido sin ejecutar JS.
 */
import { renderToString } from 'vue/server-renderer'
import { createMemoryHistory } from 'vue-router'
import BaseStyle from '@primevue/core/base/style'
import BaseComponentStyle from '@primevue/core/basecomponent/style'
import ButtonStyle from 'primevue/button/style'
import DatePickerStyle from 'primevue/datepicker/style'
import IconFieldStyle from 'primevue/iconfield/style'
import InputTextStyle from 'primevue/inputtext/style'
import MessageStyle from 'primevue/message/style'
import SelectStyle from 'primevue/select/style'
import SkeletonStyle from 'primevue/skeleton/style'
import TextareaStyle from 'primevue/textarea/style'
import ToggleSwitchStyle from 'primevue/toggleswitch/style'
import { crearApp } from './app'
import { IDIOMAS, type Idioma } from './i18n'
import { ESTATICAS } from './router'
import { head, headHtml } from './seo'

export { ESTATICAS, IDIOMAS }

/** Lo que PrimeVue expone (sin declararlo en sus tipos) para el render de servidor. */
interface EstiloPrime {
  getStyleSheet(): string
  getThemeStyleSheet(): string
}

/** Componentes de PrimeVue que aparecen en el HTML prerenderizado. */
const ESTILOS = [BaseComponentStyle, ButtonStyle, DatePickerStyle, IconFieldStyle, InputTextStyle, MessageStyle, SelectStyle, SkeletonStyle, TextareaStyle, ToggleSwitchStyle] as unknown as EstiloPrime[]
const Base = BaseStyle as unknown as { getCommonThemeStyleSheet(): string }

/** `ruta`: sin idioma (`''`, `'servicios'`…). */
export async function render(idioma: Idioma, ruta: string, opciones: { origen: string; directorio?: unknown }) {
  const { app, router, i18n } = crearApp(idioma, createMemoryHistory('/pagina/'), { ssr: true })
  app.provide('directorio', opciones.directorio ?? null)
  await router.push(`/${idioma}/${ruta}`)
  await router.isReady()

  const html = await renderToString(app)
  const r = router.currentRoute.value
  const h = head((clave) => i18n.global.t(clave), {
    idioma,
    pagina: r.meta.seo ?? 'inicio',
    ruta,
    origen: opciones.origen,
    indexable: !!r.meta.indexable,
  })

  // El tema ya quedó registrado al instalar PrimeVue (crearApp).
  const estilos = [Base.getCommonThemeStyleSheet(), ...ESTILOS.flatMap((s) => [s.getStyleSheet(), s.getThemeStyleSheet()])].join('')

  return { html, head: headHtml(h), estilos }
}
