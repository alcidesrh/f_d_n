/** Transporte del abordaje (`/api/abordaje/*`, permisos `boleto.chequear`, `salida.abordar`, `salida.iniciar`, `salida.finalizar`; ADR-032). */
import { http } from '@/core/http'
import type { Seleccion } from './chequeo'
import type { DetalleAbordaje, PuedeAbordaje, ResultadoChequeo, ResultadoFinal, ResultadoInicio, SalidaAbordaje } from './types'

const BASE = '/abordaje/salidas'

export const fetchSalidasAbordaje = () => http.get<{ items: SalidaAbordaje[]; puede: PuedeAbordaje }>(BASE)

export const fetchDetalleAbordaje = (id: number, silent = false) => http.get<DetalleAbordaje>(`${BASE}/${id}`, { silent })

export const abordarSalida = (id: number) => http.post<{ salida: SalidaAbordaje }>(`${BASE}/${id}/abordar`, {})

export const chequearBoletos = (id: number, seleccion: Seleccion) => http.post<ResultadoChequeo>(`${BASE}/${id}/chequeo`, seleccion)

export const iniciarSalida = (id: number) => http.post<ResultadoInicio>(`${BASE}/${id}/iniciar`, {})

export const finalizarSalida = (id: number) => http.post<ResultadoFinal>(`${BASE}/${id}/finalizar`, {})
