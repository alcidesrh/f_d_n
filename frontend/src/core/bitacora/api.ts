import { http } from '@/core/http'
import type { Bitacora, TipoBitacora } from './types'

export const fetchBitacora = (tipo: TipoBitacora, id: number) =>
  http.get<Bitacora>(`/bitacora/${tipo}/${id}`)
