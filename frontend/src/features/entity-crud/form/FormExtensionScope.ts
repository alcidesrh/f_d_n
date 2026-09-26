/**
 * Envuelve una sección extra del formulario para que sus hooks se registren
 * bajo su clave (`scopedEntityFormExtensionHost`).
 */
import { defineComponent, inject, provide } from 'vue'
import { ENTITY_FORM_EXTENSION, scopedEntityFormExtensionHost } from '@/core/entities/formExtension'

export default defineComponent({
  name: 'FormExtensionScope',
  props: { extensionKey: { type: String, required: true } },
  setup(props, { slots }) {
    const parent = inject(ENTITY_FORM_EXTENSION, null)
    if (parent)
      provide(ENTITY_FORM_EXTENSION, scopedEntityFormExtensionHost(parent, props.extensionKey))
    return () => slots.default?.()
  },
})
