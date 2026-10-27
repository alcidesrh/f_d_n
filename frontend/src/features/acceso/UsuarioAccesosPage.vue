<!--
  Accesos de un usuario (`/configuracion/usuarios/:id/accesos`, ADR-039):
  sus asignaciones —rol, alcance, vigencia, motivo, quién la otorgó— y lo
  que en efecto puede hacer, con el rol que se lo da. Desde aquí se otorga,
  modifica o revoca (con `iam.asignar`, sin escalar privilegios) y se activa
  o desactiva al usuario (si quien mira lo domina). Cada cambio queda en su
  bitácora.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <div class="flex min-w-0 items-center gap-2">
          <Button text rounded aria-label="Volver" class="tap-target" @click="volver"><icon name="arrow-back" /></Button>
          <div class="min-w-0">
            <h1 class="truncate text-lg font-semibold">{{ datos?.usuario.nombre || datos?.usuario.username || "Accesos" }}</h1>
            <p v-if="datos" class="truncate text-xs text-muted-color">
              @{{ datos.usuario.username }}
              <template v-if="datos.usuario.agencia"> · Agencia {{ datos.usuario.agencia.nombre }}</template>
              <template v-else-if="datos.usuario.estacion"> · Estación de trabajo: {{ datos.usuario.estacion.nombre }}</template>
            </p>
          </div>
        </div>
      </template>
      <template #end>
        <div v-if="datos" class="flex flex-wrap items-center gap-2">
          <Tag :severity="datos.usuario.activo ? 'success' : 'danger'" :value="datos.usuario.activo ? 'Activo' : 'Desactivado'" />
          <Button v-if="datos.puede.activar" :label="datos.usuario.activo ? 'Desactivar' : 'Activar'" :severity="datos.usuario.activo ? 'danger' : 'success'" size="small" outlined @click="cambiarActivo" />
          <Button label="Bitácora" size="small" text severity="secondary" @click="bitacora = true">
            <template #icon><icon name="history" class="mr-1" /></template>
          </Button>
        </div>
      </template>
    </Toolbar>

    <Message v-if="error" severity="warn" :closable="false">{{ error }}</Message>
    <div v-if="cargando && !datos" class="flex justify-center py-10"><ProgressSpinner style="width: 2rem; height: 2rem" /></div>

    <template v-if="datos && catalogo">
      <section class="card flex flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-semibold">Asignaciones</h2>
          <div class="flex flex-wrap items-center gap-2">
            <template v-if="revocables.length">
              <Button v-if="modoSeleccion" label="Todas" text size="small" :disabled="!revocables.length" @click="marcarTodas" />
              <Button v-if="modoSeleccion && seleccion.length" :label="`Revocar (${seleccion.length})`" severity="danger" size="small" outlined @click="revocarSeleccion" />
              <Button :label="modoSeleccion ? 'Listo' : 'Seleccionar'" :severity="modoSeleccion ? 'secondary' : undefined" :text="!modoSeleccion" size="small" @click="alternarSeleccion" />
            </template>
            <Button v-if="datos.puede.asignar" label="Otorgar rol" size="small" @click="abrir(null)">
              <template #icon><icon name="add" class="mr-1" /></template>
            </Button>
          </div>
        </div>
        <p v-if="!datos.asignaciones.length" class="text-sm text-muted-color">Sin asignaciones: no puede hacer nada más que entrar, usar el chat y ver lo que es de todos.</p>
        <ul class="asignaciones">
          <li v-for="a in datos.asignaciones" :key="a.id" class="asignacion" :class="{ 'asignacion--inactiva': !a.vigente }">
            <Checkbox v-if="modoSeleccion && a.puedeEditar" v-model="seleccion" :value="a.id" :input-id="`asignacion-${a.id}`" :aria-label="`Seleccionar ${a.rol.descripcion || a.rol.nombre}`" class="mt-1" />
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ a.rol.descripcion || a.rol.nombre }}</span>
                <Tag v-if="a.rol.sistema" severity="contrast" value="Sistema" />
                <Tag v-if="!a.vigente" severity="secondary" value="No vigente" />
              </div>
              <p class="text-sm">{{ alcance(a) }}</p>
              <p class="text-xs text-muted-color">
                <template v-if="a.desde || a.hasta">Vigencia: {{ a.desde ?? "…" }} a {{ a.hasta ?? "…" }} · </template>
                {{ a.otorgadaPor ? `Otorgó ${a.otorgadaPor}` : "Migración" }} · {{ fecha(a.modificada) }}
                <template v-if="a.motivo"> · {{ a.motivo }}</template>
              </p>
            </div>
            <div v-if="a.puedeEditar" class="flex gap-1">
              <Button text rounded size="small" class="tap-target" aria-label="Modificar" v-tooltip.top="'Modificar'" @click="abrir(a)"><icon name="edit-outline" /></Button>
              <Button text rounded size="small" severity="danger" class="tap-target" aria-label="Revocar" v-tooltip.top="'Revocar'" @click="revocar(a)"><icon name="remove-moderator-outline" /></Button>
            </div>
          </li>
        </ul>
      </section>

      <section class="card flex flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-semibold">Lo que puede hacer</h2>
          <span class="text-xs text-muted-color">{{ datos.efectivo.length }} capacidades vigentes</span>
        </div>
        <p v-if="!datos.efectivo.length" class="text-sm text-muted-color">Nada: ninguna asignación vigente le concede algo (o su alcance está vacío).</p>
        <div v-for="(caps, area) in porArea" :key="area" class="efectivo">
          <h3 class="text-xs font-semibold tracking-wide text-muted-color uppercase">{{ area }}</h3>
          <ul>
            <li v-for="c in caps" :key="c.codigo" class="efectivo-fila">
              <span class="min-w-0 flex-1 text-sm">{{ c.nombre }}</span>
              <span class="min-w-0 text-xs text-muted-color">{{ [...new Set(c.ambitos.map((am) => describir(am)))].join(" | ") }}</span>
              <span class="w-full text-xs text-muted-color @2xl:w-auto">por {{ c.roles.join(", ") }}</span>
            </li>
          </ul>
        </div>
      </section>

      <AsignacionDialog v-model:visible="dialogo" :usuario-id="id" :agencia="datos.usuario.agencia?.nombre ?? null" :roles="roles" :catalogo="catalogo" :asignacion="editando" @guardado="datos = $event" />
      <BitacoraDialog v-model:visible="bitacora" tipo="usuario" :id="id" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import * as api from '@/core/acceso/api'
import { describirAmbito } from '@/core/acceso/reglas'
import type { AccesosUsuario, Ambito, Asignacion, CapacidadEfectiva, CatalogoAcceso, Rol } from '@/core/acceso/tipos'
import { notify } from '@/core/notify'
import BitacoraDialog from '@/shared/bitacora/BitacoraDialog.vue'
import AsignacionDialog from './AsignacionDialog.vue'

defineOptions({ name: 'UsuarioAccesosPage' })

const props = defineProps<{ id: number }>()
const router = useRouter()
const confirm = useConfirm()

const datos = ref<AccesosUsuario | null>(null)
const catalogo = ref<CatalogoAcceso | null>(null)
const roles = ref<Rol[]>([])
const cargando = ref(false)
const error = ref<string | null>(null)
const dialogo = ref(false)
const editando = ref<Asignacion | null>(null)
const bitacora = ref(false)
const modoSeleccion = ref(false)
const seleccion = ref<number[]>([])

const revocables = computed(() => (datos.value?.asignaciones ?? []).filter((a) => a.puedeEditar))

const nombres = computed(() => ({
  empresas: new Map((catalogo.value?.empresas ?? []).map((e) => [e.id, e.nombre])),
  estaciones: new Map((catalogo.value?.estaciones ?? []).map((e) => [e.id, e.nombre])),
  agencia: datos.value?.usuario.agencia?.nombre ?? null,
}))

const porArea = computed(() => {
  const grupos: Record<string, CapacidadEfectiva[]> = {}
  for (const c of datos.value?.efectivo ?? []) (grupos[c.area] ??= []).push(c)
  return grupos
})

async function cargar() {
  cargando.value = true
  error.value = null
  modoSeleccion.value = false
  seleccion.value = []
  try {
    ;[datos.value, catalogo.value, roles.value] = await Promise.all([api.fetchAccesos(props.id), catalogo.value ?? api.fetchCatalogo(), roles.value.length ? roles.value : api.fetchRoles()])
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No se pudieron cargar sus accesos.'
  } finally {
    cargando.value = false
  }
}
watch(() => props.id, cargar, { immediate: true })

const describir = (a: Ambito) => describirAmbito(a, nombres.value)

function alcance(a: Asignacion): string {
  if (a.agencia) return `Agencia ${a.agencia} y las empresas que vende`
  return describirAmbito({ empresas: a.empresas?.map((e) => e.id) ?? null, estaciones: a.estaciones?.map((e) => e.id) ?? null }, nombres.value)
}

const fecha = (iso: string) => new Date(iso).toLocaleDateString('es-GT', { day: '2-digit', month: 'short', year: 'numeric' })

function abrir(a: Asignacion | null) {
  editando.value = a
  dialogo.value = true
}

function revocar(a: Asignacion) {
  confirm.require({
    header: 'Revocar rol',
    message: `¿Quitarle «${a.rol.descripcion || a.rol.nombre}» (${alcance(a)})? Deja de poder hacerlo desde ya.`,
    acceptLabel: 'Revocar',
    rejectLabel: 'Cancelar',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        datos.value = await api.revocar(a.id)
        notify.success('Rol revocado.')
      } catch (e) {
        notify.error(e instanceof Error ? e.message : 'No se pudo revocar.')
      }
    },
  })
}

function alternarSeleccion() {
  modoSeleccion.value = !modoSeleccion.value
  seleccion.value = []
}

function marcarTodas() {
  seleccion.value = seleccion.value.length === revocables.value.length ? [] : revocables.value.map((a) => a.id)
}

/** Revoca una a una (cada una con su bitácora y su permiso); sigue con las demás si una falla. */
function revocarSeleccion() {
  const ids = [...seleccion.value]
  confirm.require({
    header: 'Revocar asignaciones',
    message: `¿Quitar las ${ids.length} asignaciones seleccionadas? Deja de poder hacer lo que concedían desde ya.`,
    acceptLabel: `Revocar ${ids.length}`,
    rejectLabel: 'Cancelar',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      let revocadas = 0
      let fallidas = 0
      let motivo = ''
      for (const id of ids) {
        try {
          datos.value = await api.revocar(id)
          revocadas++
        } catch (e) {
          fallidas++
          motivo = e instanceof Error ? e.message : 'No se pudo revocar.'
        }
      }
      seleccion.value = []
      modoSeleccion.value = false
      if (revocadas) notify.success(`${revocadas === 1 ? 'Asignación revocada' : `${revocadas} asignaciones revocadas`}.`)
      if (fallidas) notify.error(`${fallidas} sin revocar. ${motivo}`)
    },
  })
}

function cambiarActivo() {
  const u = datos.value?.usuario
  if (!u) return
  confirm.require({
    header: u.activo ? 'Desactivar usuario' : 'Activar usuario',
    message: u.activo ? `¿Desactivar a ${u.username}? No podrá entrar y sus sesiones abiertas se cierran. Sus asignaciones se conservan.` : `¿Activar a ${u.username}? Vuelve a entrar con sus asignaciones.`,
    acceptLabel: u.activo ? 'Desactivar' : 'Activar',
    rejectLabel: 'Cancelar',
    acceptProps: { severity: u.activo ? 'danger' : 'success' },
    accept: async () => {
      try {
        datos.value = await api.activar(u.id, !u.activo)
        notify.success(datos.value.usuario.activo ? 'Usuario activado.' : 'Usuario desactivado.')
      } catch (e) {
        notify.error(e instanceof Error ? e.message : 'No se pudo cambiar.')
      }
    },
  })
}

function volver() {
  if (window.history.state?.back) router.back()
  else void router.push({ name: 'entity-list', params: { entity: 'usuario' } })
}
</script>

<style scoped>
.asignaciones {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}
.asignacion {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--p-surface-200);
  border-left: 3px solid var(--p-primary-400);
  border-radius: 0.5rem;
}
.asignacion--inactiva {
  border-left-color: var(--p-surface-300);
  opacity: 0.75;
}
.efectivo {
  display: grid;
  gap: 0.25rem;
  ul {
    margin: 0;
    padding: 0;
    list-style: none;
  }
}
.efectivo-fila {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem 0.75rem;
  padding: 0.3rem 0;
  border-bottom: 1px solid var(--p-surface-100);
}
</style>
