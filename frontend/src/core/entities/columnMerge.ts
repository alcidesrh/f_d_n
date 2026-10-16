/**
 * Columnas del listado al recibir la configuración del servidor sobre las
 * guardadas en el navegador. El store de la entidad es persistido: sin esta
 * mezcla, un cambio de `width` (u otra configuración) hecho en el backend no
 * llega a un navegador que ya tenía el listado.
 *
 * Lo del servidor manda, salvo lo que el usuario hizo en su vista: el orden
 * arrastrado, las columnas ocultas y el ancho arrastrado en el borde (que se
 * distingue porque difiere de `configWidth`).
 */
import type { CollectionFieldConfig } from './types'

export function mergeColumns(saved: readonly CollectionFieldConfig[], server: readonly CollectionFieldConfig[]): CollectionFieldConfig[] {
  const fromServer = new Map(server.map((column) => [column.field, column]))
  const kept = saved.flatMap((previous) => {
    const config = fromServer.get(previous.field)
    if (!config) return []
    // Sin `configWidth` (vista guardada antes de existir) no hay forma de saber si fue arrastrado: manda el servidor.
    const resized = previous.configWidth !== undefined && previous.width !== previous.configWidth
    return [
      {
        ...config,
        visible: previous.visible,
        showFilter: false,
        width: resized ? previous.width : (config.width ?? null),
        configWidth: config.width ?? null,
      },
    ]
  })
  const known = new Set(saved.map((column) => column.field))
  const added = server
    .filter((column) => !known.has(column.field))
    .map((column) => ({ ...column, showFilter: false, configWidth: column.width ?? null }))
  return [...kept, ...added]
}
