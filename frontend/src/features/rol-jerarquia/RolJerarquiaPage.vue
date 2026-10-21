<!--
  Jerarquía de roles (`/configuracion/roles`), con el mismo editor de árbol
  que los menús: cada rol cuelga del rol que lo incluye, y ese rol de arriba
  ve todo lo que ven los de abajo (menús, cajas de `/administracion`). A la
  derecha, los roles que no están en la jerarquía.

  El árbol admite un solo padre por rol y el orden entre hermanos no se
  guarda (el backend los devuelve por nombre). Ver `App\Rol\JerarquiaRoles`.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <PageHead title="" />
      </template>
      <template #end>
        <div class="flex flex-wrap items-center gap-3">
          <span v-if="dirty" class="text-sm text-amber-600">Jerarquía sin guardar</span>
          <Button label="Descartar" size="small" text severity="secondary" :disabled="!dirty || saving" @click="discard" />
          <Button label="Guardar jerarquía" size="small" :disabled="!dirty || saving" :loading="saving" @click="save">
            <template #icon><icon name="save-outline" class="mr-1" /></template>
          </Button>
        </div>
      </template>
    </Toolbar>

    <Message v-if="error" severity="warn">{{ error }}</Message>

    <div class="grid grid-cols-1 gap-3 @2xl:grid-cols-[minmax(0,1fr)_18rem]">
      <div class="card flex min-h-[24rem] flex-col gap-3 p-3">
        <p class="text-xs text-muted-color">
          Un rol ve todo lo que ven los roles que tiene debajo (menús y administración). Arrastrá
          <icon name="drag-indicator" class="inline align-text-bottom" /> arriba/abajo para elegir
          dónde va y a izquierda/derecha para cambiar de nivel. Soltar fuera del recuadro quita el rol
          de la jerarquía junto con los que tiene debajo. Un rol sin roles debajo y sin rol encima no
          forma jerarquía: al guardar vuelve a la derecha.
        </p>
        <div v-if="loading" class="flex flex-1 items-center justify-center">
          <ProgressSpinner style="width: 2rem; height: 2rem" />
        </div>
        <OutlineTree
          v-else
          ref="outline"
          :rows="rows"
          empty-text="Arrastrá roles de la derecha hasta aquí"
          remove-title="Quitar de la jerarquía"
          @update:rows="rows = $event"
        >
          <template #row="{ id, depth }">
            <icon :name="depth === 0 ? 'shield-person-outline' : 'subdirectory-arrow-right'" class="text-primary" />
            <div class="outline-text">
              <span class="outline-label">{{ nombre(id) }}</span>
              <span class="outline-sub">{{ incluye(id) }}</span>
            </div>
          </template>
        </OutlineTree>
      </div>

      <div class="card flex flex-col gap-2 p-3">
        <h3 class="font-semibold">Roles fuera de la jerarquía</h3>
        <div v-if="loading" class="flex justify-center py-6">
          <ProgressSpinner style="width: 1.5rem; height: 1.5rem" />
        </div>
        <OutlinePalette
          v-else
          :items="libres"
          :target="outline"
          :id-of="(rol) => rol.id"
          :text-of="(rol) => rol.nombre"
          placeholder="Filtrar roles"
          empty-text="Todos los roles están en la jerarquía"
        >
          <template #item="{ item }">
            <icon name="badge-outline" class="text-primary" />
            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ item.nombre }}</span>
          </template>
        </OutlinePalette>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { flatten, toTree, type OutlineRow } from '@/core/outline/outline'
import { notify } from '@/core/notify'
import OutlinePalette from '@/shared/outline/OutlinePalette.vue'
import OutlineTree from '@/shared/outline/OutlineTree.vue'
import { fetchJerarquia, fetchRoles, saveJerarquia, type RolDto } from './api'

defineOptions({ name: 'RolJerarquiaPage' })

const confirm = useConfirm()
const outline = ref<InstanceType<typeof OutlineTree> | null>(null)

const roles = ref<RolDto[]>([])
const rows = ref<OutlineRow[]>([])
const baseline = ref('')
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const porId = computed(() => new Map(roles.value.map((rol) => [rol.id, rol])))
const nombre = (id: number) => porId.value.get(id)?.nombre ?? `#${id}`

/**
 * Lo que se guardaría: un rol suelto en la raíz (sin roles debajo) no forma
 * jerarquía y vuelve a la derecha al guardar, así que no cuenta como cambio.
 */
const snapshot = (list: OutlineRow[]) =>
  JSON.stringify(toTree(list).filter((node) => node.children?.length))
const dirty = computed(() => !loading.value && snapshot(rows.value) !== baseline.value)

const libres = computed(() => {
  const usados = new Set(rows.value.map((row) => row.id))
  return roles.value.filter((rol) => !usados.has(rol.id))
})

/** "Incluye N roles" o "Sin roles debajo", según los descendientes de la fila. */
function incluye(id: number): string {
  const index = rows.value.findIndex((row) => row.id === id)
  const depth = rows.value[index]?.depth ?? 0
  let total = 0
  for (let k = index + 1; k < rows.value.length && rows.value[k]!.depth > depth; k++) total++
  return total ? `Incluye ${total} ${total === 1 ? 'rol' : 'roles'}` : 'Sin roles debajo'
}

function apply(tree: Parameters<typeof flatten>[0]) {
  rows.value = flatten(tree)
  baseline.value = snapshot(rows.value)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [lista, tree] = await Promise.all([fetchRoles(), fetchJerarquia()])
    roles.value = lista
    apply(tree)
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    apply(await saveJerarquia(toTree(rows.value)))
    notify.success('Jerarquía de roles guardada')
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    saving.value = false
  }
}

function discard() {
  rows.value = flatten(JSON.parse(baseline.value))
}

onBeforeRouteLeave(
  () =>
    !dirty.value ||
    new Promise<boolean>((resolve) =>
      confirm.require({
        header: 'Cambios sin guardar',
        message: 'Se perderán los cambios de la jerarquía de roles.',
        acceptLabel: 'Descartar',
        rejectLabel: 'Cancelar',
        accept: () => resolve(true),
        reject: () => resolve(false),
        onHide: () => resolve(false),
      }),
    ),
)

onMounted(load)
</script>
