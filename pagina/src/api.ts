/**
 * Cliente de la API pública (mismo origen: `/api/publico`). Los errores de
 * negocio llegan como `ErrorPublico` con el mensaje del backend, el código
 * (para traducirlo, ver `mensajeDeError`) y los datos extra del error.
 */
import type {
  Carrito,
  Catalogos,
  CobroModificacion,
  Compra,
  CotizacionModificacion,
  Estacion,
  MisCompras,
  PedidoModificacion,
  Region,
  ResultadoModificacion,
  ResultadoPago,
  Salida,
  SalidaPublico,
  SolicitudPago,
  ViajePedido,
} from './tipos'

const BASE = '/api/publico'
/** Cabecera con la llave de «Mis compras» (`llave.ts`). */
const CABECERA_LLAVE = 'X-Llave-Cliente'

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

async function pedir<T>(ruta: string, opciones: { method?: string; body?: unknown; llave?: string } = {}): Promise<T> {
  let res: Response
  try {
    res = await fetch(`${BASE}${ruta}`, {
      method: opciones.method ?? 'GET',
      headers: {
        Accept: 'application/json',
        ...(opciones.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(opciones.llave ? { [CABECERA_LLAVE]: opciones.llave } : {}),
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

/** Catálogos que no cambian mientras se usa la página: una sola petición por ruta (si falla, se reintenta). */
const recordados = new Map<string, Promise<unknown>>()
function recordado<T>(ruta: string): Promise<T> {
  let p = recordados.get(ruta)
  if (!p) {
    p = pedir<T>(ruta).catch((e) => {
      recordados.delete(ruta)
      throw e
    })
    recordados.set(ruta, p)
  }
  return p as Promise<T>
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

/** Regiones del país (ISO alfa-2) para la dirección de la tarjeta; vacío = texto libre. */
export const regiones = (pais: string) => recordado<Region[]>(`/geografia/${encodeURIComponent(pais)}/regiones`)

/** Ciudades de una región; vacío = texto libre. */
export const ciudades = (region: number) => recordado<string[]>(`/geografia/regiones/${region}/ciudades`)

/** Razón social del NIT en la SAT (a ese nombre sale la factura). */
export const nit = (valor: string) => pedir<{ nit: string; nombre: string }>(`/nit?nit=${encodeURIComponent(valor)}`)

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
  pedir<{ estado: string; compras?: Compra[]; mensaje?: string | null; llave?: string | null }>(`/compras/${token}`)

export const urlBoletoPdf = (token: string, ver = false) => `${BASE}/compras/${token}/boleto.pdf${ver ? '?ver=1' : ''}`

export const contacto = (datos: { nombre: string; email: string; telefono?: string; mensaje: string; idioma: string; web?: string }) =>
  pedir<{ ok: true }>('/contacto', { method: 'POST', body: datos })

/** Mercure: el hub está en el mismo origen. */
export const urlMercure = (topico: string) => `/.well-known/mercure?topic=${encodeURIComponent(topico)}`

// «Mis compras»: todo va con la llave del cliente en la cabecera.

/** Pide al correo un enlace de acceso (responde igual tenga o no compras). */
export const pedirAcceso = (correo: string, idioma: string) =>
  pedir<{ ok: true }>('/mis-compras/acceso', { method: 'POST', body: { correo, idioma } })

export const misCompras = (llave: string) => pedir<MisCompras>('/mis-compras', { llave })

export const cotizarModificacion = (llave: string, viaje: number, pedido: PedidoModificacion) =>
  pedir<CotizacionModificacion>(`/mis-compras/viajes/${viaje}/cotizar`, { method: 'POST', body: pedido, llave })

/** Confirma la modificación; con costo, lleva la tarjeta y sigue los pasos de 3-D Secure con `token` y `continuar`. */
export const modificar = (llave: string, viaje: number, pedido: PedidoModificacion, cobro?: CobroModificacion) =>
  pedir<ResultadoModificacion>(`/mis-compras/viajes/${viaje}/modificar`, { method: 'POST', body: { ...pedido, ...cobro }, llave })

export const huellaModificacion = (llave: string, viaje: number, token: string) =>
  pedir<{ script: string | null }>(`/mis-compras/viajes/${viaje}/huella?token=${token}`, { llave })

export const cambiarFacturacion = (llave: string, compra: number, datos: { nit: string; nombreFactura?: string }) =>
  pedir<{ ok: true; reemitidas: Array<{ venta: number; error: string | null }> }>(`/mis-compras/compras/${compra}/facturacion`, { method: 'PUT', body: datos, llave })

/** PDF del viaje (boletos con los datos de la factura): la llave va en la cabecera, por eso se baja como blob. */
export async function pdfViaje(llave: string, viaje: number): Promise<Blob> {
  let res: Response
  try {
    res = await fetch(`${BASE}/mis-compras/viajes/${viaje}/boleto.pdf`, { headers: { [CABECERA_LLAVE]: llave } })
  } catch {
    throw new ErrorPublico('', 'red', 0)
  }
  if (!res.ok) {
    const datos = (await res.json().catch(() => ({}))) as { error?: string; codigo?: string }
    throw new ErrorPublico(datos.error ?? '', datos.codigo ?? 'error', res.status)
  }
  return res.blob()
}
