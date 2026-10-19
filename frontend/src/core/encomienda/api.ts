/** Transporte de encomiendas (`/api/encomiendas/*`, ADR-035). */
import { http } from '@/core/http'
import type {
  ArticuloPedido,
  ContextoEncomienda,
  CotizacionEncomienda,
  DatosEncomienda,
  EntregaPedido,
  PaginaEncomiendas,
  PedidoEncomienda,
  ProcesoSalida,
  ResultadoDesembarque,
  ResultadoEmbarque,
  RutaOpcion,
  SalidaEstacion,
} from './types'

const BASE = '/encomiendas'

export const fetchContextoEncomienda = () => http.get<ContextoEncomienda>(`${BASE}/contexto`)

export const cotizarEncomienda = (body: { origen: number | null; destino: number; articulos: ArticuloPedido[]; modalidad?: string }) =>
  http.post<CotizacionEncomienda>(`${BASE}/cotizacion`, body, { silent: true })

export const fetchRutasEncomienda = (origen: number, destino: number) =>
  http.get<RutaOpcion[]>(`${BASE}/rutas?origen=${origen}&destino=${destino}`, { silent: true })

export const registrarEncomienda = (pedido: PedidoEncomienda) => http.post<DatosEncomienda>(BASE, pedido)

export const fetchEncomiendas = (query: string) => http.get<PaginaEncomiendas>(`${BASE}?${query}`)

export const fetchEncomienda = (id: number) => http.get<DatosEncomienda>(`${BASE}/${id}`)

export const embarcarEncomiendas = (encomiendas: number[], salida: number) => http.post<ResultadoEmbarque>(`${BASE}/embarcar`, { encomiendas, salida })

export const desembarcarEncomiendas = (encomiendas: number[], estacion: number | null) =>
  http.post<ResultadoDesembarque>(`${BASE}/desembarcar`, { encomiendas, estacion })

export const entregarEncomienda = (id: number, pedido: EntregaPedido) => http.post<DatosEncomienda>(`${BASE}/${id}/entregar`, pedido)

export const anularEncomienda = (id: number, motivo: string) => http.post<DatosEncomienda>(`${BASE}/${id}/anular`, { motivo })

export const fetchSalidasEstacion = (fecha: string, estacion: number | null, destino: number | null = null) =>
  http.get<SalidaEstacion[]>(`${BASE}/salidas?fecha=${fecha}${estacion ? `&estacion=${estacion}` : ''}${destino ? `&destino=${destino}` : ''}`)

export const fetchProcesoSalida = (salida: number, estacion: number | null, silent = false) =>
  http.get<ProcesoSalida>(`${BASE}/salidas/${salida}${estacion ? `?estacion=${estacion}` : ''}`, { silent })
