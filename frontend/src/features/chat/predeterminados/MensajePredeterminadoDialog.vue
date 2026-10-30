<!--
  Crear o editar una instancia de mensaje predeterminado (ADR-045), en el
  orden del requisito: 1) nombre y activarla, 2) audiencia (usuarios y
  grupos del chat), 3) contenido: texto, imágenes o archivos y el
  predeterminado con su configuración (empresa, ruta, hora, días). Sin
  predeterminado, el contenido propio es todo el mensaje y sale a su hora.
  El backend vuelve a validar; su mensaje se muestra tal cual.
-->
<template>
  <Dialog :visible="visible" modal :header="mensaje ? 'Editar mensaje predeterminado' : 'Nuevo mensaje predeterminado'" class="w-[min(42rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="iniciar">
    <form class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4" @submit.prevent="guardar">
      <fieldset class="paso">
        <legend><span class="paso__n">1</span> Nombre</legend>
        <div class="flex min-w-0 flex-col gap-3 @container sm:flex-row sm:items-end">
          <label class="campo min-w-0 flex-1">
            <span>Nombre de la instancia</span>
            <InputText v-model="b.nombre" :maxlength="120" placeholder="p. ej. Salidas Guatemala – Flores" fluid autofocus />
          </label>
          <div class="flex items-center gap-2 pb-2">
            <ToggleSwitch v-model="b.activo" input-id="predeterminado-activo" />
            <label for="predeterminado-activo" class="text-sm">{{ b.activo ? 'Activa' : 'Inactiva' }}</label>
          </div>
        </div>
      </fieldset>

      <fieldset class="paso">
        <legend><span class="paso__n">2</span> Audiencia</legend>
        <label class="campo">
          <span>Usuarios</span>
          <MultiSelect v-model="b.usuarios" :options="opciones.usuarios" option-label="label" option-value="id" placeholder="Elija usuarios" display="chip" filter :filter-fields="['label', 'usuario']" :max-selected-labels="6" :virtual-scroller-options="{ itemSize: 38 }" fluid />
        </label>
        <label class="campo">
          <span>Grupos del chat (llega a todos sus miembros)</span>
          <MultiSelect v-model="b.grupos" :options="opciones.grupos" option-label="nombre" option-value="id" :placeholder="opciones.grupos.length ? 'Elija grupos' : 'No hay grupos en el chat'" :disabled="!opciones.grupos.length" display="chip" filter :max-selected-labels="6" fluid />
        </label>
        <small class="text-muted-color">Llega a «Bitácora del sistema» de cada uno, una sola vez aunque esté en varios grupos.</small>
      </fieldset>

      <fieldset class="paso">
        <legend><span class="paso__n">3</span> Contenido</legend>
        <label class="campo">
          <span>Predeterminado</span>
          <Select v-model="b.tipo" :options="opciones.tipos" option-label="nombre" option-value="codigo" fluid>
            <template #option="{ option }">
              <div class="min-w-0 py-0.5">
                <div class="text-sm">{{ option.codigo === 'libre' ? 'Ninguno: solo mi texto, imagen o archivo' : option.nombre }}</div>
                <div class="text-xs whitespace-normal text-muted-color">{{ option.descripcion }}</div>
              </div>
            </template>
            <template #value="{ value }"><span class="block truncate">{{ value === 'libre' ? 'Ninguno: solo mi texto, imagen o archivo' : tipo?.nombre }}</span></template>
          </Select>
          <small v-if="tipo" class="text-muted-color">{{ tipo.descripcion }}</small>
        </label>

        <div v-if="tipo && (tipo.exigeEmpresa || tipo.esAnomalia || tipo.exigeRuta)" class="grid min-w-0 gap-3 sm:grid-cols-2">
          <label class="campo min-w-0">
            <span>{{ tipo.exigeEmpresa ? 'Empresa' : 'Empresa (opcional)' }}</span>
            <Select v-model="b.empresa" :options="opciones.empresas" option-label="nombre" option-value="id" :placeholder="tipo.exigeEmpresa ? 'Elija la empresa' : 'Todas las empresas'" :show-clear="!tipo.exigeEmpresa" filter fluid />
          </label>
          <label v-if="tipo.exigeRuta" class="campo min-w-0">
            <span>Ruta</span>
            <Select v-model="b.ruta" :options="opciones.rutas" option-label="nombre" option-value="id" placeholder="Elija la ruta" filter fluid />
          </label>
        </div>

        <div v-if="tipo?.conHora" class="grid min-w-0 gap-3 sm:grid-cols-[10rem_minmax(0,1fr)]">
          <label class="campo">
            <span>Hora</span>
            <InputText v-model="b.hora" type="time" :placeholder="tipo.codigo === 'libre' ? '' : opciones.horaCierre" fluid />
            <small v-if="tipo.codigo !== 'libre'" class="text-muted-color">Vacía: {{ opciones.horaCierre }}</small>
          </label>
          <div v-if="tipo.conDias" class="campo min-w-0">
            <span id="predeterminado-dias">Días (ninguno: todos)</span>
            <SelectButton v-model="b.dias" :options="[...DIAS]" option-label="corto" option-value="valor" multiple aria-labelledby="predeterminado-dias" class="dias" />
          </div>
        </div>

        <label class="campo">
          <span>{{ tipo?.exigeContenido ? 'Texto' : 'Texto (opcional, va antes del predeterminado)' }}</span>
          <Textarea v-model="b.texto" rows="3" auto-resize :maxlength="2000" placeholder="Lo que quiere decir" fluid />
          <small class="text-right text-muted-color">{{ b.texto.length }} / 2000</small>
        </label>

        <div class="flex min-w-0 flex-col gap-2">
          <ul v-if="b.archivos.length" class="archivos">
            <li v-for="a in b.archivos" :key="a.id" class="archivo">
              <img v-if="a.imagen" :src="urlArchivo(a)" :alt="a.nombre" class="archivo__miniatura" />
              <icon v-else name="description-outline" class="archivo__icono" />
              <span class="min-w-0 flex-1 truncate text-sm">{{ a.nombre }}</span>
              <Button type="button" text rounded severity="secondary" size="small" class="tap-target" :aria-label="`Quitar ${a.nombre}`" @click="quitar(a.id)">
                <template #icon><icon name="close" /></template>
              </Button>
            </li>
          </ul>
          <div class="flex flex-wrap items-center gap-2">
            <Button type="button" label="Adjuntar imagen o archivo" severity="secondary" outlined size="small" :loading="subiendo" :disabled="b.archivos.length >= 10" @click="selector?.click()">
              <template #icon><icon name="attach-file" class="mr-1" /></template>
            </Button>
            <small class="text-muted-color">Hasta 10, de 10 MB cada uno.</small>
            <input ref="selector" type="file" multiple class="hidden" @change="adjuntar" />
          </div>
        </div>
      </fieldset>

      <Message v-if="intentado && pendientes.length" severity="warn" :closable="false">
        <ul class="m-0 list-none p-0">
          <li v-for="p in pendientes" :key="p">{{ p }}</li>
        </ul>
      </Message>

      <div class="flex flex-wrap justify-end gap-2">
        <Button type="button" label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="mensaje ? 'Guardar' : 'Crear'" :loading="guardando" />
      </div>
    </form>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { subirArchivo, urlArchivo } from '@/core/chat/api'
import {
  borradorDe,
  crearPredeterminado,
  DIAS,
  editarPredeterminado,
  faltantes,
  type MensajePredeterminado,
  type OpcionesPredeterminados,
} from '@/core/chat/predeterminados'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

const props = defineProps<{ visible: boolean; mensaje: MensajePredeterminado | null; opciones: OpcionesPredeterminados }>()
const emit = defineEmits<{ 'update:visible': [boolean]; guardado: [MensajePredeterminado] }>()

const b = ref(borradorDe(null))
const intentado = ref(false)
const guardando = ref(false)
const subiendo = ref(false)
const selector = ref<HTMLInputElement | null>(null)

const tipo = computed(() => props.opciones.tipos.find((t) => t.codigo === b.value.tipo))
const pendientes = computed(() => faltantes(b.value, tipo.value))

function iniciar() {
  b.value = borradorDe(props.mensaje)
  intentado.value = false
}

async function adjuntar(e: Event) {
  const input = e.target as HTMLInputElement
  const archivos = [...(input.files ?? [])].slice(0, 10 - b.value.archivos.length)
  input.value = ''
  subiendo.value = true
  try {
    for (const f of archivos) b.value.archivos.push(await subirArchivo(f, f.name))
  } catch (err) {
    notify.error(err instanceof Error ? err.message : 'No se pudo subir el archivo.')
  } finally {
    subiendo.value = false
  }
}

function quitar(id: number) {
  b.value.archivos = b.value.archivos.filter((a) => a.id !== id)
}

async function guardar() {
  intentado.value = true
  if (pendientes.value.length) return
  guardando.value = true
  try {
    const m = props.mensaje ? await editarPredeterminado(props.mensaje.id, b.value) : await crearPredeterminado(b.value)
    notify.success(props.mensaje ? 'Mensaje guardado.' : `«${m.nombre}» creado${m.activo ? ' y activo' : ''}.`)
    emit('guardado', m)
    emit('update:visible', false)
  } catch (err) {
    notify.error(err instanceof HttpError ? err.message : 'No se pudo guardar el mensaje.')
  } finally {
    guardando.value = false
  }
}
</script>

<style scoped>
.campo {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.3rem;
  font-size: 0.8rem;
  color: var(--p-surface-600);
}
.paso {
  display: grid;
  /* Sin esto, el texto largo de un Select ensancha la columna en el teléfono. */
  grid-template-columns: minmax(0, 1fr);
  gap: 0.75rem;
  min-width: 0;
  margin: 0;
  padding: 0.6rem 0.75rem 0.85rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
  legend {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0 0.3rem;
    font-size: 0.85rem;
    font-weight: 600;
  }
}
.paso__n {
  display: inline-grid;
  place-items: center;
  width: 1.3rem;
  height: 1.3rem;
  border-radius: 999px;
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  font-size: 0.75rem;
}
.dias {
  flex-wrap: wrap;
}
.archivos {
  display: grid;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  list-style: none;
}
.archivo {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
  padding: 0.3rem 0.4rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.4rem;
}
.archivo__miniatura {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.3rem;
  object-fit: cover;
}
.archivo__icono {
  width: 2.5rem;
  text-align: center;
  font-size: 1.4rem;
  color: var(--p-primary-500);
}
</style>
