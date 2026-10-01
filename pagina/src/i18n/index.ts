/**
 * Idiomas de la página (ADR-023). El idioma va en la URL (`/pagina/en/...`):
 * cada página tiene su versión indexable. Sin idioma en la URL se usa el
 * elegido antes (localStorage) o el del navegador; por defecto, español.
 *
 * Los textos de la interfaz están en `mensajes/*.ts` (vue-i18n); los textos
 * largos de las páginas informativas, en `contenido/*.ts`.
 */
import { createI18n } from 'vue-i18n'
import es from './mensajes/es'
import en from './mensajes/en'
import fr from './mensajes/fr'
import de from './mensajes/de'
import it from './mensajes/it'

export const IDIOMAS = ['es', 'en', 'fr', 'de', 'it'] as const
export type Idioma = (typeof IDIOMAS)[number]
export const IDIOMA_DEFECTO: Idioma = 'es'

/** Nombre de cada idioma en su propio idioma (selector). */
export const NOMBRES_IDIOMA: Record<Idioma, string> = {
  es: 'Español',
  en: 'English',
  fr: 'Français',
  de: 'Deutsch',
  it: 'Italiano',
}

/** Configuración regional para fechas y montos (la moneda es siempre GTQ). */
export const REGION: Record<Idioma, string> = {
  es: 'es-GT',
  en: 'en-US',
  fr: 'fr-FR',
  de: 'de-DE',
  it: 'it-IT',
}

export type Mensajes = typeof es

export const esIdioma = (v: unknown): v is Idioma => typeof v === 'string' && (IDIOMAS as readonly string[]).includes(v)

const CLAVE = 'fdn.idioma'

export function idiomaGuardado(): Idioma | null {
  try {
    const v = localStorage.getItem(CLAVE)
    return esIdioma(v) ? v : null
  } catch {
    return null
  }
}

export function guardarIdioma(idioma: Idioma) {
  try {
    localStorage.setItem(CLAVE, idioma)
  } catch {
    // sin almacenamiento: se usa el de la URL
  }
}

/** Primer idioma del navegador que la página tiene (`en-US` → `en`). */
export function idiomaDelNavegador(preferidos: readonly string[] = typeof navigator !== 'undefined' ? navigator.languages ?? [navigator.language] : []): Idioma {
  for (const p of preferidos) {
    const base = p?.toLowerCase().split('-')[0]
    if (esIdioma(base)) return base
  }
  return IDIOMA_DEFECTO
}

export const idiomaInicial = (): Idioma => idiomaGuardado() ?? idiomaDelNavegador()

export function crearI18n(idioma: Idioma = IDIOMA_DEFECTO) {
  return createI18n<[Mensajes], Idioma, false>({
    legacy: false,
    locale: idioma,
    fallbackLocale: IDIOMA_DEFECTO,
    messages: { es, en, fr, de, it },
    missingWarn: false,
    fallbackWarn: false,
  })
}
