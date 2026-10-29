/** Transporte de encomiendas (`/api/encomiendas/*`, ADR-035). */
import { http } from '@/core/http'
import type {
  ArticuloPedido,
  ContextoEncomienda,
  CotizacionEncomienda,
  DatosEncomienda,
  EntregaPedido,
  EspecialAdmin,
  EspecialPedido,
  FilaEncomienda,
  PaginaEncomiendas,
  PedidoEncomienda,
  ProcesoSalida,
  ResultadoDesembarque,
  ResultadoEmbarque,
  ResultadoEntrega,
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

/** Entrega múltiple: las mismas condiciones para todas (quien retira, a quién se factura). */
export const entregarEncomiendas = (encomiendas: number[], pedido: EntregaPedido) => http.post<ResultadoEntrega>(`${BASE}/entregar`, { encomiendas, ...pedido })

export const cambiarRutaEncomienda = (id: number, ruta: number) => http.post<DatosEncomienda>(`${BASE}/${id}/ruta`, { ruta })

const conEstacion = (estacion: number | null, extra = '') => (estacion ? `?estacion=${estacion}${extra ? `&${extra}` : ''}` : extra ? `?${extra}` : '')

export const fetchPendientesEnvio = (estacion: number | null, silent = false) => http.get<FilaEncomienda[]>(`${BASE}/pendientes-envio${conEstacion(estacion)}`, { silent })

export const fetchPendientesEntrega = (estacion: number | null, q = '', silent = false) =>
  http.get<FilaEncomienda[]>(`${BASE}/pendientes-entrega${conEstacion(estacion, q.trim() ? `q=${encodeURIComponent(q.trim())}` : '')}`, { silent })

export const fetchEspeciales = () => http.get<EspecialAdmin[]>(`${BASE}/especiales`)

export const crearEspecial = (pedido: EspecialPedido) => http.post<EspecialAdmin>(`${BASE}/especiales`, pedido)

export const editarEspecial = (id: number, pedido: EspecialPedido) => http.post<EspecialAdmin>(`${BASE}/especiales/${id}`, pedido)
