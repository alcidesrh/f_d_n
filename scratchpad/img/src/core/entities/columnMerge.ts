/**
 * Columnas del listado al recibir la configuración del servidor sobre las
 * guardadas en el navegador. El store de la entidad es persistido: sin esta
 * mezcla, un cambio de `width` o `visible` (u otra configuración) hecho en el
 * backend no llega a un navegador que ya tenía el listado.
 *
 * Lo del servidor manda, salvo lo que el usuario hizo en su vista: el orden
 * arrastrado, las columnas que mostró u ocultó él (difieren de `configVisible`)
 * y el ancho arrastrado en el borde (difiere de `configWidth`).
 */
import type { CollectionFieldConfig } from './types'

/** Marca en la columna lo que dice la configuración, para distinguir después lo que cambió el usuario. */
export function columnaDeConfig(column: CollectionFieldConfig): CollectionFieldConfig {
  return { ...column, showFilter: false, configWidth: column.width ?? null, configVisible: column.visible ?? true }
}

export function mergeColumns(saved: readonly CollectionFieldConfig[], server: readonly CollectionFieldConfig[]): CollectionFieldConfig[] {
  const fromServer = new Map(server.map((column) => [column.field, column]))
  const kept = saved.flatMap((previous) => {
    const config = fromServer.get(previous.field)
    if (!config) return []
    // Sin `configWidth`/`configVisible` (vista guardada antes de existir) no hay forma de saber si lo cambió el usuario: manda el servidor.
    const resized = previous.configWidth !== undefined && previous.width !== previous.configWidth
    const toggled = previous.configVisible !== undefined && (previous.visible ?? true) !== previous.configVisible
    const column = columnaDeConfig(config)
    return [
      {
        ...column,
        visible: toggled ? previous.visible : column.configVisible,
        width: resized ? previous.width : column.configWidth,
      },
    ]
  })
  const known = new Set(saved.map((column) => column.field))
  const added = server.filter((column) => !known.has(column.field)).map(columnaDeConfig)
  return [...kept, ...added]
}
