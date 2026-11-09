<!--
  Accesos (`/configuracion/accesos`, ADR-039): los usuarios de un rol, con
  modificar y revocar cada asignación (con `iam.asignar`, sin escalar
  privilegios: lo dice `puedeEditar`); «¿Quién puede…?» —una capacidad, y si
  se quiere en una empresa y una estación: quién la tiene y por qué
  asignación— y los usuarios activos que no pueden hacer nada (los que la
  migración no pudo ubicar, para revisarlos). Con `iam.ver`.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar>
      <template #start>
        <PageHead title="" />
      </template>
      <template #end>
        <RouterLink :to="{ name: 'acceso-roles' }" class="text-sm text-primary">Roles y capacidades</RouterLink>
      </template>
    </Toolbar>

    <Tabs v-model:value="pestana">
      <TabList>
        <Tab value="rol">Por rol</Tab>
        <Tab value="quien">¿Quién puede?</Tab>
        <Tab value="sin">Sin alcance <Badge v-if="sinAlcance.length" :value="sinAlcance.length" severity="warn" class="ml-1" /></Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="quien">
          <form class="filtros" @submit.prevent="buscar">
            <label class="campo">
              <span>Capacidad</span>
              <Select v-model="capacidad" :options="opcionesCapacidad" option-label="nombre" option-value="codigo" option-group-label="area" option-group-children="items" placeholder="Qué puede hacer" filter :filter-fields="['nombre', 'codigo']" fluid />
            </label>
            <label class="campo">
              <span>Empresa (opcional)</span>
              <Select v-model="empresa" :options="(catalogo?.empresas ?? []).filter((e) => e.activo !== false)" option-label="nombre" option-value="id" placeholder="En cualquiera" show-clear fluid />
            </label>
            <label class="campo">
              <span>Estación (opcional)</span>
              <Select v-model="estacion" :options="catalogo?.estaciones ?? []" option-label="nombre" option-value="id" placeholder="En cualquiera" filter show-clear fluid />
            </label>
            <Button type="submit" label="Buscar" :loading="buscando" :disabled="!capacidad" class="self-end" />
          </form>

          <Message v-if="resultado && !resultado.length" severity="secondary" :closable="false" class="mt-3">Nadie puede eso ahí.</Message>
          <DataTable v-if="resultado?.length" :value="resultado" size="small" striped-rows class="mt-3" data-key="asignacion" paginator :rows="25" :rows-per-page-options="[25, 50, 100]">
            <Column header="Usuario">
              <template #body="{ data }">
                <RouterLink :to="{ name: 'acceso-usuario', params: { id: data.usuario.id } }" class="text-primary">{{ data.usuario.username }}</RouterLink>
                <div class="text-xs text-muted-color">{{ data.usuario.nombre }}</div>
              </template>
            </Column>
            <Column field="rol" header="Por el rol" />
            <Column header="Alcance">
              <template #body="{ data }">
                <span class="text-sm">{{ data.ambito.agencia ? `Agencia ${data.usuario.agencia ?? data.ambito.agencia}` : describir(data.ambito) }}</span>
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <TabPanel value="rol">
          <form class="filtros filtros-rol" @submit.prevent="buscarRol">
            <label class="campo">
              <span>Rol</span>
              <Select v-model="rolId" :options="roles" option-label="nombre" option-value="id" placeholder="Elija el rol" filter :filter-fields="['nombre', 'descripcion']" fluid />
            </label>
            <Button type="submit" label="Buscar" :loading="buscandoRol" :disabled="rolId === null" class="self-end" />
          </form>

          <Message v-if="resultadoRol && !resultadoRol.length" severity="secondary" :closable="false" class="mt-3">Ningún usuario tiene este rol.</Message>
          <DataTable v-if="resultadoRol?.length" :value="resultadoRol" size="small" striped-rows class="mt-3" data-key="id" paginator :rows="25" :rows-per-page-options="[25, 50, 100]">
            <Column header="Usuario">
              <template #body="{ data }">
                <RouterLink :to="{ name: 'acceso-usuario', params: { id: data.usuario.id } }" class="text-primary">{{ data.usuario.username }}</RouterLink>
                <div class="text-xs text-muted-color">{{ data.usuario.nombre }}</div>
              </template>
            </Column>
            <Column header="Alcance">
              <template #body="{ data }">
                <span class="text-sm">{{ alcanceDeAsignacion(data) }}</span>
              </template>
            </Column>
            <Column header="Vigencia">
              <template #body="{ data }">
                <Tag :severity="data.vigente ? 'success' : 'secondary'" :value="data.vigente ? textoVigencia(data) : `Fuera de vigencia · ${textoVigencia(data)}`" />
              </template>
            </Column>
            <Column header="Acciones">
              <template #body="{ data }">
                <div v-if="data.puedeEditar" class="flex gap-1">
                  <Button text rounded size="small" class="tap-target" aria-label="Modificar" v-tooltip.top="'Modificar'" @click="modificarAsignacion(data)"><icon name="edit-outline" /></Button>
                  <Button text rounded size="small" severity="danger" class="tap-target" aria-label="Revocar" v-tooltip.top="'Revocar'" @click="revocarAsignacion(data)"><icon name="remove-moderator-outline" /></Button>
                </div>
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <TabPanel value="sin">
          <p class="mb-3 text-sm text-muted-color">Usuarios activos sin ninguna asignación vigente que les conceda algo: sin roles, o con un alcance vacío (la migración no encontró sus empresas o estaciones). Entran, pero no pueden operar.</p>
          <DataTable :value="sinAlcance" size="small" striped-rows :loading="cargandoSin" paginator :rows="25">
            <template #empty>Todos los usuarios activos tienen algo que hacer.</template>
            <Column header="Usuario">
              <template #body="{ data }">
                <RouterLink :to="{ name: 'acceso-usuario', params: { id: data.id } }" class="text-primary">{{ data.username }}</RouterLink>
                <div class="text-xs text-muted-color">{{ data.nombre }}</div>
              </template>
            </Column>
            <Column header="Lugar">
              <template #body="{ data }">{{ data.agencia ? `Agencia ${data.agencia}` : (data.estacion ?? "—") }}</template>
            </Column>
            <Column field="asignaciones" header="Asignaciones" />
          </DataTable>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <AsignacionDialog v-if="catalogo" v-model:visible="dialogo" :usuario-id="editando?.usuario.id ?? 0" :agencia="editando?.agencia ?? null" :roles="roles" :catalogo="catalogo" :asignacion="editando" @guardado="recargarRol" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import * as api from '@/core/acceso/api'
import { describirAmbito } from '@/core/acceso/reglas'
import type { AsignacionDeRol, Ambito, CatalogoAcceso, FilaQuienPuede, FilaSinAlcance, Rol } from '@/core/acceso/tipos'
import { notify } from '@/core/notify'
import AsignacionDialog from './AsignacionDialog.vue'

defineOptions({ name: 'AccesosPage' })

const confirm = useConfirm()
const pestana = ref('rol')
const catalogo = ref<CatalogoAcceso | null>(null)
const capacidad = ref<string | null>(null)
const empresa = ref<number | null>(null)
const estacion = ref<number | null>(null)
const resultado = ref<FilaQuienPuede[] | null>(null)
const buscando = ref(false)
const sinAlcance = ref<FilaSinAlcance[]>([])
const cargandoSin = ref(false)
const roles = ref<Rol[]>([])
const rolId = ref<number | null>(null)
const resultadoRol = ref<AsignacionDeRol[] | null>(null)
const buscandoRol = ref(false)
/** El rol de las filas a la vista: el Select puede haber cambiado sin buscar. */
const rolConsultado = ref<number | null>(null)
const dialogo = ref(false)
const editando = ref<AsignacionDeRol | null>(null)

const opcionesCapacidad = computed(() => {
  const grupos = new Map<string, { area: string; items: Array<{ codigo: string; nombre: string }> }>()
  for (const c of catalogo.value?.capacidades ?? []) {
    let g = grupos.get(c.area)
    if (!g) grupos.set(c.area, (g = { area: c.area, items: [] }))
    g.items.push({ codigo: c.codigo, nombre: c.nombre })
  }
  return [...grupos.values()]
})

const nombres = computed(() => ({
  empresas: new Map((catalogo.value?.empresas ?? []).map((e) => [e.id, e.nombre])),
  estaciones: new Map((catalogo.value?.estaciones ?? []).map((e) => [e.id, e.nombre])),
}))
const describir = (a: Ambito) => describirAmbito(a, nombres.value)

async function cargarSinAlcance() {
  cargandoSin.value = true
  try {
    sinAlcance.value = await api.fetchSinAlcance()
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo cargar la lista.')
  } finally {
    cargandoSin.value = false
  }
}

void api.fetchCatalogo().then((c) => (catalogo.value = c)).catch((e) => notify.error(e instanceof Error ? e.message : 'No se pudo cargar el catálogo.'))
void cargarSinAlcance()
void api.fetchRoles().then((r) => (roles.value = r)).catch((e) => notify.error(e instanceof Error ? e.message : 'No se pudieron cargar los roles.'))
watch(pestana, (p) => p === 'sin' && void cargarSinAlcance())

/** Lo que cubre la asignación: su agencia, o sus empresas y estaciones («todas» si es null). */
function alcanceDeAsignacion(a: AsignacionDeRol): string {
  if (a.agencia) return `Agencia ${a.agencia}`
  const lista = (items: { nombre: string }[] | null, todas: string) => (items === null ? todas : items.map((i) => i.nombre).join(', '))
  return `Empresas: ${lista(a.empresas, 'todas')} · Estaciones: ${lista(a.estaciones, 'todas')}`
}

function textoVigencia(a: AsignacionDeRol): string {
  if (a.desde && a.hasta) return `${a.desde} al ${a.hasta}`
  if (a.desde) return `desde ${a.desde}`
  if (a.hasta) return `hasta ${a.hasta}`
  return 'Sin vencimiento'
}

async function consultarRol(id: number) {
  buscandoRol.value = true
  try {
    resultadoRol.value = await api.fetchUsuariosRol(id)
    rolConsultado.value = id
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo consultar.')
  } finally {
    buscandoRol.value = false
  }
}

function buscarRol() {
  if (rolId.value !== null) void consultarRol(rolId.value)
}

/** Tras modificar o revocar: la fila puede cambiar de datos o dejar de ser de este rol. */
function recargarRol() {
  if (rolConsultado.value !== null) void consultarRol(rolConsultado.value)
}

function modificarAsignacion(a: AsignacionDeRol) {
  editando.value = a
  dialogo.value = true
}

function revocarAsignacion(a: AsignacionDeRol) {
  confirm.require({
    header: 'Revocar rol',
    message: `¿Quitarle a ${a.usuario.username} «${a.rol.descripcion || a.rol.nombre}» (${alcanceDeAsignacion(a)})? Deja de poder hacerlo desde ya.`,
    acceptLabel: 'Revocar',
    rejectLabel: 'Cancelar',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await api.revocar(a.id)
        notify.success('Rol revocado.')
        recargarRol()
      } catch (e) {
        notify.error(e instanceof Error ? e.message : 'No se pudo revocar.')
      }
    },
  })
}

async function buscar() {
  if (!capacidad.value) return
  buscando.value = true
  try {
    resultado.value = await api.fetchQuienPuede(capacidad.value, empresa.value, estacion.value)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo consultar.')
  } finally {
    buscando.value = false
  }
}
</script>

<style scoped>
.filtros {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(0, 1fr);
  @container (min-width: 48rem) {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
  }
}
.filtros-rol {
  @container (min-width: 48rem) {
    grid-template-columns: minmax(0, 1fr) auto;
  }
}
</style>
