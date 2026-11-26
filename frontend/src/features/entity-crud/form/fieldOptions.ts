/**
 * Campos que no son relaciones pero se eligen de una lista que da el backend
 * (p. ej. códigos de capacidad). Con sus opciones, el formulario genérico los
 * muestra como `Select` (o `MultiSelect` si guardan una lista) en vez de
 * texto. Si la lista no carga, el campo queda como texto.
 */
import { fetchCapacidades } from '@/core/afectacion/api'
import type { FormFieldSource, FieldOption } from './formSchema'

export interface EntityFieldOptions {
  campos: string[]
  cargar: () => Promise<FieldOption[]>
}

/** Clave = nombre de la entidad en PascalCase. */
export const entityFieldOptions: Record<string, EntityFieldOptions> = {
  // ADR-070: quién reporta (`owner`), quién atiende (`target`) y a quién se escala.
  TipoAfectacion: {
    campos: ['owner', 'target', 'escalamiento'],
    cargar: async () => (await fetchCapacidades()).map((c) => ({ label: `${c.area} · ${c.nombre}`, value: c.codigo })),
  },
}

/** Las opciones de los campos de `fields` que las tienen (`campo → options`), sin fallar si la lista no carga. */
export async function loadFieldOptions(entityName: string, fields: FormFieldSource[]): Promise<Record<string, FieldOption[]>> {
  const registro = entityFieldOptions[entityName]
  const campos = registro ? fields.filter((f) => !f.isRelation && registro.campos.includes(f.name)) : []
  if (!registro || campos.length === 0) return {}
  try {
    const opciones = await registro.cargar()
    return Object.fromEntries(campos.map((f) => [f.name, opciones]))
  } catch (cause) {
    console.warn(`[useEntityForm] sin opciones fijas para "${entityName}":`, cause)
    return {}
  }
}
