import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import PrimeVue from 'primevue/config'
import Select from 'primevue/select'
import SelectorEstacion from '../componentes/compra/SelectorEstacion.vue'

const grupos = [
  { departamento: 'Guatemala', estaciones: [{ id: 1, nombre: 'Guatemala Terminal', departamento: 'Guatemala' }] },
  { departamento: 'Petén', estaciones: [{ id: 2, nombre: 'Flores', departamento: 'Petén' }, { id: 3, nombre: 'Santa Elena', departamento: 'Petén' }] },
]

const pausa = () => new Promise((r) => setTimeout(r, 10))
window.matchMedia = ((q: string) => ({ matches: false, media: q, onchange: null, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {}, dispatchEvent: () => false })) as unknown as typeof window.matchMedia

const montar = (modelValue: number | null) =>
  mount(SelectorEstacion, {
    props: { modelValue, grupos, placeholder: 'Origen', inputId: 'origen' },
    global: { plugins: [PrimeVue, createI18n({ legacy: false, locale: 'es', messages: { es: {} } })], stubs: { icon: true } },
    attachTo: document.body,
  })

describe('SelectorEstacion (página web)', () => {
  it('limpia el texto de búsqueda al cerrar y al volver a abrir', async () => {
    const w = montar(null)
    const vm = w.findComponent(Select).vm as unknown as { hide: () => void }
    await w.find('.p-select').trigger('mousedown')
    await w.find('.p-select').trigger('click')
    await pausa()
    const input = document.querySelector('.p-select-overlay input[role="searchbox"]') as HTMLInputElement
    input.value = 'Flo'
    input.dispatchEvent(new Event('input'))
    await pausa()
    vm.hide()
    await pausa()
    await w.find('.p-select').trigger('mousedown')
    await w.find('.p-select').trigger('click')
    await pausa()
    expect((document.querySelector('.p-select-overlay input[role="searchbox"]') as HTMLInputElement).value).toBe('')
    w.unmount()
  })

  it('la estación elegida con su departamento contraído no se pinta como placeholder', async () => {
    const w = montar(2)
    await pausa()
    const label = w.find('.p-select-label')
    expect(label.text()).toBe('Flores')
    expect(label.attributes('style')).toContain('color')
    w.unmount()
  })
})
