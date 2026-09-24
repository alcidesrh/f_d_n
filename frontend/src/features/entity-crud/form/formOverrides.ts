/**
 * Formularios dedicados por entidad. El genérico (`EntityForm`) cubre casi
 * todas; si una entidad necesita reglas propias, se registra aquí un
 * componente con la misma interfaz (props `entity` e `id`, mismos eventos).
 *
 *   Menu: () => import("@/features/menu/MenuForm.vue"),
 */
import type { Component } from 'vue'

export type EntityFormLoader = () => Promise<Component | { default: Component }>

/** Clave = nombre de la entidad en PascalCase. */
export const entityFormOverrides: Record<string, EntityFormLoader> = {}
