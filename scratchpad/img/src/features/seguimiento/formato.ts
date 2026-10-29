const hora = new Intl.DateTimeFormat('es-GT', { hour: '2-digit', minute: '2-digit', hour12: false })

export const horaDe = (epochSeg: number): string => hora.format(new Date(epochSeg * 1000))

/** "2 h 05 min", "35 min", "llegando". */
export function duracion(segundos: number): string {
  if (segundos < 60) return 'llegando'
  const min = Math.round(segundos / 60)
  const h = Math.floor(min / 60)
  return h > 0 ? `${h} h ${String(min % 60).padStart(2, '0')} min` : `${min} min`
}

export const ETIQUETA_ESTADO = {
  por_salir: 'En estación, por salir',
  en_ruta: 'En ruta',
  detenido: 'Detenido en estación',
  llego: 'Llegó a su destino',
} as const

export const ORIGEN_COORDENADA: Record<string, string> = {
  gps: 'Coordenada de la estación',
  catalogo: 'Ubicación aproximada (por nombre)',
  departamento: 'Ubicación aproximada (centro del departamento)',
}
