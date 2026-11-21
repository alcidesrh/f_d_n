import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import * as api from '@/core/venta/api'
import { useAccesoStore } from '@/core/acceso/store'
import type { MiAcceso } from '@/core/acceso/tipos'
import { useVentaStore } from '../store'

vi.mock('@/core/venta/api', () => ({
  fetchContexto: vi.fn<() => Promise<unknown>>(),
  fetchSalidas: vi.fn<() => Promise<unknown[]>>(async () => []),
  fetchSalida: vi.fn<(id: number) => Promise<unknown>>(),
  fetchOcupacion: vi.fn<() => Promise<unknown>>(async () => ({ asientos: [], recomendados: [] })),
  cotizar: vi.fn<(body: { asientos: number[] }) => Promise<unknown>>(),
  vender: vi.fn<(pedido: { salida: number }) => Promise<unknown>>(),
  // El cuerpo de error de la API viaja en `cuerpo` en estos tests.
  errorVenta: vi.fn<(e: unknown) => unknown>((e) => (e as { cuerpo?: unknown })?.cuerpo ?? null),
}))
vi.mock('@/core/realtime', () => ({ suscribir: vi.fn<() => () => void>(() => () => {}) }))

const GUATEMALA = { id: 1, nombre: 'Guatemala' }
const FLORES = { id: 2, nombre: 'Flores' }
const POPTUN = { id: 3, nombre: 'Poptún' }

/** Lo que devuelve `/venta/contexto` cuando la estación de trabajo es `estacion`. */
const contexto = (estacion: { id: number; nombre: string }) => ({
  canal: 'estacion',
  estacion: { ...estacion, departamento: null },
  agencia: null,
  permisos: { cortesia: false, sinFactura: false },
  estaciones: [],
})

/** Cambia la estación de trabajo como la cabecera: el acceso nuevo y el contexto que lo refleja. */
function cambiarEstacion(estacion: { id: number; nombre: string }) {
  vi.mocked(api.fetchContexto).mockResolvedValue(contexto(estacion) as never)
  const acceso = useAccesoStore()
  acceso.acceso = { ...acceso.acceso, estacionTrabajo: estacion } as unknown as MiAcceso
}

describe('venta: cambio de estación de trabajo', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(api.fetchContexto).mockResolvedValue(contexto(GUATEMALA) as never)
    useAccesoStore().acceso = { estacionTrabajo: GUATEMALA } as unknown as MiAcceso
  })

  it('«Sube en» toma la nueva estación y «Baja en» vuelve a Todas, como al entrar', async () => {
    const venta = useVentaStore()
    await venta.iniciar()
    expect(venta.estacionId).toBe(GUATEMALA.id)
    venta.destinoId = FLORES.id

    cambiarEstacion(POPTUN)

    await vi.waitFor(() => expect(venta.estacionId).toBe(POPTUN.id))
    expect(venta.destinoId).toBeNull()
    expect(api.fetchSalidas).toHaveBeenLastCalledWith(expect.anything(), POPTUN.id, null)
  })

  it('reasignando no mueve el viaje del boleto: lo nuevo queda para cuando termine', async () => {
    const venta = useVentaStore()
    await venta.iniciar()
    // El estado de una reasignación: el viaje del boleto (Guatemala → Flores) y los filtros de antes.
    venta.estacionId = GUATEMALA.id
    venta.destinoId = FLORES.id
    venta.reasignacion = {
      boletos: [],
      ventaId: 9,
      filtros: { estacionId: GUATEMALA.id, destinoId: null },
    } as never
    const llamadas = vi.mocked(api.fetchSalidas).mock.calls.length

    cambiarEstacion(POPTUN)

    await vi.waitFor(() =>
      expect(venta.reasignacion?.filtros).toEqual({ estacionId: POPTUN.id, destinoId: null }),
    )
    expect(venta.estacionId).toBe(GUATEMALA.id)
    expect(venta.destinoId).toBe(FLORES.id)
    expect(api.fetchSalidas).toHaveBeenCalledTimes(llamadas)
  })
})

/** Una salida de Guatemala a Flores con los asientos 1 a 4, a Q 100 cada uno. */
const salida = (id: number, salida: string) => ({
  id,
  salida,
  salidaEstacion: null,
  estado: 'programada',
  empresa: { id: 1, nombre: 'Fuentes del Norte' },
  bus: null,
  ruta: { id: 1, nombre: 'Guatemala - Flores' },
  vendidos: 0,
  capacidad: 4,
  paradas: [
    { id: GUATEMALA.id, nombre: GUATEMALA.nombre, direccion: null, posicion: 1, hora: salida },
    { id: FLORES.id, nombre: FLORES.nombre, direccion: null, posicion: 2, hora: null },
  ],
  trayectos: [{ id: 100 + id, origen: GUATEMALA.id, destino: FLORES.id, completo: true, clases: ['A'] }],
  croquis: [1, 2, 3, 4].map((n) => ({ tipo: 'asiento', id: n, numero: n, clase: 'A', x: 0, y: n })),
  cierreEnLinea: salida,
  noVendible: null,
  topico: `/salidas/${id}`,
})
const q = (centavos: number) => ({ centavos, moneda: 'GTQ', texto: `Q ${(centavos / 100).toFixed(2)}` })
const CLIENTE = { id: 7, nombre: 'Ana', apellido: null, nombreCompleto: 'Ana', nit: 'CF', email: null, telefono: null, label: 'Ana', comodin: false }

describe('venta: asientos de varias salidas', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(api.fetchContexto).mockResolvedValue(contexto(GUATEMALA) as never)
    useAccesoStore().acceso = { estacionTrabajo: GUATEMALA } as unknown as MiAcceso
    vi.mocked(api.fetchSalida).mockImplementation(async (id) => salida(id, id === 10 ? '2026-11-22T08:00:00-06:00' : '2026-11-24T20:00:00-06:00') as never)
    vi.mocked(api.cotizar).mockImplementation(async (body) => ({
      asientos: body.asientos.map((a) => ({ asiento: a, numero: a, clase: 'A', precio: q(10000), tarifa: 1 })),
      total: q(10000 * body.asientos.length),
    }) as never)
    let n = 0
    vi.mocked(api.vender).mockImplementation(async (pedido) => ({ id: ++n, salida: { id: pedido.salida } }) as never)
  })

  /** Elige dos asientos en la salida 10 y uno en la 20. */
  async function elegirEnDosSalidas() {
    const venta = useVentaStore()
    await venta.iniciar()
    venta.cliente = CLIENTE as never
    await venta.elegirSalida(10)
    venta.alternarAsiento(1)
    venta.alternarAsiento(2)
    await venta.elegirSalida(20)
    venta.alternarAsiento(4)
    await vi.waitFor(() => expect(venta.total?.centavos).toBe(30000))
    return venta
  }

  it('cambiar de salida conserva lo elegido y suma lo nuevo', async () => {
    const venta = await elegirEnDosSalidas()

    expect(venta.grupos.map((g) => [g.salidaId, g.asientos])).toEqual([[10, [1, 2]], [20, [4]]])
    expect(venta.seleccion).toEqual([4])
    expect(venta.totalAsientos).toBe(3)
    expect(venta.variasSalidas).toBe(true)
    expect(venta.grupos[0]!.ruta).toBe('Guatemala → Flores')

    // Al volver a la primera salida, su croquis muestra sus asientos elegidos.
    await venta.elegirSalida(10)
    expect(venta.seleccion).toEqual([1, 2])
  })

  it('quitar el último asiento de una salida la saca de lo elegido', async () => {
    const venta = await elegirEnDosSalidas()

    venta.quitarAsiento(venta.grupos[1]!.clave, 4)

    expect(venta.grupos).toHaveLength(1)
    await vi.waitFor(() => expect(venta.total?.centavos).toBe(20000))
  })

  it('cobra una venta por salida, cada una con su clave', async () => {
    const venta = await elegirEnDosSalidas()

    const hechos = await venta.vender({ tipoPago: 1, moneda: 1, enviarCorreo: false, cortesia: false })

    expect(hechos).toHaveLength(2)
    const pedidos = vi.mocked(api.vender).mock.calls.map(([p]) => p as unknown as { salida: number; token: string; asientos: { asiento: number }[]; cliente: number })
    expect(pedidos.map((p) => [p.salida, p.asientos.map((a) => a.asiento), p.cliente])).toEqual([
      [10, [1, 2], CLIENTE.id],
      [20, [4], CLIENTE.id],
    ])
    expect(new Set(pedidos.map((p) => p.token)).size).toBe(2)
    expect(venta.comprobantes).toHaveLength(2)
    expect(venta.grupos).toEqual([])
  })

  it('si la factura de una falla, las anteriores quedan vendidas y el reintento sigue desde esa', async () => {
    const venta = await elegirEnDosSalidas()
    const fallo = { cuerpo: { error: 'Sin respuesta del certificador', codigo: 'facturacion', recuperable: true, permiteSinFactura: true } }
    vi.mocked(api.vender).mockResolvedValueOnce({ id: 1 } as never).mockRejectedValueOnce(fallo)

    const hechos = await venta.vender({ tipoPago: 1, moneda: 1, enviarCorreo: false, cortesia: false })

    expect(hechos).toHaveLength(1)
    expect(venta.falloFacturacion?.error).toMatch(/^Guatemala → Flores \(.+\): Sin respuesta del certificador$/)
    expect(venta.grupos.map((g) => g.salidaId)).toEqual([20])
    const token = venta.grupos[0]!.token

    // Reintentar sin factura: solo esa va en contingencia, con la misma clave.
    await venta.vender({ tipoPago: 1, moneda: 1, enviarCorreo: false, cortesia: false, sinFacturaElectronica: true })

    const ultimo = vi.mocked(api.vender).mock.lastCall![0] as unknown as { salida: number; token: string; sinFacturaElectronica: boolean }
    expect([ultimo.salida, ultimo.token, ultimo.sinFacturaElectronica]).toEqual([20, token, true])
    expect(venta.comprobantes).toHaveLength(2)
    expect(venta.grupos).toEqual([])
  })
})
