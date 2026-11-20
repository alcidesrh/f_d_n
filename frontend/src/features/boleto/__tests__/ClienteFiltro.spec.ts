import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import AutoComplete from 'primevue/autocomplete'
import { buscarClientesBoleto } from '@/core/boleto/api'
import ClienteFiltro from '../ClienteFiltro.vue'

vi.mock('@/core/boleto/api', () => ({ buscarClientesBoleto: vi.fn<(q: string) => Promise<unknown[]>>() }))

const cliente = { id: 44, nit: '123456', nombreCompleto: 'Ana Pérez', numeroDocumento: '987', nombre: 'Ana', apellido: 'Pérez', email: null, telefono: null, tipoDocumento: null, nacionalidad: null, label: '', comodin: false }

beforeEach(() => {
  vi.mocked(buscarClientesBoleto).mockReset()
})

describe('ClienteFiltro («Comprado por»)', () => {
  it('busca por NIT o nombre y ofrece el NIT y el documento de cada cliente', async () => {
    vi.mocked(buscarClientesBoleto).mockResolvedValue([cliente as never])
    const w = mount(ClienteFiltro, { props: { modelValue: null }, global: { plugins: [PrimeVue] } })

    w.findComponent(AutoComplete).vm.$emit('complete', { query: 'ana' })
    await flushPromises()

    expect(buscarClientesBoleto).toHaveBeenCalledWith('ana')
    expect(w.findComponent(AutoComplete).props('suggestions')).toEqual([
      { id: 44, label: '123456 / Ana Pérez', nombreCompleto: 'Ana Pérez', detalle: 'NIT 123456 · Doc. 987' },
    ])
    w.unmount()
  })

  it('al elegir emite solo el id y la etiqueta, y al limpiar emite null', async () => {
    const w = mount(ClienteFiltro, { props: { modelValue: null }, global: { plugins: [PrimeVue] } })
    const ac = w.findComponent(AutoComplete)

    ac.vm.$emit('update:modelValue', { id: 44, label: '123456 / Ana Pérez', nombreCompleto: 'Ana Pérez', detalle: '' })
    ac.vm.$emit('update:modelValue', 'texto sin elegir')
    ac.vm.$emit('update:modelValue', null)

    expect(w.emitted('update:modelValue')).toEqual([[{ id: 44, label: '123456 / Ana Pérez' }], [null]])
    w.unmount()
  })
})
