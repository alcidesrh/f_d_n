/**
 * Cliente de la API pública (mismo origen: `/api/publico`). Los errores de
 * negocio llegan como `ErrorPublico` con el mensaje del backend, el código
 * (para traducirlo, ver `mensajeDeError`) y los datos extra del error.
 */
import type {
  Carrito,
  Catalogos,
  Compra,
  Estacion,
  ResultadoPago,
  Salida,
  SalidaPublico,
  SolicitudPago,
  ViajePedido,
} from './tipos'

const BASE = '/api/publico'

export class ErrorPublico extends Error {
  constructor(
    message: string,
    readonly codigo: string,
    readonly estado: number,
    readonly detalle: Record<string, unknown> = {},
  ) {
    super(message)
  }
}

async function pedir<T>(ruta: string, opciones: { method?: string; body?: unknown } = {}): Promise<T> {
  let res: Response
  try {
    res = await fetch(`${BASE}${ruta}`, {
      method: opciones.method ?? 'GET',
      headers: {
        Accept: 'application/json',
        ...(opciones.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      },
      body: opciones.body !== undefined ? JSON.stringify(opciones.body) : undefined,
    })
  } catch {
    throw new ErrorPublico('', 'red', 0)
  }
  const texto = await res.text()
  let datos: unknown = null
  try {
    datos = texto ? JSON.parse(texto) : null
  } catch {
    datos = null
  }
  if (!res.ok) {
    const { error, codigo, ...detalle } = (datos ?? {}) as { error?: string; codigo?: string } & Record<string, unknown>
    throw new ErrorPublico(error ?? '', codigo ?? 'error', res.status, detalle)
  }
  return datos as T
}

export const estaciones = () => pedir<Estacion[]>('/estaciones')
export interface EstacionDirectorio extends Estacion {
  direccion: string | null
  latitud: number | null
  longitud: number | null
}
export const directorio = () => pedir<EstacionDirectorio[]>('/estaciones/directorio')
export const destinos = (origen: number) => pedir<Estacion[]>(`/destinos?origen=${origen}`)
export const catalogos = () => pedir<Catalogos>('/catalogos')

export const salidas = (origen: number, destino: number, fecha: string) =>
  pedir<Salida[]>(`/salidas?origen=${origen}&destino=${destino}&fecha=${fecha}`)

export const salida = (id: number, trayecto: number, carrito: string | null) =>
  pedir<SalidaPublico>(`/salidas/${id}?trayecto=${trayecto}${carrito ? `&carrito=${carrito}` : ''}`)

/** Aparta todos los asientos (ida y regreso) o ninguno; reemplaza lo que el carrito tuviera. */
export const reservar = (viajes: ViajePedido[], token: string | null) =>
  pedir<Carrito>('/carritos', { method: 'POST', body: { token, viajes } })

export const verCarrito = (token: string) => pedir<Carrito>(`/carritos/${token}`)

export const vaciar = (token: string) => pedir<{ ok: true }>(`/carritos/${token}`, { method: 'DELETE' })

/** Inicia el pago o, con `continuar`, sigue tras un paso de 3-D Secure. */
export const pagar = (token: string, solicitud: SolicitudPago) =>
  pedir<ResultadoPago>(`/carritos/${token}/pago`, { method: 'POST', body: solicitud })

/** Script de huella del dispositivo que pide el antifraude del banco. */
export const huella = (token: string) => pedir<{ script: string | null }>(`/carritos/${token}/huella`)

export const compra = (token: string) =>
  pedir<{ estado: string; compras?: Compra[]; mensaje?: string | null }>(`/compras/${token}`)

export const urlBoletoPdf = (token: string, ver = false) => `${BASE}/compras/${token}/boleto.pdf${ver ? '?ver=1' : ''}`

export const contacto = (datos: { nombre: string; email: string; telefono?: string; mensaje: string; idioma: string; web?: string }) =>
  pedir<{ ok: true }>('/contacto', { method: 'POST', body: datos })

/** Mercure: el hub está en el mismo origen. */
export const urlMercure = (topico: string) => `/.well-known/mercure?topic=${encodeURIComponent(topico)}`
