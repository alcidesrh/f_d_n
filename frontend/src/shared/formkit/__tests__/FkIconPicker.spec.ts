import { afterEach, beforeAll, describe, it, expect, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { defaultConfig, plugin as formkitPlugin } from '@formkit/vue'
import PrimeVue from 'primevue/config'
import formkitConfig from '@/shared/formkit/config'
import type { FormKitSchemaNode } from '@formkit/core'
import { loadIconCatalog } from '@/shared/icons/iconCatalog'

if (typeof window.matchMedia !== 'function') {
  window.matchMedia = (query: string) =>
    ({
      matches: false,
      media: query,
      onchange: null,
      addListener: () => {},
      removeListener: () => {},
      addEventListener: () => {},
      removeEventListener: () => {},
      dispatchEvent: () => false,
    }) as MediaQueryList
}

function mountForm(schema: FormKitSchemaNode[], initial: Record<string, unknown> = {}) {
  const formData = ref<Record<string, unknown>>(initial)
  const wrapper = mount(
    {
      template: `<FormKit type="form" v-model="formData" :actions="false"><FormKitSchema :schema="schema" /></FormKit>`,
      setup() {
        return { schema, formData }
      },
    },
    {
      attachTo: document.body,
      global: { plugins: [PrimeVue, [formkitPlugin, defaultConfig(formkitConfig())]] },
    },
  )
  return { wrapper, formData }
}

/** Retardo del buscador antes de filtrar (`IconPicker.vue`). */
const DEBOUNCE_MS = 120
/** `delay` por defecto de FormKit entre `node.input()` y el commit del valor. */
const COMMIT_MS = 20

/**
 * Reloj falso solo para los timeouts: con `Date` congelado, los invokers de eventos de Vue
 * descartan los eventos sobre listeners agregados en el mismo instante.
 */
const relojFalso = () => vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })

async function adelantar(ms: number) {
  vi.advanceTimersByTime(ms)
  await flushPromises()
}

describe('FkIconPicker', () => {
  // Transformar el set de íconos (~8 MB de JSON) es lento con la suite en paralelo.
  beforeAll(() => loadIconCatalog(), 30_000)
  afterEach(() => {
    vi.useRealTimers()
  })

  it('modo inline: buscar y elegir un ícono exporta su nombre como valor', async () => {
    const { wrapper, formData } = mountForm([
      { $formkit: 'IconPicker', name: 'icono', inline: true },
    ] as unknown as FormKitSchemaNode[])
    relojFalso()
    // El catálogo ya está cargado (beforeAll): basta con resolver la promesa.
    await flushPromises()

    await wrapper.find('input[aria-label="Buscar ícono"]').setValue('home')
    await adelantar(DEBOUNCE_MS - 1)
    expect(wrapper.find('button[data-icon]').attributes('title')).not.toContain('home-outline ·')
    await adelantar(1)

    // Estilo por defecto: contorno.
    const first = wrapper.find('button[data-icon]')
    expect(first.attributes('title')).toContain('home-outline ·')
    await first.trigger('click')
    await adelantar(COMMIT_MS)

    expect(formData.value.icono).toBe('home-outline')
    expect(wrapper.find('button[data-icon="home-outline"]').attributes('aria-pressed')).toBe('true')
    wrapper.unmount()
  })

  it('modo popover: muestra el ícono de una entidad Icon hidratada y permite limpiarlo', async () => {
    const { wrapper, formData } = mountForm(
      [{ $formkit: 'IconPicker', name: 'icono', label: 'Ícono' }] as unknown as FormKitSchemaNode[],
      { icono: { id: '/api/icons/1', icon: 'settings-outline', name: 'Configuración' } },
    )
    relojFalso()
    await flushPromises()

    const trigger = wrapper.find('button[aria-haspopup="dialog"]')
    expect(trigger.text()).toContain('settings-outline')

    await wrapper.find('button[aria-label="Quitar ícono"]').trigger('click')
    await adelantar(COMMIT_MS)
    expect(formData.value.icono).toBeNull()
    expect(trigger.text()).toContain('Selecciona un ícono')
    wrapper.unmount()
  })
})
