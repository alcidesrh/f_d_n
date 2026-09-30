/**
 * Cliente de la API pública (mismo origen: `/api/publico`). Los errores de
 * negocio llegan como `ErrorPublico` con el mensaje para el cliente.
 */
import type {
  Carrito,
  Catalogos,
  Compra,
  Opcion,
  RecorridoPublico,
  ResultadoPago,
  Salida,
  SolicitudPago,
} from './tipos'

const BASE = '/api/publico'

export class ErrorPublico extends Error {
  constructor(
    message: string,
    readonly codigo: string,
    readonly estado: number,
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
    throw new ErrorPublico('No hay conexión. Revise su internet e intente de nuevo.', 'red', 0)
  }
  const texto = await res.text()
  let datos: unknown = null
  try {
    datos = texto ? JSON.parse(texto) : null
  } catch {
    datos = null
  }
  if (!res.ok) {
    const cuerpo = (datos ?? {}) as { error?: string; codigo?: string }
    throw new ErrorPublico(
      cuerpo.error ?? 'Ocurrió un problema. Intente de nuevo en unos minutos.',
      cuerpo.codigo ?? 'error',
      res.status,
    )
  }
  return datos as T
}

export const estaciones = () => pedir<Opcion[]>('/estaciones')
export const destinos = (origen: number) => pedir<Opcion[]>(`/destinos?origen=${origen}`)
export const catalogos = () => pedir<Catalogos>('/catalogos')

export const salidas = (origen: number, destino: number, fecha: string) =>
  pedir<Salida[]>(`/recorridos?origen=${origen}&destino=${destino}&fecha=${fecha}`)

export const recorrido = (id: number, trayecto: number, carrito: string | null) =>
  pedir<RecorridoPublico>(
    `/recorridos/${id}?trayecto=${trayecto}${carrito ? `&carrito=${carrito}` : ''}`,
  )

export const apartar = (body: {
  recorrido: number
  trayecto: number
  asiento: number
  token: string | null
}) => pedir<Carrito>('/carritos', { method: 'POST', body })

export const verCarrito = (token: string) => pedir<Carrito>(`/carritos/${token}`)

export const liberar = (token: string, asiento: number) =>
  pedir<Carrito>(`/carritos/${token}/asientos/${asiento}`, { method: 'DELETE' })

export const vaciar = (token: string) => pedir<{ ok: true }>(`/carritos/${token}`, { method: 'DELETE' })

/** Inicia el pago o, con `continuar`, sigue tras un paso de 3-D Secure. */
export const pagar = (token: string, solicitud: SolicitudPago) =>
  pedir<ResultadoPago>(`/carritos/${token}/pago`, { method: 'POST', body: solicitud })

/** Script de huella del dispositivo que pide el antifraude del banco. */
export const huella = (token: string) => pedir<{ script: string | null }>(`/carritos/${token}/huella`)

export const compra = (token: string) =>
  pedir<{ estado: string; compra?: Compra; mensaje?: string | null }>(`/compras/${token}`)

export const urlBoletoPdf = (token: string, ver = false) =>
  `${BASE}/compras/${token}/boleto.pdf${ver ? '?ver=1' : ''}`

/** Mercure: el hub está en el mismo origen. */
export const urlMercure = (topico: string) =>
  `/.well-known/mercure?topic=${encodeURIComponent(topico)}`
