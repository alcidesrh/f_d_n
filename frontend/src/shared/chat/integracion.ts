/**
 * Enchufes del chat con el resto de la app (ADR-026). El chat es una feature
 * y no importa a otras (ADR-017): lo que necesita de ellas le llega por aquí.
 *
 * - `LISTADO_DE_REGISTROS`: el listado genérico de una entidad (filtros,
 *   orden, páginas) en modo selección; lo provee el shell y lo usa el
 *   selector de registros del redactor. Props: `entity` (nombre de la
 *   entidad) y `v-model:seleccion` (filas elegidas, de cualquier página).
 * - Registros en pantalla: cada página anuncia lo que muestra (el registro
 *   del formulario, la selección de un listado, la salida abierta) y el
 *   redactor lo ofrece con un clic mientras el chat flota encima.
 */
import { computed, onScopeDispose, reactive, type Component, type InjectionKey } from 'vue'
import type { Registro } from '@/core/chat/types'

export const LISTADO_DE_REGISTROS: InjectionKey<Component> = Symbol('listado-de-registros')

const fuentes = reactive(new Map<symbol, () => Registro[]>())

/** Anuncia lo que muestra el componente que lo llama, mientras viva. */
export function anunciarEnPantalla(registros: () => Registro[]) {
  const clave = Symbol()
  fuentes.set(clave, registros)
  onScopeDispose(() => fuentes.delete(clave))
}

/** Lo que hay en pantalla ahora, sin repetidos. */
export const enPantalla = computed<Registro[]>(() => {
  const vistos = new Map<string, Registro>()
  for (const fuente of fuentes.values()) for (const r of fuente()) vistos.set(`${r.tipo}:${r.id}`, r)
  return [...vistos.values()]
})

/** Etiqueta legible de una fila cualquiera de un listado o formulario. */
export function etiquetaDeFila(fila: Record<string, unknown>, id: number, tipo: string): string {
  for (const campo of ['label', 'nombre', 'name', 'titulo', 'codigo', 'matricula', 'username']) {
    const v = fila[campo]
    // Un código suelto ("41") no dice qué es: "Bus 41".
    if (typeof v === 'string' && v.trim()) return /^\d+$/.test(v.trim()) ? `${nombre(tipo)} ${v.trim()}` : v.trim()
  }
  return `${nombre(tipo)} ${id}`
}

const nombre = (tipo: string) => tipo.replace(/([a-z])([A-Z])/g, '$1 $2')
