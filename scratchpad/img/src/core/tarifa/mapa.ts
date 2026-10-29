/**
 * Tarifas sobre el mapa de una ruta (`/tarifas/mapa`, `GET /api/gestion-tarifas/ruta/{id}`).
 * Puro: filtros de las tarifas y el punto donde va el indicador de cada trayecto.
 */

export interface TarifaMapa {
  id: number
  nombre: string | null
  /** Clase del asiento: `A` o `B` (no la clase de bus). */
  clase: 'A' | 'B'
  empresaId: number | null
  empresa: string | null
  busId: number | null
  bus: string | null
  /** Clase de bus (`BusClase`), no la del asiento. */
  busClaseId: number | null
  busClase: string | null
  /** En unidades de su moneda. */
  precio: number | null
  importe: string | null
  /** `HH:mm`. */
  horaDesde: string | null
  horaHasta: string | null
  /** `AAAA-MM-DD HH:mm`. */
  vigenteDesde: string
  usuario: string | null
}

export interface ParadaMapa {
  orden: number
  enclaveId: number
  nombre: string
  latitud: number | null
  longitud: number | null
}

/** Par de paradas en orden de la ruta (`desde` < `hasta`, índices en `paradas`) con sus tarifas. */
export interface TrayectoMapa {
  id: number
  desde: number
  hasta: number
  origen: string
  destino: string
  tarifas: TarifaMapa[]
}

export interface RutaTarifasMapa {
  /** `null` para un trayecto origen→destino, que no es una ruta. */
  ruta: { id: number | null; nombre: string; paradas: ParadaMapa[] }
  trayectos: TrayectoMapa[]
}

export interface FiltroMapaTarifa {
  empresaId: number | null
  clase: 'A' | 'B' | null
  busId: number | null
  busClaseId: number | null
  precioMin: number | null
  precioMax: number | null
  /** `AAAA-MM-DD`: tarifas vigentes desde (inclusive). */
  vigenteDesde: string | null
  /** `AAAA-MM-DD`: tarifas vigentes desde no después de (inclusive). */
  vigenteHasta: string | null
}

export const FILTRO_MAPA_VACIO: Readonly<FiltroMapaTarifa> = {
  empresaId: null,
  clase: null,
  busId: null,
  busClaseId: null,
  precioMin: null,
  precioMax: null,
  vigenteDesde: null,
  vigenteHasta: null,
}

/** Las tarifas que cumplen todos los términos elegidos (AND). Un rango sin precio no deja pasar tarifas sin precio. */
export function filtrarTarifas(tarifas: TarifaMapa[], filtro: FiltroMapaTarifa): TarifaMapa[] {
  return tarifas.filter((t) => {
    if (filtro.empresaId !== null && t.empresaId !== filtro.empresaId) return false
    if (filtro.clase !== null && t.clase !== filtro.clase) return false
    if (filtro.busId !== null && t.busId !== filtro.busId) return false
    if (filtro.busClaseId !== null && t.busClaseId !== filtro.busClaseId) return false
    if (filtro.precioMin !== null && (t.precio === null || t.precio < filtro.precioMin))
      return false
    if (filtro.precioMax !== null && (t.precio === null || t.precio > filtro.precioMax))
      return false
    const dia = t.vigenteDesde.slice(0, 10)
    if (filtro.vigenteDesde !== null && dia < filtro.vigenteDesde) return false
    if (filtro.vigenteHasta !== null && dia > filtro.vigenteHasta) return false
    return true
  })
}

/** Distancia aproximada (km) entre dos puntos; basta para repartir la mitad de un tramo. */
function distanciaKm(
  a: { latitud: number; longitud: number },
  b: { latitud: number; longitud: number },
): number {
  const cos = Math.cos((((a.latitud + b.latitud) / 2) * Math.PI) / 180)
  return Math.hypot((b.longitud - a.longitud) * cos, b.latitud - a.latitud) * 111.32
}

/**
 * Punto a medio camino, por la línea de la ruta, entre las paradas `desde` y
 * `hasta`. `null` si alguna de ellas no tiene ubicación (no hay dónde dibujarlo).
 */
export function puntoMedio(
  paradas: ParadaMapa[],
  desde: number,
  hasta: number,
): [number, number] | null {
  const tramo: Array<{ latitud: number; longitud: number }> = []
  for (let i = desde; i <= hasta; i++) {
    const p = paradas[i]
    if (!p || p.latitud === null || p.longitud === null) return null
    tramo.push({ latitud: p.latitud, longitud: p.longitud })
  }
  if (tramo.length < 2) return null
  const largos = tramo.slice(1).map((p, i) => distanciaKm(tramo[i]!, p))
  const total = largos.reduce((s, l) => s + l, 0)
  let mitad = total / 2
  for (let i = 0; i < largos.length; i++) {
    const largo = largos[i]!
    if (mitad <= largo) {
      const f = largo === 0 ? 0 : mitad / largo
      const a = tramo[i]!
      const b = tramo[i + 1]!
      return [a.latitud + (b.latitud - a.latitud) * f, a.longitud + (b.longitud - a.longitud) * f]
    }
    mitad -= largo
  }
  const ultima = tramo[tramo.length - 1]!
  return [ultima.latitud, ultima.longitud]
}
