import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import Select from 'primevue/select'
import EstacionSelect from '@/shared/ui/EstacionSelect.vue'

const estaciones = [
  { id: 1, nombre: 'Guatemala Terminal', departamento: 'Guatemala' },
  { id: 2, nombre: 'Flores', departamento: 'Petén' },
  { id: 3, nombre: 'Cobán', departamento: 'Alta Verapaz' },
]

const pausa = () => new Promise((r) => setTimeout(r, 10))
window.matchMedia = ((q: string) => ({ matches: false, media: q, onchange: null, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {}, dispatchEvent: () => false })) as unknown as typeof window.matchMedia

const montar = (modelValue: number | null) => mount(EstacionSelect, { props: { modelValue, estaciones, placeholder: 'Todas' }, global: { plugins: [PrimeVue] }, attachTo: document.body })

describe('EstacionSelect', () => {
  it('limpia el texto de búsqueda al cerrar y al volver a abrir', async () => {
    const w = montar(null)
    const vm = w.findComponent(Select).vm as unknown as { hide: () => void }
    await w.find('.p-select').trigger('mousedown')
    await w.find('.p-select').trigger('click')
    await pausa()
    const input = document.querySelector('.p-select-filter') as HTMLInputElement
    input.value = 'Flo'
    input.dispatchEvent(new Event('input'))
    await pausa()
    vm.hide()
    await pausa()
    await w.find('.p-select').trigger('mousedown')
    await w.find('.p-select').trigger('click')
    await pausa()
    expect((document.querySelector('.p-select-filter') as HTMLInputElement).value).toBe('')
    w.unmount()
  })

  it('la estación elegida no se pinta con el color de placeholder aunque su departamento esté contraído', async () => {
    const w = montar(2)
    await pausa()
    const label = w.find('.p-select-label')
    expect(label.text()).toBe('Flores')
    expect(label.attributes('style')).toContain('color')
    w.unmount()
  })

  it('sin estación elegida sigue mostrando el placeholder', async () => {
    const w = montar(null)
    await pausa()
    const label = w.find('.p-select-label')
    expect(label.text()).toBe('Todas')
    expect(label.classes()).toContain('p-placeholder')
    expect(label.attributes('style') ?? '').not.toContain('color')
    w.unmount()
  })
})
