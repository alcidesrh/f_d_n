import { beforeAll, describe, it, expect } from 'vitest'
import { ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { defaultConfig, plugin as formkitPlugin } from '@formkit/vue'
import PrimeVue from 'primevue/config'
import formkitConfig from '@/shared/formkit/config'
import type { FormKitSchemaNode } from '@formkit/core'
import { loadTablerCatalog } from '@/shared/icons/tablerCatalog'

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

/** Espera la carga del catálogo, el debounce del buscador y el commit de FormKit. */
async function settle() {
  await loadTablerCatalog()
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 150))
  await flushPromises()
}

describe('FkIconPicker', () => {
  // Transformar el set de íconos (~2 MB de JSON) es lento con la suite en paralelo.
  beforeAll(() => loadTablerCatalog(), 30_000)

  it('modo inline: buscar y elegir un ícono exporta su nombre como valor', async () => {
    const { wrapper, formData } = mountForm([
      { $formkit: 'IconPicker', name: 'icono', inline: true },
    ] as unknown as FormKitSchemaNode[])
    await settle()

    await wrapper.find('input[aria-label="Buscar ícono"]').setValue('bus')
    await settle()

    const first = wrapper.find('button[data-icon]')
    expect(first.attributes('title')).toContain('bus ·')
    await first.trigger('click')
    await settle()

    expect(formData.value.icono).toBe('bus')
    expect(wrapper.find('button[data-icon="bus"]').attributes('aria-pressed')).toBe('true')
    wrapper.unmount()
  })

  it('modo popover: muestra el ícono de una entidad Icon hidratada y permite limpiarlo', async () => {
    const { wrapper, formData } = mountForm(
      [{ $formkit: 'IconPicker', name: 'icono', label: 'Ícono' }] as unknown as FormKitSchemaNode[],
      { icono: { id: '/api/icons/1', icon: 'settings', name: 'Configuración' } },
    )
    await flushPromises()

    const trigger = wrapper.find('button[aria-haspopup="dialog"]')
    expect(trigger.text()).toContain('settings')

    await wrapper.find('button[aria-label="Quitar ícono"]').trigger('click')
    await settle()
    expect(formData.value.icono).toBeNull()
    expect(trigger.text()).toContain('Selecciona un ícono')
    wrapper.unmount()
  })
})
