/**
 * Manual de uso dentro del dashboard (`/ayuda`): guías cortas por tema para
 * quien viene del sistema anterior. El contenido vive en `features/ayuda/contenido/`.
 */
import type { RouteLocationRaw } from 'vue-router'
import type { Exige } from '@/core/acceso/tipos'

/** A quién va dirigida una guía: un puesto descrito por lo que puede hacer. */
export interface Audiencia {
  etiqueta: string
  exige: Exige
}

export interface Tema {
  id: string
  titulo: string
  icono: string
}

export interface Guia {
  id: string
  tema: string
  titulo: string
  /** Qué resuelve, en una línea. */
  resumen: string
  /** Vacío: para todos. Si no, la ve quien encaja en alguna. */
  para: Audiencia[]
  /** Cómo se hacía en el sistema anterior y qué cambió. */
  antes?: string
  pasos?: string[]
  /** Equivalencias sistema anterior → sistema nuevo. */
  tabla?: { antes: string; ahora: string }[]
  ejemplo?: { caso: string; pasos: string[] }
  /** Lo que suele confundir. */
  ojo?: string[]
  /** Pantalla donde se hace. */
  abrir?: { etiqueta: string; destino: RouteLocationRaw }
  /** Claves de ruta (`nombre` o `nombre:entidad`) donde la guía es ayuda contextual. */
  pantallas?: string[]
  /** Otros nombres con que se busca (los del sistema anterior). */
  palabras?: string[]
}
