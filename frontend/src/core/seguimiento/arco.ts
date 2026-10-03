/**
 * Arcos para que las rutas que comparten tramo no se tapen: cada ruta se dibuja
 * con una curvatura distinta (estable, según su código) y el bus se mueve por
 * el mismo arco. El arco es una curva de Bézier cuadrática por tramo cuyo punto
 * de control se separa del punto medio una fracción `k` de la longitud del tramo,
 * así la separación crece con el zoom igual que el mapa.
 */
import type { BusEnRecorrido, PosicionEstimada } from './types'

type Punto = { lat: number; lng: number }

/** Curvaturas (fracción de la longitud del tramo) que se reparten entre las rutas; 0 = recto. */
const NIVELES = [0, 0.14, -0.14, 0.28, -0.28]

/** Curvatura y fase de guiones por código de ruta: el orden alfabético entre todas las rutas fija el nivel. */
export function estilosPorRuta(rutas: string[]): Map<string, { k: number; fase: number }> {
  const unicas = [...new Set(rutas)].sort()
  return new Map(unicas.map((r, i) => [r, { k: NIVELES[i % NIVELES.length]!, fase: (i % 2) * 6 }]))
}

/** Desplazamiento perpendicular (en lat/lng) de un punto del tramo a la fracción t, con curvatura k. */
function desplazamiento(a: Punto, b: Punto, t: number, k: number): [number, number] {
  const cos = Math.cos((((a.lat + b.lat) / 2) * Math.PI) / 180) || 1
  const dx = (b.lng - a.lng) * cos
  const dy = b.lat - a.lat
  const largo = Math.hypot(dx, dy)
  if (largo === 0 || k === 0) return [0, 0]
  const altura = k * largo * 2 * t * (1 - t)
  // Perpendicular unitaria (-dy, dx)/largo, vuelta a lat/lng.
  return [(dx / largo) * altura, ((-dy / largo) * altura) / cos]
}

/** Punto del arco entre a y b a la fracción t. */
export function puntoEnArco(a: Punto, b: Punto, t: number, k: number): [number, number] {
  const [dLat, dLng] = desplazamiento(a, b, t, k)
  return [a.lat + (b.lat - a.lat) * t + dLat, a.lng + (b.lng - a.lng) * t + dLng]
}

/** Polilínea de la ruta con cada tramo arqueado. */
export function trazadoArqueado(puntos: [number, number][], k: number, pasos = 16): [number, number][] {
  if (k === 0 || puntos.length < 2) return puntos
  const out: [number, number][] = []
  for (let i = 0; i < puntos.length - 1; i++) {
    const a = { lat: puntos[i]![0], lng: puntos[i]![1] }
    const b = { lat: puntos[i + 1]![0], lng: puntos[i + 1]![1] }
    for (let s = 0; s < pasos; s++) out.push(puntoEnArco(a, b, s / pasos, k))
  }
  out.push(puntos[puntos.length - 1]!)
  return out
}

/** Posición del bus sobre su arco (en marcha); detenido o en una estación queda en ella. */
export function posicionEnArco(bus: BusEnRecorrido, p: PosicionEstimada, k: number): [number, number] {
  if (p.estado !== 'en_ruta' || p.tramo === undefined || p.fraccion === undefined) return [p.lat, p.lng]
  const a = bus.paradas[p.tramo]
  const b = bus.paradas[p.tramo + 1]
  return a && b ? puntoEnArco(a, b, p.fraccion, k) : [p.lat, p.lng]
}
