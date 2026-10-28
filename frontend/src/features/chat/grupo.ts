/** Textos de los modos de grupo, los mismos al crear y al editar. */
import type { ModoGrupo } from '@/core/chat/types'

export const MODOS_GRUPO: { valor: ModoGrupo; nombre: string; detalle: string }[] = [
  { valor: 'abierto', nombre: 'Abierto', detalle: 'Todos los miembros envían y reciben.' },
  { valor: 'dirigido', nombre: 'Dirigido', detalle: 'Todos reciben; solo envían el dueño y quienes él elija.' },
]
