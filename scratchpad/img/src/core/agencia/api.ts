/** Transporte de la gestión de agencias (`/api/depositos-agencia`, ADR-034). */
import { http } from '@/core/http'
import type { DepositoFila, NuevoDeposito, OpcionesDepositos, PaginaDepositos, SaldoAgenciaFila } from './types'

const BASE = '/depositos-agencia'

export const fetchOpcionesDepositos = () => http.get<OpcionesDepositos>(`${BASE}/opciones`)

export const fetchDepositos = (query: string) => http.get<PaginaDepositos>(`${BASE}?${query}`)

export const registrarDeposito = (body: NuevoDeposito) => http.post<DepositoFila>(BASE, body)

export const acreditarDeposito = (id: number) => http.post<DepositoFila>(`${BASE}/${id}/acreditar`, {})

export const rechazarDeposito = (id: number, motivo: string) => http.post<DepositoFila>(`${BASE}/${id}/rechazar`, { motivo })

export const fetchSaldosAgencias = () => http.get<SaldoAgenciaFila[]>(`${BASE}/saldos`)
