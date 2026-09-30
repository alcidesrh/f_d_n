/**
 * FormKit con los mismos inputs sobre PrimeVue que el frontend (se importan
 * de `frontend/src/shared/formkit` por alias).
 */
import { createInput, defineFormKitConfig } from '@formkit/vue'
import { es } from '@formkit/i18n'
import FkCheckbox from '@/shared/formkit/inputs/FkCheckbox.vue'
import FkInputMask from '@/shared/formkit/inputs/FkInputMask.vue'
import FkInputText from '@/shared/formkit/inputs/FkInputText.vue'
import FkSelect from '@/shared/formkit/inputs/FkSelect.vue'

export default defineFormKitConfig({
  locales: { es },
  locale: 'es',
  inputs: {
    InputText: createInput(FkInputText),
    InputMask: createInput(FkInputMask),
    Select: createInput(FkSelect),
    Checkbox: createInput(FkCheckbox),
  },
  config: {
    classes: {
      outer: 'mb-4',
      label: 'block font-medium mb-1.5 text-sm',
      help: 'mt-1 text-xs text-muted-color',
      messages: 'list-none p-0 m-0 mt-1',
      message: 'mt-1 text-xs text-red-600',
    },
  },
})
