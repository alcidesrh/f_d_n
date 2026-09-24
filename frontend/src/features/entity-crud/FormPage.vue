<template>
  <component
    :is="formComponent"
    v-if="entityName"
    :key="`${entityName}:${recordId ?? 'new'}`"
    :entity="entityName"
    :id="recordId"
    @submitted="onSubmitted"
    @deleted="onDeleted"
    @cancel="toList"
  />
</template>

<script setup lang="ts">
/**
 * Página `/form/:entity/:id?`: alta (sin id) o edición. Usa el formulario
 * dedicado de la entidad si está registrado en `formOverrides`.
 */
import { computed, defineAsyncComponent } from 'vue'
import { router } from '@/app/router'
import { entityNameFromSlug } from '@/core/entities/slug'
import { notify } from '@/core/notify'
import EntityForm from './form/EntityForm.vue'
import { entityFormOverrides } from './form/formOverrides'
import { idDisplay } from './list/listUtils'

const props = defineProps<{ entity: string | string[]; id?: string | string[] }>()

const first = (value?: string | string[]) => (Array.isArray(value) ? value[0] : value) || null
const entityName = computed(() => entityNameFromSlug(first(props.entity) ?? ''))
const recordId = computed(() => first(props.id))

const formComponent = computed(() => {
  const override = entityFormOverrides[entityName.value]
  return override ? defineAsyncComponent(override) : EntityForm
})

function toList() {
  void router.push({ name: 'entity-list', params: { entity: entityName.value } })
}

function onDeleted() {
  notify.success(`${entityName.value} eliminado`)
  toList()
}

/** Tras crear, pasa a la URL de edición: los siguientes guardados son `update`. */
function onSubmitted(item: Record<string, unknown>) {
  notify.success(`${entityName.value} guardado`)
  if (!recordId.value && item.id) {
    void router.replace({
      name: 'entity-form',
      params: { entity: entityName.value, id: idDisplay(item.id) },
    })
  }
}
</script>
