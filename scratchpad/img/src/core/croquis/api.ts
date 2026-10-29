/**
 * Transporte del croquis (`BusCroquisController`). Ids numéricos de `Bus`.
 */
import { http, request } from '@/core/http'
import type { ElementoCroquis, PlantillaCroquis } from './types'

interface CroquisDto {
  elementos: ElementoCroquis[]
}

/** Id numérico de un IRI o id (`/api/buses/7` → 7). */
export const busIdNumerico = (id: string | number) => Number(String(id).split('/').pop())

export async function fetchCroquis(busId: number): Promise<ElementoCroquis[]> {
  return (await http.get<CroquisDto>(`/buses/${busId}/croquis`)).elementos
}

/** Reemplaza el croquis del bus; devuelve el guardado (con ids nuevos). */
export async function saveCroquis(
  busId: number,
  elementos: readonly ElementoCroquis[],
): Promise<ElementoCroquis[]> {
  const body = {
    elementos: elementos.map((e) =>
      e.tipo === 'asiento'
        ? {
            tipo: e.tipo,
            id: e.id,
            numero: e.numero,
            clase: e.clase,
            planta: e.planta,
            fila: e.fila,
            columna: e.columna,
          }
        : { tipo: e.tipo, id: e.id, planta: e.planta, fila: e.fila, columna: e.columna },
    ),
  }
  return (await request<CroquisDto>(`/buses/${busId}/croquis`, { method: 'PUT', body })).elementos
}

export const fetchPlantillas = () => http.get<PlantillaCroquis[]>('/croquis/plantillas')
