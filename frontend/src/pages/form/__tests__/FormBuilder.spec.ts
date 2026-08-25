import { describe, it, expect, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { defaultConfig, plugin as formkitPlugin } from '@formkit/vue'
import PrimeVue from 'primevue/config'
import formkitConfig from '@/formkit.config'
import FormBuilderPage from '@/pages/form/FormBuilder.vue'
import { useFormBuilderStore } from '@/stores/formBuilder'

/**
 * Integración de la página: valida que FormKitSchema renderice los wrappers
 * `.fb-cell` del preview Y que el onClick de celda (función en attrs del nodo
 * $el) llegue al DOM y seleccione en el store.
 */

describe('FormBuilder page (preview)', () => {
  let pinia: Pinia

  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
  })

  function mountPage() {
    return mount(FormBuilderPage, {
      global: {
        plugins: [pinia, PrimeVue, [formkitPlugin, defaultConfig(formkitConfig())]],
      },
    })
  }

  it('sin root muestra el empty-state', async () => {
    const wrapper = mountPage()
    await flushPromises()
    expect(wrapper.find('.p-message-info').exists()).toBe(true)
    expect(wrapper.find('.fb-preview').exists()).toBe(false)
    wrapper.unmount()
  })

  it('renderiza celdas con bordes y el click selecciona (onClick vía FormKitSchema)', async () => {
    const store = useFormBuilderStore()
    store.initRoot(2, 2)
    store.select(null)
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('.fb-preview').exists()).toBe(true)
    const cells = wrapper.findAll('.fb-cell')
    expect(cells).toHaveLength(4)
    expect(cells[0]?.classes()).toContain('fb-cell--empty')

    await cells[1]?.trigger('click')
    await flushPromises()
    expect(store.selectedKey?.index).toBe(1)
    expect(store.selectedKey?.gridId).toBe(store.root?.id)
    // La celda clickada refleja la selección tras el re-render.
    expect(wrapper.findAll('.fb-cell')[1]?.classes()).toContain('fb-cell--selected')

    wrapper.unmount()
  })
})
