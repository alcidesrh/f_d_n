<template>
  <div class="icon-picker flex flex-col gap-2">
    <div class="flex items-center gap-2">
      <icon
        :name="previewIcon"
        class="text-2xl! p-1 border border-solid border-gray-300 rounded"
        fill
      />
      <q-input
        v-model="freeText"
        outlined
        dense
        bg-color="white"
        placeholder="font code (ej. arrow_forward)"
        class="flex-1"
        @update:model-value="onFreeTextChange"
        @keydown.enter.prevent="createAndSelect"
      >
        <template #append>
          <q-btn
            flat
            dense
            icon="add"
            color="primary"
            :disable="!freeText || creating"
            :loading="creating"
            @click="createAndSelect"
          />
        </template>
      </q-input>
      <q-btn
        flat
        dense
        icon="grid_view"
        color="primary"
        @click="galleryOpen = true"
      >
        <q-tooltip>Galería de iconos</q-tooltip>
      </q-btn>
    </div>
    <div v-if="selectedIcon" class="text-sm text-gray-500 flex items-center gap-1">
      <span class="material-symbols-outlined text-sm">{{
        selectedIcon.icon
      }}</span>
      <span>{{ selectedIcon.name || selectedIcon.icon }}</span>
    </div>

    <q-dialog v-model="galleryOpen" maximized>
      <q-card>
        <q-card-section class="q-pa-md">
          <div class="text-h6 q-mb-sm">Galería de iconos</div>
          <q-input
            v-model="gallerySearch"
            outlined
            dense
            placeholder="Buscar icono..."
            clearable
            class="q-mb-md"
          >
            <template #prepend>
              <q-icon name="search" />
            </template>
          </q-input>
          <div v-if="loadingGallery" class="flex justify-center q-pa-xl">
            <q-spinner color="primary" size="3rem" />
          </div>
          <div
            v-else
            class="flex flex-wrap gap-10px"
            style="max-height: 70vh; overflow-y: auto"
          >
            <div
              v-for="icon in filteredIcons"
              :key="icon.id"
              class="icon-card flex flex-col items-center gap-1 p-8px border border-solid border-gray-200 rounded-lg cursor-pointer hover:border-primary hover:bg-gray-50 transition-all"
              :class="{
                'border-primary! bg-primary-50': selectedIcon?.id === icon.id,
              }"
              style="width: 100px"
              @click="selectFromGallery(icon)"
            >
              <span class="material-symbols-outlined text-3xl">{{
                icon.icon
              }}</span>
              <span class="text-xs text-center truncate w-full">{{
                icon.name || icon.icon
              }}</span>
            </div>
            <div
              v-if="filteredIcons.length === 0 && !loadingGallery"
              class="w-full text-center py-10 text-gray-400"
            >
              No hay iconos en la galería. Escribe un font code nuevo arriba y
              presiona Enter.
            </div>
          </div>
        </q-card-section>
        <q-card-actions class="justify-end q-pa-sm">
          <q-btn flat label="Cerrar" v-close-popup color="negative" />
        </q-card-actions>
        </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
const props = defineProps({
  context: Object,
})

const loadingGallery = ref(false)
const galleryOpen = ref(false)
const gallerySearch = ref('')
const galleryIcons = ref<any[]>([])
const creating = ref(false)
const previewIcon = ref('edit')
const selectedIcon = ref<any>(null)
const freeText = ref('')

if (props.context?.options?.length) {
  galleryIcons.value = props.context.options
}

watch(
  () => props.context._value,
  (v) => {
    if (v && typeof v === 'object' && v.icon) {
      selectedIcon.value = v
      freeText.value = v.icon
      previewIcon.value = v.icon
    } else if (v && typeof v === 'number') {
      selectedIcon.value = { id: v }
    } else if (v && typeof v === 'string') {
      freeText.value = v
      previewIcon.value = v
    }
  },
  { immediate: true },
)

const filteredIcons = computed(() => {
  if (!gallerySearch.value) return galleryIcons.value
  const q = gallerySearch.value.toLowerCase()
  return galleryIcons.value.filter(
    (i) =>
      i.icon?.toLowerCase().includes(q) ||
      i.name?.toLowerCase().includes(q) ||
      i.label?.toLowerCase().includes(q),
  )
})

watch(galleryOpen, async (open) => {
  if (open && !galleryIcons.value.length) {
    await loadIcons()
  }
})

async function loadIcons() {
  loadingGallery.value = true
  try {
    const { getStore } = await import('@/composables/entityRegistry')
    const iconStore = await getStore('Icon')
    await iconStore.getOptions()
    galleryIcons.value = iconStore.options || []
  } catch (err) {
    console.error('Error loading icons:', err)
  } finally {
    loadingGallery.value = false
  }
}

function onFreeTextChange(val: string) {
  previewIcon.value = val || 'edit'
}

function selectIcon(icon: any) {
  selectedIcon.value = icon
  props.context.node.input(icon)
}

async function createAndSelect() {
  if (!freeText.value || creating.value) return

  const match = galleryIcons.value.find((i) => i.icon === freeText.value)
  if (match) {
    selectIcon(match)
    return
  }

  creating.value = true
  try {
    const { gql } = await import('graphql-tag')
    const { default: queryBuilder } = await import('gql-query-builder')
    const apolloStore = useApolloStore()

    const name = str.titleCase(freeText.value.replace(/_/g, ' '))

    const qb = queryBuilder.mutation({
      operation: 'createIcon',
      variables: {
        input: {
          type: 'createIconInput!',
          value: {
            icon: freeText.value,
            name,
          },
        },
      },
      fields: [{ icon: ['id', 'icon', 'name'] }],
    })

    const { data } = await apolloStore.mutate({
      mutation: gql(qb.query),
      variables: qb.variables,
    })

    const newIcon = data.createIcon.icon
    galleryIcons.value.push(newIcon)
    selectIcon(newIcon)
  } catch (err) {
    console.error('Error al crear icono:', err)
  } finally {
    creating.value = false
  }
}

function selectFromGallery(icon: any) {
  selectIcon(icon)
  galleryOpen.value = false
}
</script>
