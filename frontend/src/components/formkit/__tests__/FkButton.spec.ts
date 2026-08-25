import { describe, it, expect } from 'vitest'
import { ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { defaultConfig, plugin as formkitPlugin } from '@formkit/vue'
import PrimeVue from 'primevue/config'
import formkitConfig from '@/formkit.config'
import type { FormKitSchemaNode } from '@formkit/core'

function mountForm(schema: FormKitSchemaNode[]) {
  return mount(
    {
      template: `<FormKit type="form" v-model="formData"><FormKitSchema :schema="schema" /></FormKit>`,
      setup() {
        return { schema, formData: ref<Record<string, unknown>>({}) }
      },
    },
    {
      attachTo: document.body,
      global: { plugins: [PrimeVue, [formkitPlugin, defaultConfig(formkitConfig())]] },
    },
  )
}

describe('FkButton', () => {
  it('pinta el label SOLO en el botón (sin sección label de FormKit)', async () => {
    const wrapper = mountForm([
      { key: 'b1', $formkit: 'Button', name: 'guardar', label: 'Guardar todo' },
    ] as unknown as FormKitSchemaNode[])
    await flushPromises()

    expect(wrapper.find('button.p-button').exists()).toBe(true)
    expect(wrapper.find('button.p-button').text()).toContain('Guardar todo')
    // La sección label de FormKit quedó suprimida en el registro.
    expect(wrapper.findAll('label')).toHaveLength(0)

    // Props visuales pasan por attrs.
    const severityBtn = mountForm([
      { key: 'b2', $formkit: 'Button', name: 'x', label: 'Borra', severity: 'danger' },
    ] as unknown as FormKitSchemaNode[])
    await flushPromises()
    expect(severityBtn.find('button.p-button-danger').exists()).toBe(true)

    wrapper.unmount()
    severityBtn.unmount()
  })

  it('toggle: primer click → true, segundo click → false', async () => {
    const wrapper = mountForm([
      { key: 'b1', $formkit: 'Button', name: 'acepto', label: 'OK' },
    ] as unknown as FormKitSchemaNode[])
    await flushPromises()

    // El commit del valor al form v-model va por un debounce interno.
    const settle = () => new Promise((resolve) => setTimeout(resolve, 150))

    await wrapper.find('button.p-button').trigger('click')
    await settle()
    expect((wrapper.vm as unknown as { formData: Record<string, unknown> }).formData.acepto).toBe(true)

    await wrapper.find('button.p-button').trigger('click')
    await settle()
    expect((wrapper.vm as unknown as { formData: Record<string, unknown> }).formData.acepto).toBe(false)

    wrapper.unmount()
  })
})
