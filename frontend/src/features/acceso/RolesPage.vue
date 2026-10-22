<!--
  Roles (`/configuracion/roles`, ADR-039): cada rol es un puesto con las
  capacidades del catálogo que concede (por área; las del CRUD genérico en
  una fila por entidad) y sus límites. Plano: no hereda de otro rol. Dónde
  rige lo decide cada asignación, no el rol.

  Ver con `iam.ver`; crear, editar y eliminar con `iam.roles`. El backend
  impide agregar lo que quien edita no tiene, o donde el rol ya está asignado
  y él no lo tiene (`App\Acceso\Delegacion`). El de sistema no se edita; el
  base (`ROLE_USER`) lo tienen todos y no lleva capacidades.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <PageHead title="" />
      </template>
      <template #end>
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink :to="{ name: 'accesos' }" class="text-sm text-primary">¿Quién puede…?</RouterLink>
          <Button v-if="puedeEditar" label="Nuevo rol" size="small" @click="nuevo">
            <template #icon><icon name="add" class="mr-1" /></template>
          </Button>
        </div>
      </template>
    </Toolbar>

    <Message v-if="error" severity="warn" :closable="false">{{ error }}</Message>

    <div class="roles-layout">
      <!-- Lista de roles -->
      <aside class="card flex flex-col gap-2 p-3">
        <IconField>
          <InputIcon><icon name="search" /></InputIcon>
          <InputText v-model="busqueda" placeholder="Buscar rol o capacidad" fluid />
        </IconField>
        <div v-if="cargando" class="flex justify-center py-6"><ProgressSpinner style="width: 1.5rem; height: 1.5rem" /></div>
        <ul v-else class="roles-lista" role="listbox" aria-label="Roles">
          <li v-for="r in rolesVisibles" :key="r.id">
            <button type="button" role="option" class="rol tap-target" :class="{ 'rol--activo': r.id === seleccion?.id }" :aria-selected="r.id === seleccion?.id" @click="elegir(r)">
              <icon :name="r.sistema ? 'shield-lock-outline' : r.base ? 'groups-outline' : 'badge-outline'" class="text-primary" />
              <span class="min-w-0 flex-1 text-left">
                <span class="block truncate text-sm font-medium">{{ r.descripcion || r.nombre }}</span>
                <span class="block truncate text-xs text-muted-color">{{ r.nombre }}</span>
              </span>
              <span class="rol-cuenta" v-tooltip.left="`${r.usuarios} con asignación vigente`">
                <icon name="person-outline" size=".85rem" />{{ r.usuarios }}
              </span>
            </button>
          </li>
          <li v-if="!rolesVisibles.length" class="py-4 text-center text-sm text-muted-color">Ningún rol coincide.</li>
        </ul>
      </aside>

      <!-- Editor -->
      <section class="card flex min-w-0 flex-col gap-4 p-3 md:p-4">
        <div v-if="!borrador" class="flex flex-1 flex-col items-center justify-center gap-2 py-10 text-center text-muted-color">
          <icon name="badge-outline" size="2rem" />
          <p>Elija un rol para ver qué concede{{ puedeEditar ? ", o cree uno" : "" }}.</p>
        </div>
        <template v-else>
          <header class="flex flex-wrap items-start gap-3">
            <div class="grid min-w-0 flex-1 gap-2 @xl:grid-cols-2">
              <label class="campo">
                <span>Nombre</span>
                <InputText v-model="borrador.nombre" :disabled="soloLectura" placeholder="ROLE_VENDEDOR o «Vendedor de taquilla»" fluid />
              </label>
              <label class="campo">
                <span>Descripción</span>
                <InputText v-model="borrador.descripcion" :disabled="soloLectura" placeholder="Qué hace quien tiene este rol" fluid />
              </label>
            </div>
            <div class="flex items-center gap-2 pt-5">
              <Tag v-if="actual?.sistema" severity="contrast" value="Sistema: todo" />
              <Tag v-else-if="actual?.base" severity="secondary" value="Base: lo tienen todos" />
              <Tag v-else severity="info" :value="`${elegidas.length} capacidades`" />
            </div>
          </header>

          <Message v-if="actual?.sistema" severity="secondary" :closable="false">
            El rol de sistema tiene todas las capacidades, también las que se agreguen después, y no se edita. Se otorga con cuidado: quien lo tiene puede todo.
          </Message>
          <Message v-else-if="actual?.base" severity="secondary" :closable="false">
            Lo tienen todos los usuarios sin asignárselo. Solo sirve para mostrar menús y cajas de administración a todos: no lleva capacidades.
          </Message>

          <template v-if="!actual?.sistema && !actual?.base">
            <div v-for="g in grupos" :key="g.area" class="area">
              <div class="area-titulo">
                <Checkbox :model-value="estadoArea(g) === true" :indeterminate="estadoArea(g) === null" binary :disabled="soloLectura" :input-id="`area-${g.area}`" @update:model-value="marcarArea(g, $event)" />
                <label :for="`area-${g.area}`" class="font-semibold">{{ g.area }}</label>
                <span class="text-xs text-muted-color">{{ cuantasDe(g) }}</span>
              </div>

              <ul v-if="g.dominio.length" class="capacidades">
                <li v-for="c in g.dominio" :key="c.codigo" class="capacidad">
                  <Checkbox v-model="marcadas[c.codigo]" binary :disabled="soloLectura" :input-id="c.codigo" />
                  <label :for="c.codigo" class="min-w-0 flex-1">
                    <span class="block text-sm">{{ c.nombre }}</span>
                    <span class="flex flex-wrap gap-1 text-xs text-muted-color">
                      <code>{{ c.codigo }}</code>
                      <span v-for="d in c.dimensiones" :key="d" class="dim">por {{ d === "empresa" ? "empresa" : "estación" }}</span>
                    </span>
                  </label>
                  <div v-for="l in limitesDe(c)" :key="l.codigo" class="limite">
                    <label :for="`l-${l.codigo}`" class="text-xs text-muted-color">{{ l.nombre }}</label>
                    <InputNumber v-model="borrador.limites[l.codigo]" :input-id="`l-${l.codigo}`" :min="0" :max="100000" placeholder="Sin límite" :disabled="soloLectura || !marcadas[c.codigo]" size="small" input-class="w-24" />
                  </div>
                </li>
              </ul>

              <div v-if="g.recursos.length" class="matriz" role="table" :aria-label="`Datos de ${g.area}`">
                <div class="matriz-fila matriz-cabecera" role="row">
                  <span role="columnheader">Datos (listados y formularios)</span>
                  <span role="columnheader">Ver</span>
                  <span role="columnheader">Editar</span>
                  <span role="columnheader">Eliminar</span>
                </div>
                <div v-for="r in g.recursos" :key="r.recurso" class="matriz-fila" role="row">
                  <span role="cell" class="truncate text-sm">{{ r.nombre }}</span>
                  <span v-for="op in OPERACIONES" :key="op" role="cell">
                    <Checkbox v-if="r[op]" v-model="marcadas[r[op]!.codigo]" binary :disabled="soloLectura" :aria-label="`${r.nombre}: ${op}`" v-tooltip.top="r[op]!.codigo" />
                    <span v-else class="text-xs text-muted-color" v-tooltip.top="op === 'ver' ? 'La ve cualquiera con sesión' : 'No se modifica por el listado genérico'">{{ op === "ver" ? "libre" : "—" }}</span>
                  </span>
                </div>
              </div>
            </div>
          </template>

          <footer v-if="!soloLectura" class="sticky bottom-0 -mx-3 flex flex-wrap items-center justify-end gap-2 border-t border-surface-200 bg-surface-0 px-3 pt-3 md:-mx-4 md:px-4">
            <span v-if="cambios" class="mr-auto text-sm text-amber-600">{{ cambios }}</span>
            <Button v-if="actual && !actual.base" label="Eliminar" severity="danger" text size="small" :disabled="guardando" @click="eliminar" />
            <Button label="Descartar" severity="secondary" text size="small" :disabled="!cambios || guardando" @click="descartar" />
            <Button :label="actual ? 'Guardar rol' : 'Crear rol'" size="small" :loading="guardando" :disabled="!cambios && !!actual" @click="guardar" />
          </footer>
        </template>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import * as api from '@/core/acceso/api'
import { agruparCatalogo, cambiosDeCapacidades, type GrupoCapacidades } from '@/core/acceso/reglas'
import type { CatalogoAcceso, DefinicionCapacidad, Rol, RolPayload } from '@/core/acceso/tipos'
import { notify } from '@/core/notify'

defineOptions({ name: 'RolesPage' })

const OPERACIONES = ['ver', 'editar', 'eliminar'] as const

const confirm = useConfirm()
const catalogo = ref<CatalogoAcceso | null>(null)
const roles = ref<Rol[]>([])
const cargando = ref(true)
const guardando = ref(false)
const error = ref<string | null>(null)
const busqueda = ref('')
const seleccion = ref<Rol | null>(null)

interface Borrador {
  nombre: string
  descripcion: string
  limites: Record<string, number | null>
}
const borrador = ref<Borrador | null>(null)
/** Casillas marcadas por código (el `Set` del borrador se arma de aquí). */
const marcadas = reactive<Record<string, boolean>>({})

const puedeEditar = computed(() => catalogo.value?.puede.roles ?? false)
const actual = computed(() => (borrador.value ? seleccion.value : null))
const soloLectura = computed(() => !puedeEditar.value || Boolean(actual.value?.sistema))
const grupos = computed(() => agruparCatalogo(catalogo.value?.capacidades ?? []))
const nombres = computed(() => new Map((catalogo.value?.capacidades ?? []).map((c) => [c.codigo, c.nombre])))

const rolesVisibles = computed(() => {
  const q = busqueda.value.trim().toLocaleLowerCase()
  if (!q) return roles.value
  return roles.value.filter((r) => `${r.nombre} ${r.descripcion ?? ''} ${r.capacidades.map((c) => `${c} ${nombres.value.get(c) ?? ''}`).join(' ')}`.toLocaleLowerCase().includes(q))
})

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    ;[catalogo.value, roles.value] = await Promise.all([api.fetchCatalogo(), api.fetchRoles()])
    if (seleccion.value) {
      const otra = roles.value.find((r) => r.id === seleccion.value!.id)
      if (otra) elegir(otra)
      else {
        seleccion.value = null
        borrador.value = null
      }
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudieron cargar los roles.'
  } finally {
    cargando.value = false
  }
}
void cargar()

function llenar(capacidades: string[]) {
  for (const k of Object.keys(marcadas)) delete marcadas[k]
  for (const c of capacidades) marcadas[c] = true
}

function elegir(r: Rol) {
  seleccion.value = r
  borrador.value = { nombre: r.nombre, descripcion: r.descripcion ?? '', limites: { ...r.limites } }
  llenar(r.capacidades)
}

function nuevo() {
  seleccion.value = null
  borrador.value = { nombre: '', descripcion: '', limites: {} }
  llenar([])
}

const elegidas = computed(() => Object.entries(marcadas).filter(([, v]) => v).map(([k]) => k).sort())

const cambios = computed(() => {
  if (!borrador.value) return ''
  if (!actual.value) return borrador.value.nombre.trim() ? 'Rol nuevo sin guardar' : ''
  const { agregadas, quitadas } = cambiosDeCapacidades(actual.value.capacidades, elegidas.value)
  const datos = borrador.value.nombre !== actual.value.nombre || borrador.value.descripcion !== (actual.value.descripcion ?? '')
  const limites = JSON.stringify(limpios(borrador.value.limites)) !== JSON.stringify(limpios(actual.value.limites))
  const partes = [agregadas.length ? `+${agregadas.length}` : '', quitadas.length ? `−${quitadas.length}` : '', datos ? 'datos' : '', limites ? 'límites' : ''].filter(Boolean)
  return partes.length ? `Sin guardar: ${partes.join(', ')}` : ''
})

function limpios(l: Record<string, number | null | undefined>): Record<string, number> {
  return Object.fromEntries(Object.entries(l).filter(([, v]) => v !== null && v !== undefined).sort(([a], [b]) => a.localeCompare(b))) as Record<string, number>
}

function limitesDe(c: DefinicionCapacidad) {
  return (catalogo.value?.limites ?? []).filter((l) => l.capacidad === c.codigo)
}

function codigosDe(g: GrupoCapacidades): string[] {
  return [...g.dominio.map((c) => c.codigo), ...g.recursos.flatMap((r) => OPERACIONES.map((op) => r[op]?.codigo).filter((c): c is string => Boolean(c)))]
}

/** true: todas; false: ninguna; null: algunas. */
function estadoArea(g: GrupoCapacidades): boolean | null {
  const codigos = codigosDe(g)
  const n = codigos.filter((c) => marcadas[c]).length
  return n === 0 ? false : n === codigos.length ? true : null
}

function marcarArea(g: GrupoCapacidades, valor: boolean) {
  for (const c of codigosDe(g)) marcadas[c] = valor
}

function cuantasDe(g: GrupoCapacidades): string {
  const codigos = codigosDe(g)
  return `${codigos.filter((c) => marcadas[c]).length} de ${codigos.length}`
}

function descartar() {
  if (actual.value) elegir(actual.value)
  else borrador.value = null
}

function payload(): RolPayload {
  const b = borrador.value!
  const capacidades = elegidas.value
  // Un límite solo vale con su capacidad marcada.
  const limites = Object.fromEntries(
    Object.entries(limpios(b.limites)).filter(([codigo]) => capacidades.includes((catalogo.value?.limites ?? []).find((l) => l.codigo === codigo)?.capacidad ?? '')),
  )
  return { nombre: b.nombre.trim(), descripcion: b.descripcion.trim() || null, capacidades, limites }
}

async function guardar() {
  if (!borrador.value?.nombre.trim()) {
    notify.warning('El rol necesita un nombre.')
    return
  }
  const r = actual.value
  if (r && r.usuarios > 0) {
    const { agregadas, quitadas } = cambiosDeCapacidades(r.capacidades, elegidas.value)
    if (agregadas.length || quitadas.length) {
      const ok = await new Promise<boolean>((resolve) =>
        confirm.require({
          header: 'Cambiar un rol asignado',
          message: `Lo tienen ${r.usuarios} ${r.usuarios === 1 ? 'usuario' : 'usuarios'}: el cambio les rige desde ya.${agregadas.length ? `\n\nGanan: ${agregadas.map((c) => nombres.value.get(c) ?? c).join(', ')}.` : ''}${quitadas.length ? `\n\nPierden: ${quitadas.map((c) => nombres.value.get(c) ?? c).join(', ')}.` : ''}`,
          acceptLabel: 'Guardar',
          rejectLabel: 'Cancelar',
          accept: () => resolve(true),
          reject: () => resolve(false),
          onHide: () => resolve(false),
        }),
      )
      if (!ok) return
    }
  }
  guardando.value = true
  try {
    const guardado = r ? await api.guardarRol(r.id, payload()) : await api.crearRol(payload())
    notify.success(r ? 'Rol guardado.' : 'Rol creado.')
    await cargar()
    const nuevoRol = roles.value.find((x) => x.id === guardado.id)
    if (nuevoRol) elegir(nuevoRol)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo guardar el rol.')
  } finally {
    guardando.value = false
  }
}

function eliminar() {
  const r = actual.value
  if (!r) return
  confirm.require({
    header: 'Eliminar rol',
    message: `¿Eliminar «${r.descripcion || r.nombre}»? Solo se puede si nadie lo tiene asignado.`,
    acceptLabel: 'Eliminar',
    rejectLabel: 'Cancelar',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await api.eliminarRol(r.id)
        notify.success('Rol eliminado.')
        seleccion.value = null
        borrador.value = null
        await cargar()
      } catch (e) {
        notify.error(e instanceof Error ? e.message : 'No se pudo eliminar el rol.')
      }
    },
  })
}
</script>

<style scoped>
.roles-layout {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(0, 1fr);
  @container (min-width: 52rem) {
    grid-template-columns: 18rem minmax(0, 1fr);
    align-items: start;
    & > aside {
      position: sticky;
      top: 0.5rem;
    }
  }
}
.roles-lista {
  display: grid;
  gap: 0.15rem;
  max-height: min(40dvh, 22rem);
  overflow-y: auto;
  margin: 0;
  padding: 0;
  list-style: none;
  @container (min-width: 52rem) {
    max-height: calc(100dvh - 14rem);
  }
}
.rol {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 0.5rem;
  border-radius: 0.4rem;
  cursor: pointer;
  &:hover {
    background: var(--p-content-hover-background);
  }
}
.rol--activo {
  background: var(--p-primary-50);
  box-shadow: inset 3px 0 0 var(--p-primary-500);
}
.rol-cuenta {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  font-size: 0.75rem;
  color: var(--p-surface-500);
}
.campo {
  display: grid;
  gap: 0.25rem;
  font-size: 0.8rem;
  color: var(--p-surface-600);
}
.area {
  display: grid;
  gap: 0.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--p-surface-200);
}
.area-titulo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.capacidades {
  display: grid;
  gap: 0.35rem;
  margin: 0;
  padding: 0;
  list-style: none;
  @container (min-width: 64rem) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.capacidad {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.35rem 0.25rem;
  & > label {
    cursor: pointer;
  }
  code {
    font-size: 0.7rem;
  }
}
.dim {
  padding: 0 0.35rem;
  border-radius: 999px;
  background: var(--p-surface-100);
}
.limite {
  display: grid;
  gap: 0.15rem;
  margin-left: 1.75rem;
  flex-basis: 100%;
  @container (min-width: 40rem) {
    flex-basis: auto;
  }
}
.matriz {
  display: grid;
  overflow-x: auto;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
}
.matriz-fila {
  display: grid;
  grid-template-columns: minmax(7rem, 1fr) repeat(3, 3.4rem);
  @container (min-width: 40rem) {
    grid-template-columns: minmax(9rem, 1fr) repeat(3, 4.5rem);
  }
  align-items: center;
  gap: 0.25rem;
  padding: 0.3rem 0.6rem;
  & > span:not(:first-child) {
    text-align: center;
  }
  & + & {
    border-top: 1px solid var(--p-surface-100);
  }
}
.matriz-cabecera {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--p-surface-600);
  background: var(--p-surface-50);
}
</style>
