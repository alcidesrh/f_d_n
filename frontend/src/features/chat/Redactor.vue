<!--
  Redactor del chat: texto (Enter envía, Mayús+Enter salta de línea), fotos y
  documentos (clip, pegar o arrastrar; las fotos se reducen antes de subir),
  registros elegidos de sus listados con el selector (llegan como tarjetas),
  lo que la app tiene en pantalla bajo el chat (un clic lo adjunta) y, si se
  está respondiendo, la cita del mensaje original.
-->
<template>
  <form class="redactor" @submit.prevent="enviar" @paste="alPegar">
    <div v-if="respondiendo" class="cita">
      <icon name="reply" size="1.1rem" color="text-current" />
      <span class="min-w-0 flex-1">
        <span class="cita__autor">Respondiendo a {{ respondiendo.autor?.nombre ?? 'Sistema' }}</span>
        <span class="cita__texto">{{ extractoDe(respondiendo) }}</span>
      </span>
      <button type="button" class="tap-target" aria-label="Cancelar respuesta" @click="emit('cancelarRespuesta')"><icon name="close" size="1rem" color="text-current" /></button>
    </div>

    <div v-if="subidas.length" class="subidas">
      <div v-for="s in subidas" :key="s.clave" class="subida" :class="{ 'subida--error': s.error }" :title="s.error ?? s.nombre">
        <img v-if="s.vista" :src="s.vista" alt="" class="subida__vista" />
        <span v-else class="subida__icono"><icon name="description-outline" color="text-current" /></span>
        <span class="subida__nombre">{{ s.error ? 'Error' : s.nombre }}</span>
        <span v-if="!s.archivo && !s.error" class="subida__progreso" :style="{ width: `${Math.round(s.progreso * 100)}%` }" />
        <button type="button" class="subida__quitar" :aria-label="`Quitar ${s.nombre}`" @click="quitar(s)"><icon name="close" size=".8rem" color="text-current" /></button>
      </div>
    </div>

    <div v-if="adjuntos.length || sugeridos.length" class="redactor__adjuntos">
      <span v-for="(a, i) in adjuntos" :key="`${a.tipo}:${a.id}`" class="adjunto" :title="`${tipoTarjeta(a.tipo).nombre} ${a.id}`">
        <icon :name="tipoTarjeta(a.tipo).icono" size=".95rem" color="text-current" /><span class="adjunto__texto">{{ a.etiqueta }}</span>
        <button type="button" class="tap-target" :aria-label="`Quitar ${a.etiqueta}`" @click="adjuntos.splice(i, 1)"><icon name="close" size=".85rem" color="text-current" /></button>
      </span>
      <button v-for="r in sugeridos" :key="`s${r.tipo}:${r.id}`" type="button" class="adjunto adjunto--sugerido" :aria-label="`Adjuntar ${r.etiqueta}, en pantalla`" v-tooltip.top="'En pantalla: clic para adjuntar'" @click="adjuntar(r)">
        <icon name="frame-inspect" size=".95rem" color="text-current" /><span class="adjunto__texto">{{ r.etiqueta }}</span><icon name="add" size=".85rem" color="text-current" />
      </button>
    </div>

    <div class="redactor__fila">
      <button type="button" class="redactor__boton tap-target" aria-label="Adjuntar foto o archivo" v-tooltip.top="'Foto o archivo'" @click="selector?.click()">
        <icon name="attach-file" size="1.35rem" color="text-current" />
      </button>
      <button type="button" class="redactor__boton tap-target" aria-label="Adjuntar registros" v-tooltip.top="'Boletos, salidas, buses…'" @click="eligiendo = true">
        <icon name="add-circle-outline" size="1.35rem" color="text-current" />
      </button>
      <textarea ref="campo" v-model="texto" class="redactor__texto" rows="1" placeholder="Escriba un mensaje" aria-label="Mensaje" :maxlength="4000" @keydown.enter.exact.prevent="enviar" @input="ajustar" />
      <button type="submit" class="redactor__enviar tap-target" :disabled="!puedeEnviar" :aria-label="subiendo ? 'Subiendo archivos…' : 'Enviar'">
        <icon :name="subiendo ? 'progress-activity' : 'send'" size="1.25rem" color="text-current" :class="{ 'animate-spin': subiendo }" />
      </button>
    </div>
    <input ref="selector" type="file" multiple :accept="ACEPTA" class="hidden" @change="alElegir" />

    <SelectorRegistros v-model:visible="eligiendo" :inicial="adjuntos" @aceptar="alElegirRegistros" />
  </form>
</template>

<script setup lang="ts">
import { subirArchivo, type Envio } from '@/core/chat/api'
import type { Archivo, Mensaje, Referencia, Registro } from '@/core/chat/types'
import { notify } from '@/core/notify'
import { enPantalla } from '@/shared/chat/integracion'
import { ACEPTA, prepararArchivo } from './imagen'
import SelectorRegistros from './SelectorRegistros.vue'
import { tipoTarjeta } from './tarjetas/catalogo'

const emit = defineEmits<{ enviar: [envio: Envio]; cancelarRespuesta: [] }>()
const props = defineProps<{ enviando?: boolean; respondiendo?: Mensaje | null }>()

const MAX_ADJUNTOS = 20
/** Sugerencias "en pantalla" a la vista, como mucho. */
const MAX_SUGERIDOS = 4
const MAX_ARCHIVOS = 10

interface Subida {
  clave: number
  nombre: string
  /** Miniatura local (object URL) de las fotos. */
  vista: string | null
  progreso: number
  archivo: Archivo | null
  error: string | null
}

const texto = ref('')
const adjuntos = ref<Registro[]>([])
const subidas = ref<Subida[]>([])
const campo = ref<HTMLTextAreaElement | null>(null)
const selector = ref<HTMLInputElement | null>(null)
/** El selector de registros está abierto. */
const eligiendo = ref(false)
let claves = 0

// Sin desplazar la página: el foco no debe mover el shell (alto fijo).
const enfocar = () => campo.value?.focus({ preventScroll: true })

/** Lo que la app muestra bajo el chat y aún no está adjunto. */
const sugeridos = computed(() => enPantalla.value.filter((r) => !adjuntos.value.some((a) => a.tipo === r.tipo && a.id === r.id)).slice(0, MAX_SUGERIDOS))
const subiendo = computed(() => subidas.value.some((s) => !s.archivo && !s.error))
const listos = computed(() => subidas.value.filter((s) => s.archivo).map((s) => s.archivo!.id))
const puedeEnviar = computed(() => !props.enviando && !subiendo.value && (texto.value.trim() !== '' || adjuntos.value.length > 0 || listos.value.length > 0))

const extractoDe = (m: Mensaje) => m.texto || (m.archivos.length ? (m.archivos.every((a) => a.imagen) ? 'Foto' : m.archivos[0]!.nombre) : m.adjuntos.length ? `${m.adjuntos.length} registro(s)` : '')

function alElegirRegistros(registros: Registro[]) {
  adjuntos.value = registros
  enfocar()
}

/** Sube cada archivo en cuanto se elige; el envío espera a que terminen. */
async function agregarArchivos(archivos: File[]) {
  const libres = MAX_ARCHIVOS - subidas.value.length
  if (archivos.length > libres) notify.warning(`Hasta ${MAX_ARCHIVOS} archivos por mensaje.`)
  for (const f of archivos.slice(0, Math.max(0, libres))) {
    const s = reactive<Subida>({ clave: ++claves, nombre: f.name, vista: f.type.startsWith('image/') ? URL.createObjectURL(f) : null, progreso: 0, archivo: null, error: null })
    subidas.value.push(s)
    void (async () => {
      try {
        const { blob, nombre } = await prepararArchivo(f)
        s.archivo = await subirArchivo(blob, nombre, (p) => (s.progreso = p))
      } catch (e) {
        s.error = e instanceof Error ? e.message : String(e)
        notify.error(`${f.name}: ${s.error}`)
      }
    })()
  }
  enfocar()
}

function alElegir(e: Event) {
  const input = e.target as HTMLInputElement
  void agregarArchivos([...(input.files ?? [])])
  input.value = ''
}

/** Pegar una captura o foto copiada la adjunta. */
function alPegar(e: ClipboardEvent) {
  const archivos = [...(e.clipboardData?.files ?? [])]
  if (!archivos.length) return
  e.preventDefault()
  void agregarArchivos(archivos)
}

function quitar(s: Subida) {
  if (s.vista) URL.revokeObjectURL(s.vista)
  subidas.value = subidas.value.filter((x) => x.clave !== s.clave)
}

function enviar() {
  if (!puedeEnviar.value) return
  emit('enviar', { texto: texto.value, adjuntos: adjuntos.value.map(({ tipo, id }) => ({ tipo, id })), archivos: listos.value, respuestaA: props.respondiendo?.id ?? null })
}

/** Lo llama la conversación cuando el mensaje quedó guardado. */
function limpiar() {
  texto.value = ''
  adjuntos.value = []
  subidas.value.forEach((s) => s.vista && URL.revokeObjectURL(s.vista))
  subidas.value = []
  void nextTick(ajustar)
  enfocar()
}

function ajustar() {
  const el = campo.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = `${Math.min(el.scrollHeight, 160)}px`
}

/** Prepara un adjunto desde fuera (p. ej. `?adjuntar=Salida:832`) o de lo que hay en pantalla. */
function adjuntar(r: Referencia | Registro) {
  if (adjuntos.value.some((a) => a.tipo === r.tipo && a.id === r.id)) return
  if (adjuntos.value.length >= MAX_ADJUNTOS) return notify.warning(`Se pueden adjuntar hasta ${MAX_ADJUNTOS} registros por mensaje.`)
  adjuntos.value.push({ etiqueta: `${tipoTarjeta(r.tipo).nombre} ${r.id}`, ...r })
  enfocar()
}

onBeforeUnmount(() => subidas.value.forEach((s) => s.vista && URL.revokeObjectURL(s.vista)))

defineExpose({ limpiar, adjuntar, enfocar, agregarArchivos })
</script>

<style scoped>
.redactor {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom));
  border-top: 1px solid var(--p-surface-200);
  background: var(--p-content-background);
}
.cita {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.45rem 0.4rem 0.45rem 0.75rem;
  border-left: 3px solid var(--p-primary-color);
  border-radius: 0.5rem;
  color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 7%, transparent);
  button {
    display: grid;
    place-items: center;
    min-width: 1.75rem;
    min-height: 1.75rem;
    border-radius: 50%;
    color: var(--p-surface-500);
  }
}
.cita__autor,
.cita__texto {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cita__autor {
  font-size: 0.75rem;
  font-weight: 600;
}
.cita__texto {
  font-size: 0.82rem;
  color: var(--p-surface-600);
}
.subidas {
  display: flex;
  gap: 0.5rem;
  overflow-x: auto;
  padding-top: 0.35rem;
}
.subida {
  position: relative;
  flex: none;
  display: flex;
  flex-direction: column;
  width: 4.5rem;
  border-radius: 0.6rem;
  background: var(--p-surface-100);
}
.subida--error {
  outline: 2px solid var(--c-danger);
}
.subida__vista,
.subida__icono {
  width: 4.5rem;
  height: 3.5rem;
  border-radius: 0.6rem 0.6rem 0 0;
  object-fit: cover;
}
.subida__icono {
  display: grid;
  place-items: center;
  color: var(--p-primary-color);
}
.subida__nombre {
  padding: 0.15rem 0.3rem 0.25rem;
  overflow: hidden;
  font-size: 0.65rem;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--p-surface-600);
}
.subida__progreso {
  position: absolute;
  left: 0;
  bottom: 0;
  height: 3px;
  border-radius: 999px;
  background: var(--p-primary-color);
  transition: width 0.2s linear;
}
.subida__quitar {
  position: absolute;
  top: -0.4rem;
  right: -0.4rem;
  display: grid;
  place-items: center;
  width: 1.3rem;
  height: 1.3rem;
  border-radius: 50%;
  color: white;
  background: var(--p-surface-700);
  box-shadow: 0 0 0 2px var(--p-content-background);
}
.redactor__adjuntos {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.adjunto {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  max-width: 100%;
  padding: 0.15rem 0.25rem 0.15rem 0.55rem;
  border-radius: 999px;
  font-size: 0.78rem;
  color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
  button {
    display: grid;
    place-items: center;
    min-width: 1.25rem;
    min-height: 1.25rem;
    border-radius: 50%;
  }
}
.adjunto__texto {
  min-width: 0;
  max-width: 14rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.adjunto--sugerido {
  padding-right: 0.55rem;
  color: var(--p-surface-600);
  background: transparent;
  box-shadow: inset 0 0 0 1px var(--p-surface-300);
  border: 0;
  cursor: pointer;
  &:hover {
    color: var(--p-primary-color);
    box-shadow: inset 0 0 0 1px var(--p-primary-color);
  }
}
.redactor__fila {
  display: flex;
  align-items: flex-end;
  gap: 0.25rem;
}
.redactor__boton {
  width: 2.5rem;
  height: 2.75rem;
  display: grid;
  place-items: center;
  flex: none;
  border-radius: 50%;
  color: var(--p-surface-500);
  &:hover {
    color: var(--p-primary-color);
  }
}
.redactor__texto {
  flex: 1;
  min-width: 0;
  min-height: 2.75rem;
  max-height: 10rem;
  margin-left: 0.25rem;
  padding: 0.65rem 1rem;
  border: 0;
  border-radius: 1.4rem;
  outline: 0;
  resize: none;
  font: inherit;
  font-size: 0.925rem;
  line-height: 1.45;
  color: var(--p-surface-800);
  background: var(--p-surface-100);
  transition: box-shadow var(--transition);
  &:focus {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--p-primary-color) 45%, transparent);
  }
}
.redactor__enviar {
  width: 2.75rem;
  height: 2.75rem;
  margin-left: 0.25rem;
  display: grid;
  place-items: center;
  flex: none;
  border-radius: 50%;
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
  transition: transform 0.15s var(--ease), opacity var(--transition);
  &:not(:disabled):hover {
    transform: scale(1.06);
  }
  &:disabled {
    opacity: 0.35;
    cursor: default;
  }
}
</style>
