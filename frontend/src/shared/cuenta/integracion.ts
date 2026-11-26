/**
 * Enchufes de "Mi cuenta" con el shell. La apariencia (modo, colores, estilo)
 * vive en `app/` y una feature no importa de ahí (ADR-017): el editor le
 * llega por aquí, lo provee `AppShell`.
 */
import type { Component, InjectionKey } from 'vue'

export const EDITOR_DE_APARIENCIA: InjectionKey<Component> = Symbol('editor-de-apariencia')
