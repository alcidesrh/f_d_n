/**
 * Enclaves sobre el trayecto de una ruta (puro): los que quedan a menos de
 * `radioKm` de algún tramo entre dos paradas consecutivas con coordenadas.
 * Las paradas sin coordenadas no cuentan, igual que en el trazo del mapa.
 */

/** Distancia máxima (km) de un enclave al tramo para considerarlo parte del trayecto. */
export const RADIO_TRAYECTO_KM = 5

/** Punto [latitud, longitud] en grados. */
export type PuntoGeo = [number, number]

const KM_POR_GRADO = 111.32

/** Distancia (km) de `p` al segmento `a`–`b`, en plano local: exacta para trayectos cortos. */
export function distanciaASegmentoKm(p: PuntoGeo, a: PuntoGeo, b: PuntoGeo): number {
  const cos = Math.cos((((a[0] + b[0]) / 2) * Math.PI) / 180) || 1
  const proyectar = (q: PuntoGeo): [number, number] => [q[1] * cos * KM_POR_GRADO, q[0] * KM_POR_GRADO]
  const [px, py] = proyectar(p)
  const [ax, ay] = proyectar(a)
  const [bx, by] = proyectar(b)
  const dx = bx - ax
  const dy = by - ay
  const largo2 = dx * dx + dy * dy
  const t = largo2 === 0 ? 0 : Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / largo2))
  return Math.hypot(px - (ax + t * dx), py - (ay + t * dy))
}

/**
 * Filtra `candidatos` (con latitud/longitud, pueden ser null) a los que están
 * sobre el trayecto de `paradas` (en orden, sin coordenadas). Conserva el orden
 * de `candidatos`. Con una sola parada ubicada, el trayecto es ese punto.
 */
export function sobreElTrayecto<T extends { latitud: number | null; longitud: number | null }>(
  candidatos: T[],
  paradas: PuntoGeo[],
  radioKm = RADIO_TRAYECTO_KM,
): T[] {
  if (paradas.length === 0) return []
  const tramos: Array<[PuntoGeo, PuntoGeo]> = paradas.length === 1 ? [[paradas[0]!, paradas[0]!]] : paradas.slice(1).map((b, i) => [paradas[i]!, b])
  return candidatos.filter((c) => {
    if (c.latitud === null || c.longitud === null) return false
    const p: PuntoGeo = [c.latitud, c.longitud]
    return tramos.some(([a, b]) => distanciaASegmentoKm(p, a, b) <= radioKm)
  })
}
