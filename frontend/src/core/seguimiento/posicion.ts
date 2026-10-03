/**
 * Posición de un bus en un instante a partir de su cronograma (espejo de
 * `App\Seguimiento\PlanDeViaje::posicionEn`). El servidor reparte el
 * cronograma y el navegador anima el bus con el reloj, sin consultar cada
 * segundo. Con GPS real (`paradas` vacío) se usa la lectura tal cual.
 */
import type { BusEnRecorrido, ParadaPlan, PosicionEstimada } from './types'

const rad = (g: number) => (g * Math.PI) / 180
const grados = (r: number) => (r * 180) / Math.PI

/** Rumbo inicial de A hacia B, en grados [0, 360). */
export function rumbo(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const f1 = rad(lat1)
  const f2 = rad(lat2)
  const dl = rad(lng2 - lng1)
  const y = Math.sin(dl) * Math.cos(f2)
  const x = Math.cos(f1) * Math.sin(f2) - Math.sin(f1) * Math.cos(f2) * Math.cos(dl)
  return (grados(Math.atan2(y, x)) + 360) % 360
}

const rumboDe = (paradas: ParadaPlan[], tramo: number): number => {
  const i = Math.max(0, Math.min(paradas.length - 2, tramo))
  const a = paradas[i]!
  const b = paradas[i + 1]!
  return rumbo(a.lat, a.lng, b.lat, b.lng)
}

/** Velocidad media en marcha implícita del cronograma (km/h), sin contar paradas. */
function velocidadMedia(paradas: ParadaPlan[]): number {
  let km = 0
  let seg = 0
  for (let i = 1; i < paradas.length; i++) {
    km += paradas[i]!.km - paradas[i - 1]!.km
    seg += paradas[i]!.llegada - paradas[i - 1]!.salida
  }
  return seg > 0 ? (km / seg) * 3600 : 0
}

export function posicionEn(bus: BusEnRecorrido, ahora: number): PosicionEstimada {
  const { paradas } = bus
  const total = Math.max(1, bus.kilometros)

  if (paradas.length < 2) {
    const p = bus.posicion
    return { lat: p.lat, lng: p.lng, rumbo: p.rumbo, velocidadKmh: p.velocidadKmh, km: p.km, estado: p.estado, progreso: Math.min(1, p.km / total) }
  }

  const origen = paradas[0]!
  const destino = paradas[paradas.length - 1]!
  const n = paradas.length

  if (ahora <= origen.salida) {
    return { lat: origen.lat, lng: origen.lng, rumbo: rumboDe(paradas, 0), velocidadKmh: 0, km: 0, estado: 'por_salir', progreso: 0 }
  }
  if (ahora >= destino.llegada) {
    return { lat: destino.lat, lng: destino.lng, rumbo: rumboDe(paradas, n - 2), velocidadKmh: 0, km: destino.km, estado: 'llego', progreso: 1 }
  }

  for (let i = 1; i < n; i++) {
    const a = paradas[i - 1]!
    const b = paradas[i]!
    if (ahora < b.llegada) {
      const f = (ahora - a.salida) / Math.max(1, b.llegada - a.salida)
      const km = a.km + (b.km - a.km) * f
      return {
        lat: a.lat + (b.lat - a.lat) * f,
        lng: a.lng + (b.lng - a.lng) * f,
        rumbo: rumboDe(paradas, i - 1),
        velocidadKmh: velocidadMedia(paradas),
        km,
        estado: 'en_ruta',
        progreso: Math.min(1, km / total),
        tramo: i - 1,
        fraccion: f,
      }
    }
    if (ahora < b.salida) {
      return { lat: b.lat, lng: b.lng, rumbo: rumboDe(paradas, i), velocidadKmh: 0, km: b.km, estado: 'detenido', progreso: Math.min(1, b.km / total) }
    }
  }
  return { lat: destino.lat, lng: destino.lng, rumbo: 0, velocidadKmh: 0, km: destino.km, estado: 'llego', progreso: 1 }
}

/** Estación hacia la que va (o en la que está detenido) el bus; null si no ha salido o ya llegó. */
export function proximaParada(bus: BusEnRecorrido, ahora: number): { nombre: string; llegada: number } | null {
  if (bus.paradas.length < 2) return ahora < bus.llegadaEstimada ? bus.proxima : null
  const origen = bus.paradas[0]!
  if (ahora < origen.salida) return { nombre: bus.paradas[1]!.nombre, llegada: bus.paradas[1]!.llegada }
  const siguiente = bus.paradas.find((p, i) => i > 0 && ahora < p.llegada)
  return siguiente ? { nombre: siguiente.nombre, llegada: siguiente.llegada } : null
}
