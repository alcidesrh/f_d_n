/**
 * Reproducción de una trayectoria GPS guardada (ADR-050): dónde iba el bus en
 * cada instante, interpolando entre lecturas. Un hueco largo sin lecturas
 * (teléfono apagado o sin señal) no se interpola: el bus espera en la última
 * lectura y, al reproducir, el hueco se salta.
 */
import { rumbo } from './posicion'
import type { PuntoGps } from './types'

/** Más que esto (s) entre dos lecturas es un hueco sin señal. */
export const SIN_SENAL_SEGUNDOS = 300

/** Por debajo de esto (km) entre dos lecturas el rumbo es ruido: se usa el que dio el GPS. */
const QUIETO_KM = 0.005

export interface PosicionReproducida {
  lat: number
  lng: number
  rumbo: number
  velocidadKmh: number
  /** Km recorridos desde la primera lectura. */
  km: number
  /** Última lectura en o antes del instante. */
  indice: number
  /** El instante cae en un hueco sin señal. */
  sinSenal: boolean
}

const rad = (g: number) => (g * Math.PI) / 180

/** Distancia sobre la esfera entre dos coordenadas, en km. */
export function distanciaKm(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2
  return 2 * 6371 * Math.asin(Math.min(1, Math.sqrt(a)))
}

/** Km acumulados en cada lectura. */
export function kmAcumulados(puntos: PuntoGps[]): number[] {
  const km: number[] = []
  puntos.forEach((p, i) => {
    const a = puntos[i - 1]
    km.push(a ? km[i - 1]! + distanciaKm(a[1], a[2], p[1], p[2]) : 0)
  })
  return km
}

/** Índice de la última lectura con instante ≤ `t`; -1 si `t` es anterior a la primera. */
export function indiceEn(puntos: PuntoGps[], t: number): number {
  let lo = 0
  let hi = puntos.length - 1
  let r = -1
  while (lo <= hi) {
    const m = (lo + hi) >> 1
    if (puntos[m]![0] <= t) {
      r = m
      lo = m + 1
    } else hi = m - 1
  }
  return r
}

const esHueco = (a: PuntoGps, b: PuntoGps) => b[0] - a[0] > SIN_SENAL_SEGUNDOS

/** Posición del bus en `t`; null sin lecturas. Antes de la primera, en la primera; después de la última, en la última. */
export function posicionEn(puntos: PuntoGps[], km: number[], t: number): PosicionReproducida | null {
  if (puntos.length === 0) return null
  const i = Math.max(0, indiceEn(puntos, t))
  const a = puntos[i]!
  const b = puntos[i + 1]
  const enLectura = { lat: a[1], lng: a[2], rumbo: a[4], velocidadKmh: a[3], km: km[i] ?? 0, indice: i, sinSenal: false }
  if (!b || t <= a[0]) return enLectura
  if (esHueco(a, b)) return { ...enLectura, velocidadKmh: 0, sinSenal: true }

  const f = (t - a[0]) / (b[0] - a[0])
  const movio = distanciaKm(a[1], a[2], b[1], b[2]) > QUIETO_KM
  return {
    lat: a[1] + (b[1] - a[1]) * f,
    lng: a[2] + (b[2] - a[2]) * f,
    rumbo: movio ? rumbo(a[1], a[2], b[1], b[2]) : a[4],
    velocidadKmh: a[3] + (b[3] - a[3]) * f,
    km: (km[i] ?? 0) + ((km[i + 1] ?? 0) - (km[i] ?? 0)) * f,
    indice: i,
    sinSenal: false,
  }
}

/** Lo recorrido hasta la posición: las lecturas anteriores y la posición misma. */
export function recorridoHasta(puntos: PuntoGps[], p: PosicionReproducida): [number, number][] {
  const linea = puntos.slice(0, p.indice + 1).map((x) => [x[1], x[2]] as [number, number])
  linea.push([p.lat, p.lng])
  return linea
}

/** Tramos con lecturas seguidas (se dibujan continuos) y los huecos sin señal entre ellos (discontinuos). */
export function partirPorHuecos(puntos: PuntoGps[]): { tramos: [number, number][][]; huecos: [[number, number], [number, number]][] } {
  const tramos: [number, number][][] = []
  const huecos: [[number, number], [number, number]][] = []
  let actual: [number, number][] = []
  puntos.forEach((p, i) => {
    const a = puntos[i - 1]
    if (a && esHueco(a, p)) {
      tramos.push(actual)
      huecos.push([
        [a[1], a[2]],
        [p[1], p[2]],
      ])
      actual = []
    }
    actual.push([p[1], p[2]])
  })
  if (actual.length) tramos.push(actual)
  return { tramos: tramos.filter((t) => t.length > 0), huecos }
}

/**
 * Instante siguiente al reproducir `segundos` desde `t`: si cae en un hueco
 * sin señal, salta a la lectura que lo cierra. Nunca pasa de la última lectura.
 */
export function avanzar(puntos: PuntoGps[], t: number, segundos: number): number {
  const ultima = puntos[puntos.length - 1]
  if (!ultima) return t
  const siguiente = Math.min(t + segundos, ultima[0])
  const i = indiceEn(puntos, siguiente)
  const a = puntos[i]
  const b = puntos[i + 1]
  return a && b && esHueco(a, b) && siguiente > a[0] ? b[0] : siguiente
}
