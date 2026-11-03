import 'vue-router'
import type { Component } from 'vue'
import type { RecursoDeRuta } from '@/core/acceso/tipos'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    /** `blank` = pantalla completa sin shell (login, 404). Por defecto, el shell de la app. */
    layout?: 'blank'
    /** Rutas accesibles sin sesión. */
    public?: boolean
    icon?: string
    label?: string
    /** Panel lateral derecho propio de la ruta (reemplaza al menú derecho). */
    panel?: { component: () => Promise<Component | { default: Component }>; width?: number }
    /** La página no se vuelve a montar al cambiar los parámetros (p. ej. el chat al cambiar de conversación). */
    conservar?: boolean
    /**
     * Capacidad que exige la pantalla (o cualquiera de varias), ADR-039: el
     * router no la abre a quien no la tiene y, con la sincronización de
     * rutas, el backend no le manda menús ni enlaces de la administración
     * que lleven a ella (ADR-049). Quien autoriza de verdad es la API.
     */
    capacidad?: string | readonly string[]
    /** Pantalla del CRUD genérico: qué exige sobre la entidad de su parámetro (ADR-049). */
    recurso?: RecursoDeRuta
  }
}
