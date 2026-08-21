import { describe, it, expect } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defaultConfig, plugin as formkitPlugin } from '@formkit/vue'
import PrimeVue from 'primevue/config'
import formkitConfig from '@/formkit.config'
import type { FormKitSchemaNode } from '@formkit/core'
import { normalizeOptions, toScalarArray, toScalarValue } from '../useFormKitInput'

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

class RO {
  observe() {}
  unobserve() {}
  disconnect() {}
}
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = RO as unknown as typeof ResizeObserver
}
if (typeof globalThis.IntersectionObserver === 'undefined') {
  globalThis.IntersectionObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof IntersectionObserver
}
if (typeof globalThis.requestAnimationFrame !== 'function') {
  globalThis.requestAnimationFrame = (cb: FrameRequestCallback) =>
    setTimeout(cb, 16) as unknown as number
}

const bodyItems = (sel: string) => Array.from(document.body.querySelectorAll(sel))

function selectSchema(
  options: Array<{ label: string; value?: string; id?: string }>,
): FormKitSchemaNode[] {
  return [
    {
      key: 'x.localidad_1',
      $formkit: 'Select',
      name: 'localidad',
      label: 'Localidad',
      options: options as unknown as Record<string, unknown>[],
      filter: true,
      showClear: true,
      placeholder: 'Selecciona…',
      optionLabel: 'label',
      optionValue: 'value',
    },
  ]
}

function multiSelectSchema(
  options: Array<{ label: string; value?: string; id?: string }>,
): FormKitSchemaNode[] {
  return [
    {
      key: 'x.permisos_1',
      $formkit: 'MultiSelect',
      name: 'permisos',
      label: 'Permisos',
      options: options as unknown as Record<string, unknown>[],
      filter: true,
      display: 'chip',
      placeholder: 'Selecciona…',
      optionLabel: 'label',
      optionValue: 'value',
      showClear: true,
    },
  ]
}

async function mountForm(schema: FormKitSchemaNode[]) {
  const wrapper = mount(
    {
      template: `
        <FormKit type="form" v-model="formData">
          <FormKitSchema :schema="schema" />
        </FormKit>
      `,
      setup() {
        return { schema, formData: {} as Record<string, unknown> }
      },
    },
    {
      attachTo: document.body,
      global: { plugins: [PrimeVue, [formkitPlugin, defaultConfig(formkitConfig())]] },
    },
  )
  await flushPromises()
  return wrapper
}

describe('FkSelect / FkMultiSelect: normalizacion de options y valores', () => {
  describe('helpers de normalizacion', () => {
    it('normalizeOptions: acepta {label, value} y {id, label}', () => {
      expect(normalizeOptions([{ label: 'A', value: '/api/a/1' }])).toEqual([
        { label: 'A', value: '/api/a/1' },
      ])
      expect(normalizeOptions([{ id: '/api/a/1', label: 'A' }])).toEqual([
        { label: 'A', value: '/api/a/1' },
      ])
      expect(normalizeOptions([{ '@id': '/api/a/1', name: 'A' }])).toEqual([
        { label: 'A', value: '/api/a/1' },
      ])
      expect(normalizeOptions(['A'])).toEqual([{ label: 'A', value: 'A' }])
      expect(normalizeOptions(null)).toEqual([])
    })

    it('toScalarValue: extrae value/id/@id; toScalarArray mapea', () => {
      expect(toScalarValue({ value: '/api/a/1' })).toBe('/api/a/1')
      expect(toScalarValue({ id: '/api/a/1' })).toBe('/api/a/1')
      expect(toScalarValue({ '@id': '/api/a/1' })).toBe('/api/a/1')
      expect(toScalarValue('/api/a/1')).toBe('/api/a/1')
      expect(toScalarArray([{ id: '/api/a/1' }, '/api/b/2'])).toEqual(['/api/a/1', '/api/b/2'])
    })
  })

  describe('Select', () => {
    it('con options {label, value}: muestra clear icon al seleccionar y emite el IRI', async () => {
      const wrapper = await mountForm(
        selectSchema([
          { label: 'Localidad A', value: '/api/localidades/1' },
          { label: 'Localidad B', value: '/api/localidades/2' },
        ]),
      )
      await wrapper.find('.p-select').trigger('click')
      await flushPromises()
      const opts = bodyItems('.p-select-option')
      expect(opts.length).toBeGreaterThan(0)
      ;(opts[0] as HTMLElement).dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true }))
      await flushPromises()
      expect(!!document.querySelector('[data-pc-section="clearicon"]')).toBe(true)
      expect(
        (wrapper.vm as unknown as { formData: Record<string, unknown> }).formData.localidad,
      ).toBe('/api/localidades/1')
      wrapper.unmount()
    })

    it('con options {id, label}: muestra clear icon al seleccionar (regresion)', async () => {
      const wrapper = await mountForm(
        selectSchema([
          { id: '/api/localidades/1', label: 'Localidad A' },
          { id: '/api/localidades/2', label: 'Localidad B' },
        ]),
      )
      await wrapper.find('.p-select').trigger('click')
      await flushPromises()
      const opts = bodyItems('.p-select-option')
      expect(opts.length).toBeGreaterThan(0)
      ;(opts[0] as HTMLElement).dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true }))
      await flushPromises()
      expect(!!document.querySelector('[data-pc-section="clearicon"]')).toBe(true)
      expect(
        (wrapper.vm as unknown as { formData: Record<string, unknown> }).formData.localidad,
      ).toBe('/api/localidades/1')
      wrapper.unmount()
    })
  })

  describe('MultiSelect', () => {
    it('con options {label, value}: click en una opcion marca solo esa; click en otra acumula', async () => {
      const wrapper = await mountForm(
        multiSelectSchema([
          { label: 'Permiso 1', value: '/api/permisos/1' },
          { label: 'Permiso 2', value: '/api/permisos/2' },
        ]),
      )
      const msRoot = wrapper.find('.p-multiselect')
      await msRoot.trigger('click')
      await flushPromises()
      const items = () => bodyItems('.p-multiselect-option')
      expect(items().length).toBeGreaterThan(0)

      ;(items()[0] as HTMLElement).dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
      await flushPromises()
      expect(bodyItems('.p-multiselect-option .p-checkbox-checked').length).toBe(1)
      expect(bodyItems('.p-chip').map((c) => c.textContent)).toEqual(['Permiso 1'])
      expect(
        (wrapper.vm as unknown as { formData: Record<string, unknown> }).formData.permisos,
      ).toEqual(['/api/permisos/1'])

      ;(items()[1] as HTMLElement).dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
      await flushPromises()
      expect(bodyItems('.p-multiselect-option .p-checkbox-checked').length).toBe(2)
      expect(bodyItems('.p-chip').map((c) => c.textContent)).toEqual(['Permiso 1', 'Permiso 2'])
      expect(
        (wrapper.vm as unknown as { formData: Record<string, unknown> }).formData.permisos,
      ).toEqual(['/api/permisos/1', '/api/permisos/2'])
      wrapper.unmount()
    })

    it('con options {id, label}: mismo comportamiento correcto (regresion)', async () => {
      const wrapper = await mountForm(
        multiSelectSchema([
          { id: '/api/permisos/1', label: 'Permiso 1' },
          { id: '/api/permisos/2', label: 'Permiso 2' },
        ]),
      )
      const msRoot = wrapper.find('.p-multiselect')
      await msRoot.trigger('click')
      await flushPromises()
      const items = () => bodyItems('.p-multiselect-option')
      expect(items().length).toBeGreaterThan(0)

      ;(items()[0] as HTMLElement).dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
      await flushPromises()
      expect(bodyItems('.p-multiselect-option .p-checkbox-checked').length).toBe(1)
      expect(bodyItems('.p-chip').map((c) => c.textContent)).toEqual(['Permiso 1'])
      expect(
        (wrapper.vm as unknown as { formData: Record<string, unknown> }).formData.permisos,
      ).toEqual(['/api/permisos/1'])

      ;(items()[1] as HTMLElement).dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
      await flushPromises()
      expect(bodyItems('.p-multiselect-option .p-checkbox-checked').length).toBe(2)
      expect(bodyItems('.p-chip').map((c) => c.textContent)).toEqual(['Permiso 1', 'Permiso 2'])
      expect(
        (wrapper.vm as unknown as { formData: Record<string, unknown> }).formData.permisos,
      ).toEqual(['/api/permisos/1', '/api/permisos/2'])
      wrapper.unmount()
    })
  })
})
