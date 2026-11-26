/** Transporte del inventario (`/api/inventario`, ADR-071). */
import { http } from '@/core/http'
import type { AlmacenVisible, ArticuloBreve, Almacen, EnCamino, Existencia, ExistenciasAlmacen, Movimiento, Niveles, PaginaMovimientos, PorReponer, Registro } from './tipos'

const BASE = '/inventario'

export const fetchAlmacenes = (silent = false) => http.get<AlmacenVisible[]>(`${BASE}/almacenes`, { silent })

export const fetchExistencias = (almacen: number, silent = false) => http.get<ExistenciasAlmacen>(`${BASE}/almacenes/${almacen}/existencias`, { silent })

export function fetchMovimientos(almacen: number, articulo: number | null, pagina = 1) {
  const q = new URLSearchParams({ pagina: String(pagina) })
  if (articulo) q.set('articulo', String(articulo))
  return http.get<PaginaMovimientos>(`${BASE}/almacenes/${almacen}/movimientos?${q}`)
}

export const fetchDestinos = (almacen: number) => http.get<Almacen[]>(`${BASE}/almacenes/${almacen}/destinos`)

export const fetchArticulos = () => http.get<ArticuloBreve[]>(`${BASE}/articulos`)

export const fetchEnCamino = (silent = false) => http.get<EnCamino[]>(`${BASE}/en-camino`, { silent })

export const fetchPorReponer = (silent = false) => http.get<PorReponer[]>(`${BASE}/por-reponer`, { silent })

export const registrar = (r: Registro) => http.post<Movimiento>(`${BASE}/movimientos`, r)

export const recibir = (envio: number, documento?: string) => http.post<Movimiento>(`${BASE}/envios/${envio}/recibir`, documento?.trim() ? { documento: documento.trim() } : {})

export const guardarNiveles = (almacen: number, articulo: number, n: Niveles) => http.post<Existencia>(`${BASE}/almacenes/${almacen}/articulos/${articulo}/niveles`, n)
