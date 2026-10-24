<!--
  Otorgar o modificar una asignación (ADR-039): un rol sobre un alcance
  —empresas × estaciones, «todas» incluye las que se den de alta después— y,
  si es temporal, una vigencia. A un usuario de agencia el alcance no se
  elige: es su agencia. El backend rechaza lo que quien otorga no tiene ahí
  (`App\Acceso\Delegacion`); el mensaje se muestra tal cual.
-->
<template>
  <Dialog :visible="visible" modal :header="asignacion ? 'Modificar asignación' : 'Otorgar rol'" class="w-[min(36rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="iniciar">
    <form class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4" @submit.prevent="guardar">
      <label class="campo">
        <span>Rol</span>
        <Select v-model="rol" :options="opcionesRoles" option-label="etiqueta" option-value="id" placeholder="Elija el rol" filter :filter-fields="['etiqueta', 'nombre']" fluid>
          <template #option="{ option }">
            <div class="min-w-0">
              <div class="truncate text-sm">{{ option.etiqueta }}</div>
              <div class="truncate text-xs text-muted-color">{{ option.nombre }} · {{ option.resumen }}</div>
            </div>
          </template>
        </Select>
      </label>

      <Message v-if="agencia" severity="secondary" :closable="false">
        Es usuario de la agencia <strong>{{ agencia }}</strong>: el rol rige en su agencia y en las empresas que esta vende.
      </Message>
      <template v-else>
        <fieldset class="alcance min-w-0">
          <legend>Empresas</legend>
          <div class="flex min-w-0 items-center gap-2">
            <ToggleSwitch v-model="todasEmpresas" input-id="todas-empresas" />
            <label for="todas-empresas" class="text-sm">Todas (también las que se agreguen)</label>
          </div>
          <MultiSelect v-if="!todasEmpresas" v-model="empresas" :options="catalogo.empresas" option-label="nombre" option-value="id" placeholder="Elija las empresas" display="chip" filter :max-selected-labels="3" fluid />
        </fieldset>
        <fieldset class="alcance min-w-0">
          <legend>Estaciones</legend>
          <div class="flex min-w-0 items-center gap-2">
            <ToggleSwitch v-model="todasEstaciones" input-id="todas-estaciones" />
            <label for="todas-estaciones" class="text-sm">Todas (oficina)</label>
          </div>
          <MultiSelect v-if="!todasEstaciones" v-model="estaciones" :options="estacionesActivas" option-label="nombre" option-value="id" placeholder="Elija las estaciones" display="chip" filter :max-selected-labels="6" fluid />
          <small v-if="rolElegido && !usaEstacion" class="text-muted-color">Las capacidades de este rol no dependen de la estación: elegirlas solo acota sus listados.</small>
          <small v-else-if="rolElegido" class="text-muted-color">Vende, chequea o recibe solo desde esas estaciones (su estación de trabajo).</small>
        </fieldset>
      </template>

      <div class="grid gap-3 @container sm:grid-cols-2">
        <label class="campo">
          <span>Desde (opcional)</span>
          <InputText v-model="desde" type="date" fluid />
        </label>
        <label class="campo">
          <span>Hasta (opcional, inclusive)</span>
          <InputText v-model="hasta" type="date" fluid />
        </label>
      </div>
      <label class="campo">
        <span>Motivo</span>
        <Textarea v-model="motivo" rows="2" auto-resize placeholder="Por qué se otorga (suplencia, cambio de puesto…)" fluid />
      </label>

      <div class="flex justify-end gap-2">
        <Button type="button" label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="asignacion ? 'Guardar' : 'Otorgar'" :loading="guardando" :disabled="!rol" />
      </div>
    </form>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import * as api from '@/core/acceso/api'
import type { AccesosUsuario, Asignacion, CatalogoAcceso, Rol } from '@/core/acceso/tipos'
import { notify } from '@/core/notify'

const props = defineProps<{
  visible: boolean
  usuarioId: number
  agencia: string | null
  roles: Rol[]
  catalogo: CatalogoAcceso
  asignacion: Asignacion | null
}>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; guardado: [accesos: AccesosUsuario] }>()

const rol = ref<number | null>(null)
const todasEmpresas = ref(false)
const empresas = ref<number[]>([])
const todasEstaciones = ref(false)
const estaciones = ref<number[]>([])
const desde = ref('')
const hasta = ref('')
const motivo = ref('')
const guardando = ref(false)

const porCodigo = computed(() => new Map(props.catalogo.capacidades.map((c) => [c.codigo, c])))
const opcionesRoles = computed(() =>
  props.roles
    .filter((r) => !r.base)
    .map((r) => ({ id: r.id, nombre: r.nombre, etiqueta: r.descripcion || r.nombre, resumen: r.sistema ? 'todas las capacidades' : `${r.capacidades.length} capacidades` })),
)
const rolElegido = computed(() => props.roles.find((r) => r.id === rol.value) ?? null)
const usaEstacion = computed(() => rolElegido.value?.sistema || (rolElegido.value?.capacidades ?? []).some((c) => porCodigo.value.get(c)?.dimensiones.includes('estacion')))
const estacionesActivas = computed(() => props.catalogo.estaciones.filter((e) => e.activo !== false || estaciones.value.includes(e.id)))

function iniciar() {
  const a = props.asignacion
  rol.value = a?.rol.id ?? null
  todasEmpresas.value = a ? a.empresas === null : false
  empresas.value = a?.empresas?.map((e) => e.id) ?? []
  todasEstaciones.value = a ? a.estaciones === null : false
  estaciones.value = a?.estaciones?.map((e) => e.id) ?? []
  desde.value = a?.desde ?? ''
  hasta.value = a?.hasta ?? ''
  motivo.value = a?.motivo ?? ''
}

async function guardar() {
  if (!rol.value) return
  if (!props.agencia && ((!todasEmpresas.value && !empresas.value.length) || (!todasEstaciones.value && !estaciones.value.length))) {
    notify.warning('Elija al menos una empresa y una estación, o «todas».')
    return
  }
  const payload = {
    rol: rol.value,
    empresas: props.agencia || todasEmpresas.value ? null : empresas.value,
    estaciones: props.agencia || todasEstaciones.value ? null : estaciones.value,
    desde: desde.value || null,
    hasta: hasta.value || null,
    motivo: motivo.value.trim() || null,
  }
  guardando.value = true
  try {
    const r = props.asignacion ? await api.modificar(props.asignacion.id, payload) : await api.otorgar(props.usuarioId, payload)
    notify.success(props.asignacion ? 'Asignación modificada.' : 'Rol otorgado.')
    emit('guardado', r)
    emit('update:visible', false)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo guardar la asignación.')
  } finally {
    guardando.value = false
  }
}
</script>

<style scoped>
.campo {
  display: grid;
  gap: 0.3rem;
  font-size: 0.8rem;
  color: var(--p-surface-600);
}
.alcance {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0.6rem 0.75rem 0.75rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
  legend {
    padding: 0 0.3rem;
    font-size: 0.8rem;
    font-weight: 600;
  }
}
</style>
