import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import * as api from '@/core/venta/api'
import { useAccesoStore } from '@/core/acceso/store'
import type { MiAcceso } from '@/core/acceso/tipos'
import { useVentaStore } from '../store'

vi.mock('@/core/venta/api', () => ({
  fetchContexto: vi.fn<() => Promise<unknown>>(),
  fetchSalidas: vi.fn<() => Promise<unknown[]>>(async () => []),
  errorVenta: vi.fn<() => null>(() => null),
}))

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
