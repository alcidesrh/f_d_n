<!--
  Constructor de menús de navegación (ADR-018).

  Pestaña "Menús": a la izquierda los menús; en el centro el menú abierto
  (nombre, roles que lo ven y su árbol, editado con drag & drop); a la
  derecha la paleta de ítems navegables que aún no están en el menú.
  Pestaña "Áreas de la UI": qué menús se despliegan en cada área del shell
  y en qué orden.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <PageHead title="" />
      </template>
      <template #end>
        <div class="flex items-center gap-3">
          <span v-if="tab === 'menus' && store.treeDirty" class="text-sm text-amber-600">
            Árbol sin guardar
          </span>
          <span v-if="tab === 'areas' && store.layoutDirty" class="text-sm text-amber-600">
            Distribución sin guardar
          </span>
          <Button
            v-if="tab === 'menus'"
            label="Guardar árbol"
            size="small"
            :disabled="!store.treeDirty || store.saving"
            :loading="store.saving"
            @click="run(() => store.saveTree(), 'Árbol del menú guardado')"
          >
            <template #icon><icon name="device-floppy" class="mr-1" /></template>
          </Button>
          <Button
            v-else
            label="Guardar distribución"
            size="small"
            :disabled="!store.layoutDirty || store.saving"
            :loading="store.saving"
            @click="run(() => store.saveLayout(), 'Distribución de menús guardada')"
          >
            <template #icon><icon name="device-floppy" class="mr-1" /></template>
          </Button>
        </div>
      </template>
    </Toolbar>

    <Message v-if="store.error" severity="warn">{{ store.error }}</Message>

    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="menus">Menús ({{ store.menus.length }})</Tab>
        <Tab value="areas">Áreas de la UI</Tab>
      </TabList>
      <TabPanels class="px-0!">
        <TabPanel value="menus">
          <!-- Columnas según el ancho del contenido (no del viewport): las barras laterales lo reducen. -->
          <div
            class="grid grid-cols-1 gap-3 @2xl:grid-cols-[minmax(0,1fr)_16rem] @6xl:grid-cols-[13rem_minmax(0,1fr)_19rem]"
          >
            <!-- Menús -->
            <div class="card flex flex-col gap-2 p-2 @2xl:col-span-2 @6xl:col-span-1">
              <Button label="Nuevo menú" size="small" text @click="newMenu">
                <template #icon><icon name="plus" class="mr-1" /></template>
              </Button>
              <Listbox
                :model-value="store.selectedMenuId"
                :options="menuOptions"
                option-label="nombre"
                option-value="id"
                empty-message="Sin menús"
                class="menu-list"
                @change="onSelectMenu"
              />
            </div>

            <!-- Menú abierto -->
            <div class="@container card flex min-h-[24rem] flex-col gap-3 p-3">
              <form
                v-if="menuDraft"
                class="grid grid-cols-1 items-end gap-3 @xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto]"
                @submit.prevent="saveMenuDraft"
              >
                <label class="flex flex-col gap-1">
                  <span class="text-sm font-medium">Nombre</span>
                  <InputText v-model="menuDraft.nombre" size="small" required maxlength="255" />
                </label>
                <label class="flex flex-col gap-1">
                  <span class="text-sm font-medium">
                    Roles que lo ven
                    <span class="font-normal text-muted-color">(y sus ascendientes)</span>
                  </span>
                  <MultiSelect
                    v-model="menuDraft.roles"
                    :options="store.roles"
                    option-label="nombre"
                    option-value="id"
                    filter
                    display="chip"
                    size="small"
                    placeholder="Ningún rol: no lo ve nadie"
                  />
                </label>
                <div class="flex gap-1">
                  <Button
                    type="submit"
                    :label="menuDraft.id ? 'Guardar' : 'Crear'"
                    size="small"
                    :disabled="!menuDraft.nombre.trim() || !menuDraftDirty"
                  />
                  <Button
                    v-if="menuDraft.id"
                    size="small"
                    severity="danger"
                    text
                    title="Eliminar menú"
                    aria-label="Eliminar menú"
                    @click="removeMenu"
                  >
                    <template #icon><icon name="trash" /></template>
                  </Button>
                </div>
              </form>

              <div
                v-if="store.status === 'loading' || store.treeLoading"
                class="flex flex-1 items-center justify-center"
              >
                <ProgressSpinner style="width: 2rem; height: 2rem" />
              </div>
              <template v-else-if="store.selectedMenuId !== null">
                <p class="text-xs text-muted-color">
                  Arrastrá
                  <icon name="grip-vertical" class="inline align-text-bottom" /> arriba/abajo para
                  ordenar y a izquierda/derecha para cambiar de nivel. Soltar fuera del recuadro
                  quita el ítem (y sus hijos) del menú.
                </p>
                <MenuOutline
                  ref="outline"
                  :rows="store.rows"
                  :items-by-id="store.itemsById"
                  @update:rows="store.rows = $event"
                />
              </template>
              <div
                v-else-if="!menuDraft"
                class="flex flex-1 items-center justify-center text-muted-color"
              >
                Creá o elegí un menú.
              </div>
            </div>

            <!-- Paleta -->
            <div class="card flex flex-col gap-2 p-3">
              <div class="flex flex-col items-start gap-1">
                <h3 class="font-semibold">Ítems navegables</h3>
                <Button
                  label="Nuevo ítem"
                  size="small"
                  text
                  title="Nuevo ítem"
                  @click="editItem(null)"
                >
                  <template #icon><icon name="plus" class="mr-1" /></template>
                </Button>
              </div>
              <MenuItemPalette
                :items="store.paletteItems"
                :target="store.selectedMenuId !== null ? outline : null"
                @edit="editItem"
                @remove="removeItem"
              />
            </div>
          </div>
        </TabPanel>

        <TabPanel value="areas">
          <MenuLayoutEditor />
        </TabPanel>
      </TabPanels>
    </Tabs>

    <MenuItemDialog
      v-model:visible="itemDialog"
      :initial="itemDraft"
      :routes="store.routes"
      :saving="itemSaving"
      @save="saveItem"
    />
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { notify } from '@/core/notify'
import { numericId, type MenuItemDto } from './api'
import MenuItemDialog from './MenuItemDialog.vue'
import MenuItemPalette from './MenuItemPalette.vue'
import MenuLayoutEditor from './MenuLayoutEditor.vue'
import MenuOutline from './MenuOutline.vue'
import { useMenuBuilderStore, type MenuDraft, type MenuItemDraft } from './store'

defineOptions({ name: 'MenuBuilderPage' })

const store = useMenuBuilderStore()
const confirm = useConfirm()
const tab = ref<'menus' | 'areas'>('menus')
const outline = ref<InstanceType<typeof MenuOutline> | null>(null)

const menuOptions = computed(() =>
  store.menus.map((menu) => ({ id: numericId(menu.id), nombre: menu.nombre })),
)

// --- utilidades ------------------------------------------------------------

const message = (error: unknown) => (error instanceof Error ? error.message : String(error))

async function run(action: () => Promise<unknown>, success: string): Promise<boolean> {
  try {
    await action()
    notify.success(success)
    return true
  } catch (error) {
    notify.error(message(error))
    return false
  }
}

function ask(header: string, text: string, acceptLabel = 'Continuar'): Promise<boolean> {
  return new Promise((resolve) =>
    confirm.require({
      header,
      message: text,
      acceptLabel,
      rejectLabel: 'Cancelar',
      accept: () => resolve(true),
      reject: () => resolve(false),
      onHide: () => resolve(false),
    }),
  )
}

const confirmDiscardTree = () =>
  store.treeDirty
    ? ask('Cambios sin guardar', 'Se perderán los cambios del árbol del menú abierto.', 'Descartar')
    : Promise.resolve(true)

// --- menú: nombre y roles ------------------------------------------------

const menuDraft = ref<MenuDraft | null>(null)
const draftOf = (): MenuDraft | null =>
  store.selectedMenu
    ? {
        id: store.selectedMenu.id,
        nombre: store.selectedMenu.nombre,
        roles: store.selectedMenu.roles.map((role) => role.id),
      }
    : null
const menuDraftBaseline = ref('')
const menuDraftDirty = computed(
  () => !menuDraft.value?.id || JSON.stringify(menuDraft.value) !== menuDraftBaseline.value,
)

watch(
  () => store.selectedMenu,
  () => {
    menuDraft.value = draftOf()
    menuDraftBaseline.value = JSON.stringify(menuDraft.value)
  },
  { immediate: true },
)

async function onSelectMenu(event: { value: number | null }) {
  if (event.value === null || event.value === store.selectedMenuId) return
  if (await confirmDiscardTree()) await store.selectMenu(event.value)
}

async function newMenu() {
  if (!(await confirmDiscardTree())) return
  await store.selectMenu(null)
  menuDraft.value = { nombre: '', roles: [] }
}

async function saveMenuDraft() {
  const draft = menuDraft.value
  if (!draft) return
  const created = !draft.id
  const ok = await run(() => store.saveMenu(draft), created ? 'Menú creado' : 'Menú guardado')
  if (ok) {
    menuDraft.value = draftOf()
    menuDraftBaseline.value = JSON.stringify(menuDraft.value)
  }
}

async function removeMenu() {
  const menu = store.selectedMenu
  if (!menu) return
  if (
    await ask(
      'Eliminar menú',
      `Se eliminará «${menu.nombre}» y se quitará de todas las áreas.`,
      'Eliminar',
    )
  )
    await run(() => store.removeMenu(menu), 'Menú eliminado')
}

// --- ítems navegables ----------------------------------------------------

const itemDialog = ref(false)
const itemSaving = ref(false)
const itemDraft = ref<MenuItemDraft>({ nombre: '', icon: '', route: '' })

function editItem(item: MenuItemDto | null) {
  itemDraft.value = item
    ? { id: item.id, nombre: item.nombre, icon: item.icon.icon, route: item.route.id }
    : { nombre: '', icon: '', route: '' }
  itemDialog.value = true
}

async function saveItem(draft: MenuItemDraft) {
  itemSaving.value = true
  try {
    if (await run(() => store.saveItem(draft), draft.id ? 'Ítem guardado' : 'Ítem creado'))
      itemDialog.value = false
  } finally {
    itemSaving.value = false
  }
}

async function removeItem(item: MenuItemDto) {
  if (
    await ask(
      'Eliminar ítem',
      `Se eliminará «${item.nombre}» de todos los menús que lo usan (sus hijos suben un nivel).`,
      'Eliminar',
    )
  )
    await run(() => store.removeItem(item), 'Ítem eliminado')
}

onBeforeRouteLeave(() => confirmDiscardTree())

onMounted(() => store.load())
</script>

<style scoped>
/* Apilada sobre el editor la lista de menús es compacta; en su columna (≥ @6xl), alta. */
.menu-list :deep(.p-listbox-list-container) {
  max-height: 9rem;
}
@container (min-width: 72rem) {
  .menu-list :deep(.p-listbox-list-container) {
    max-height: calc(100vh - 18rem);
  }
}
</style>
