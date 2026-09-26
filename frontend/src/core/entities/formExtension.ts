/**
 * Secciones extra del formulario genérico de una entidad (p. ej. el croquis
 * del bus). La sección vive en su feature; `EntityForm` la monta en su propia
 * pestaña y la sección se engancha al guardado con `useEntityFormExtension`:
 *
 * 1. `validate()` de cada sección antes de guardar (un mensaje cancela).
 * 2. Guardado genérico (`create`/`update`) de la entidad.
 * 3. `afterSave(item)` de cada sección con el registro guardado (su IRI).
 *
 * Cada sección se registra bajo su clave (`scopedEntityFormExtensionHost`),
 * así el formulario sabe cuál bloqueó o falló y puede mostrarla.
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

/** Hooks con la clave de la sección que los registró. */
export type KeyedEntityFormExtensionHooks = EntityFormExtensionHooks & { key?: string }

export interface EntityFormExtensionHost {
  register(hooks: KeyedEntityFormExtensionHooks): () => void
}

export const ENTITY_FORM_EXTENSION: InjectionKey<EntityFormExtensionHost> =
  Symbol('entity-form-extension')

/** Error de `afterSave` con la clave de la sección que falló. */
export class EntityFormExtensionError extends Error {
  constructor(
    readonly key: string | undefined,
    cause: unknown,
  ) {
    super(cause instanceof Error ? cause.message : String(cause))
  }
}

/** Engancha la sección al guardado del formulario que la contiene. */
export function useEntityFormExtension(hooks: EntityFormExtensionHooks): void {
  const host = inject(ENTITY_FORM_EXTENSION, null)
  if (!host) return
  const unregister = host.register(hooks)
  onBeforeUnmount(unregister)
}

/** Host hijo que registra los hooks de una sección bajo su clave. */
export function scopedEntityFormExtensionHost(
  parent: EntityFormExtensionHost,
  key: string,
): EntityFormExtensionHost {
  return { register: (hooks) => parent.register({ ...hooks, key }) }
}

/** Registro de secciones de un formulario y ejecución de sus hooks en orden. */
export function createEntityFormExtensionHost() {
  const hooks = new Set<KeyedEntityFormExtensionHooks>()
  return {
    register(entry: KeyedEntityFormExtensionHooks) {
      hooks.add(entry)
      return () => void hooks.delete(entry)
    },
    /** Primera sección que impide guardar, con su mensaje. */
    validate(): { key?: string; message: string } | null {
      for (const entry of hooks) {
        const message = entry.validate?.()
        if (message) return { key: entry.key, message }
      }
      return null
    },
    /** @throws EntityFormExtensionError con la sección que falló */
    async afterSave(item: Record<string, unknown>): Promise<void> {
      for (const entry of hooks) {
        try {
          await entry.afterSave?.(item)
        } catch (cause) {
          throw new EntityFormExtensionError(entry.key, cause)
        }
      }
    },
  }
}
