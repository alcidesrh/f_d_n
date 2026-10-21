import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { Manifiesto } from '@/core/metadata/manifiesto'
import type { DiferenciasEsquema } from '@/core/metadata/huellas'

const { fetchManifiesto, schemaMock, registro, olvidarEntidad, suscripciones, notifyMock, oyentesDesfase } = vi.hoisted(() => ({
  fetchManifiesto: vi.fn<() => Promise<Manifiesto>>(),
  schemaMock: {
    desactualizado: vi.fn<(m: Manifiesto) => boolean>(),
    recargar: vi.fn<(m: Manifiesto | null) => Promise<DiferenciasEsquema | null>>(),
    init: vi.fn<(m: Manifiesto | null) => Promise<void>>(),
  },
  registro: new Map<string, { revisionConfig: number | null; reconciliar: () => void }>(),
  olvidarEntidad: vi.fn<(nombre: string) => void>(),
  suscripciones: [] as Array<(datos: unknown) => void>,
  notifyMock: { info: vi.fn<(texto: string) => void>() },
  oyentesDesfase: [] as Array<(mensajes: string[]) => void>,
}))

vi.mock('@/core/metadata/manifiesto', async (original) => ({
  ...(await original<typeof import('@/core/metadata/manifiesto')>()),
  fetchManifiesto,
}))
vi.mock('@/core/entities/schema', () => ({ useSchemaStore: () => schemaMock }))
vi.mock('@/core/entities/registry', () => ({ entityStores: registro, olvidarEntidad }))
vi.mock('@/core/auth/session', () => ({ useSessionStore: () => ({ isAuthenticated: true }) }))
vi.mock('@/core/realtime', () => ({
  suscribir: (_topico: string, alCambiar: (datos: unknown) => void) => {
    suscripciones.push(alCambiar)
    return () => {}
  },
}))
vi.mock('@/core/notify', () => ({ notify: notifyMock }))
vi.mock('@/core/graphql/desfase', () => ({
  alDesfasarseElEsquema: (oyente: (mensajes: string[]) => void) => {
    oyentesDesfase.push(oyente)
    return () => {}
  },
}))

const { useMetadataStore } = await import('@/core/metadata/sincronizacion')
const { manifiestoActual } = await import('@/core/metadata/manifiesto')

const manifiesto = (revision: number, extra: Partial<Manifiesto> = {}): Manifiesto => ({
  revision,
  esquema: { revision: 1, huella: 's1' },
  configs: {},
  completa: 0,
  aviso: null,
  ...extra,
})

function storeViva(nombre: string, revisionConfig: number | null) {
  const store = { revisionConfig, reconciliar: vi.fn<() => void>() }
  registro.set(nombre, store)
  return store
}

const sinDiferencias: DiferenciasEsquema = { agregadas: [], modificadas: [], eliminadas: [] }

describe('sincronización de la metadata', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    registro.clear()
    suscripciones.length = 0
    oyentesDesfase.length = 0
    manifiestoActual.value = null
    schemaMock.desactualizado.mockReturnValue(false)
    schemaMock.recargar.mockResolvedValue(sinDiferencias)
    schemaMock.init.mockResolvedValue(undefined)
  })
  afterEach(() => vi.useRealTimers())

  it('al arrancar pasa el manifiesto al schema y lo deja vigente', async () => {
    const m = manifiesto(5)
    fetchManifiesto.mockResolvedValue(m)
    const metadata = useMetadataStore()
    await metadata.arrancar()
    expect(schemaMock.init).toHaveBeenCalledWith(m)
    expect(manifiestoActual.value).toBe(m)
    expect(metadata.revision).toBe(5)
  })

  it('si el manifiesto no llega, arranca con lo guardado', async () => {
    fetchManifiesto.mockRejectedValue(new Error('sin red'))
    const metadata = useMetadataStore()
    await metadata.arrancar()
    expect(schemaMock.init).toHaveBeenCalledWith(null)
    expect(metadata.revision).toBeNull()
  })

  it('marca solo las stores en uso cuya configuración quedó vieja, sin pedir el schema si es el mismo', async () => {
    const bus = storeViva('Bus', 10)
    const piloto = storeViva('Piloto', 10)
    storeViva('Nunca', null)
    const metadata = useMetadataStore()

    await metadata.aplicar(manifiesto(12, { configs: { Bus: 12, Piloto: 8 } }))
    expect(schemaMock.recargar).not.toHaveBeenCalled()
    expect(metadata.versionDe('Bus')).toBe(1)
    expect(bus.reconciliar).toHaveBeenCalledOnce()
    expect(metadata.versionDe('Piloto')).toBe(0)
    expect(piloto.reconciliar).not.toHaveBeenCalled()
    expect(metadata.versionDe('Nunca')).toBe(0)
  })

  it('con otro schema vuelve a pedirlo, olvida las entidades que ya no existen y marca las que cambiaron', async () => {
    schemaMock.desactualizado.mockReturnValue(true)
    schemaMock.recargar.mockResolvedValue({ agregadas: ['Copiloto'], modificadas: ['Bus'], eliminadas: ['Viejo'] })
    const metadata = useMetadataStore()

    expect(await metadata.aplicar(manifiesto(13))).toBe(true)
    expect(olvidarEntidad).toHaveBeenCalledWith('Viejo')
    expect(metadata.versionDe('Bus')).toBe(1)
    expect(metadata.versionDe('Copiloto')).toBe(1)
  })

  it('recargar todo marca todas las stores en uso', async () => {
    storeViva('Bus', 10)
    storeViva('Piloto', 11)
    const metadata = useMetadataStore()
    await metadata.aplicar(manifiesto(12, { completa: 12 }))
    expect(metadata.versionDe('Bus')).toBe(1)
    expect(metadata.versionDe('Piloto')).toBe(1)
  })

  it('el aviso se muestra solo si llegó después de lo que ya se tenía', async () => {
    const metadata = useMetadataStore()
    await metadata.aplicar(manifiesto(7, { aviso: { revision: 7, nota: 'vieja' } }))
    expect(metadata.aviso).toBeNull()

    await metadata.aplicar(manifiesto(9, { aviso: { revision: 9, nota: 'Nuevo campo copiloto' } }))
    expect(metadata.aviso).toEqual({ revision: 9, nota: 'Nuevo campo copiloto' })
    metadata.descartarAviso()
    expect(metadata.aviso).toBeNull()

    await metadata.aplicar(manifiesto(10, { aviso: { revision: 9, nota: 'Nuevo campo copiloto' } }))
    expect(metadata.aviso).toBeNull()
  })

  it('junta los avisos de Mercure y no consulta por una revisión que ya tiene', async () => {
    vi.useFakeTimers()
    fetchManifiesto.mockResolvedValue(manifiesto(5))
    const metadata = useMetadataStore()
    await metadata.arrancar()
    fetchManifiesto.mockClear()
    metadata.iniciar()
    const avisar = suscripciones[0]!

    avisar({ revision: 5 })
    await vi.advanceTimersByTimeAsync(3000)
    expect(fetchManifiesto).not.toHaveBeenCalled()

    fetchManifiesto.mockResolvedValue(manifiesto(7))
    avisar({ revision: 6 })
    await vi.advanceTimersByTimeAsync(300)
    avisar({ revision: 7 })
    await vi.advanceTimersByTimeAsync(3000)
    expect(fetchManifiesto).toHaveBeenCalledOnce()
    expect(metadata.revision).toBe(7)
    metadata.detener()
  })

  it('si al vencer la espera ya tiene lo anunciado, no vuelve a consultar', async () => {
    vi.useFakeTimers()
    fetchManifiesto.mockResolvedValue(manifiesto(5))
    const metadata = useMetadataStore()
    await metadata.arrancar()
    fetchManifiesto.mockClear()
    metadata.iniciar()

    suscripciones[0]!({ revision: 6 })
    // Otra consulta en curso trae la 6 antes de que venza la espera.
    await metadata.aplicar(manifiesto(6))
    await vi.advanceTimersByTimeAsync(3000)
    expect(fetchManifiesto).not.toHaveBeenCalled()
    metadata.detener()
  })

  it('un error de schema viejo lo vuelve a pedir aunque el manifiesto no lo diga, y avisa si cambió', async () => {
    fetchManifiesto.mockResolvedValue(manifiesto(5))
    schemaMock.recargar.mockResolvedValue({ agregadas: [], modificadas: ['Bus'], eliminadas: [] })
    const metadata = useMetadataStore()
    metadata.iniciar()
    oyentesDesfase[0]!(['Cannot query field "x" on type "Bus".'])
    await vi.waitFor(() => expect(notifyMock.info).toHaveBeenCalledOnce())
    expect(schemaMock.recargar).toHaveBeenCalledOnce()
    expect(metadata.versionDe('Bus')).toBe(1)

    // Como mucho una vez por minuto.
    oyentesDesfase[0]!(['Cannot query field "x" on type "Bus".'])
    await vi.waitFor(() => expect(schemaMock.recargar).toHaveBeenCalledOnce())
    metadata.detener()
  })
})
