import type { Idioma } from '..'
import type { Contenido } from './tipos'
import es from './es'
import en from './en'
import fr from './fr'
import de from './de'
import it from './it'

const CONTENIDO: Record<Idioma, Contenido> = { es, en, fr, de, it }

export const contenido = (idioma: Idioma): Contenido => CONTENIDO[idioma] ?? es
export { CONTACTO } from './tipos'
export type { Contenido, Seccion, Servicio } from './tipos'
