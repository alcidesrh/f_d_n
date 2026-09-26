/**
 * Secciones extra del formulario genérico de una entidad (p. ej. el croquis
 * del bus). La sección vive en su feature; `EntityForm` la monta debajo de los
 * campos y la sección se engancha al guardado con `useEntityFormExtension`:
 *
 * 1. `validate()` de cada sección antes de guardar (un mensaje cancela).
 * 2. Guardado genérico (`create`/`update`) de la entidad.
 * 3. `afterSave(item)` de cada sección con el registro guardado (su IRI).
 *
 * El contrato vive en `core` para que la feature genérica y la dueña de la
 * sección no se importen entre sí (ADR-017).
 */
import { inject, onBeforeUnmount, type InjectionKey } from 'vue'

export interface EntityFormExtensionHooks {
  /** Mensaje que impide guardar, o null. */
  validate?: () => string | null
  /** Tras guardar la entidad; un error se muestra y el formulario sigue abierto. */
  afterSave?: (item: Record<string, unknown>) => Promise<void>
}

export interface EntityFormExtensionHost {
  register(hooks: EntityFormExtensionHooks): () => void
}

export const ENTITY_FORM_EXTENSION: InjectionKey<EntityFormExtensionHost> =
  Symbol('entity-form-extension')

/** Engancha la sección al guardado del formulario que la contiene. */
export function useEntityFormExtension(hooks: EntityFormExtensionHooks): void {
  const host = inject(ENTITY_FORM_EXTENSION, null)
  if (!host) return
  const unregister = host.register(hooks)
  onBeforeUnmount(unregister)
}

/** Registro de secciones de un formulario y ejecución de sus hooks en orden. */
export function createEntityFormExtensionHost() {
  const hooks = new Set<EntityFormExtensionHooks>()
  return {
    register(entry: EntityFormExtensionHooks) {
      hooks.add(entry)
      return () => void hooks.delete(entry)
    },
    validate(): string | null {
      for (const entry of hooks) {
        const message = entry.validate?.()
        if (message) return message
      }
      return null
    },
    async afterSave(item: Record<string, unknown>): Promise<void> {
      for (const entry of hooks) await entry.afterSave?.(item)
    },
  }
}
