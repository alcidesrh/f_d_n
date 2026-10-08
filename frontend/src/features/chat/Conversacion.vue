<!--
  Una conversación: cabecera, mensajes por día y por racha de autor (texto,
  fotos y documentos, tarjetas de lo compartido, cita del mensaje al que
  responde y "visto" en los propios) y el redactor. Abre pegada al final; si
  llega algo mientras se lee más arriba, ofrece bajar en lugar de saltar. Se
  pueden soltar archivos encima. Los avisos del sistema son de solo lectura.
-->
<template>
  <section class="conv" @dragenter.prevent="alEntrar" @dragover.prevent @dragleave="alSalir" @drop.prevent="alSoltar">
    <header class="conv__cabeza">
      <button type="button" class="conv__volver tap-target" aria-label="Volver a la bandeja" @click="emit('volver')">
        <icon name="arrow-back" color="text-current" />
      </button>
      <template v-if="canal">
        <ChatAvatar :id="canal.id" :nombre="canal.nombre" :grupo="canal.tipo === 'grupo'" :sistema="esSistema" :ambito="canal.contacto?.ambito" />
        <div class="min-w-0">
          <div class="conv__nombre">{{ canal.nombre }}</div>
          <div class="conv__sub" :title="miembros">{{ subtitulo }}</div>
        </div>
      </template>
    </header>

    <div ref="lienzo" class="conv__lienzo" @scroll.passive="alDesplazar">
      <div class="conv__pila">
        <div v-if="cargandoPrevios" class="py-3 text-center"><ProgressSpinner style="width: 1.5rem; height: 1.5rem" /></div>
        <p v-else-if="chat.completos[canalId] && lista.length" class="conv__inicio">Inicio de la conversación</p>

        <template v-if="cargado">
          <div v-if="!lista.length" class="conv__vacia">
            <icon :name="esSistema ? 'notifications-outline' : 'waving-hand-outline'" size="2rem" color="text-current" />
            <p v-if="esSistema">Aquí llegarán los avisos automáticos:<br /><span class="text-xs">acreditaciones de saldo, salidas anuladas y más.</span></p>
            <p v-else>Escriba el primer mensaje.<br /><span class="text-xs">Puede mandar fotos con el clip, adjuntar boletos o salidas con <b>+</b>, o enviarlos desde sus listados.</span></p>
          </div>
          <template v-for="b in bloques" :key="b.dia">
            <div class="conv__dia"><span>{{ b.etiqueta }}</span></div>
            <div v-for="r in b.rachas" :key="r.mensajes[0]!.id" class="racha" :class="{ 'racha--mia': r.mio }">
              <ChatAvatar v-if="!r.mio && canal?.tipo === 'grupo'" :id="r.autor?.id ?? 0" :nombre="r.autor?.nombre ?? 'Sistema'" tamano="sm" class="racha__avatar" />
              <div class="racha__mensajes">
                <span v-if="!r.mio && canal?.tipo === 'grupo'" class="racha__autor" :style="{ '--tono': tono(r.autor?.id ?? 0) * 45 }">{{ r.autor?.nombre ?? 'Sistema' }}</span>
                <div v-for="m in r.mensajes" :key="m.id" class="mensaje">
                  <div :id="`m-${m.id}`" class="burbuja" :class="{ 'burbuja--suelta': !m.texto && !m.respuesta, 'burbuja--resaltada': resaltado === m.id }">
                    <button v-if="m.respuesta" type="button" class="burbuja__cita" @click="irA(m.respuesta.id)">
                      <span class="burbuja__cita-autor">{{ m.respuesta.autor }}</span>
                      <span class="burbuja__cita-texto">{{ m.respuesta.extracto }}</span>
                    </button>
                    <ArchivosMensaje v-if="m.archivos.length" :archivos="m.archivos" @ver="verFotos" />
                    <p v-if="m.texto" class="burbuja__texto">
                      <template v-for="(s, i) in segmentos(m.texto)" :key="i">
                        <a v-if="'url' in s" :href="s.url" target="_blank" rel="noopener noreferrer">{{ s.url }}</a>
                        <template v-else>{{ s.texto }}</template>
                      </template>
                    </p>
                    <div v-if="m.adjuntos.length" class="burbuja__tarjetas">
                      <Tarjeta v-for="a in m.adjuntos" :key="`${a.tipo}:${a.id}`" :adjunto="a" />
                    </div>
                    <span class="burbuja__pie">
                      <time :datetime="m.fecha">{{ horaCorta(m.fecha) }}</time>
                      <span v-if="r.mio" v-tooltip.left="tituloVisto(m.id)" class="burbuja__visto" :class="{ 'burbuja__visto--si': visto(m.id).todos }" :aria-label="tituloVisto(m.id)">
                        <icon :name="visto(m.id).todos ? 'done-all' : 'done'" size=".95rem" color="text-current" />
                      </span>
                    </span>
                  </div>
                  <button v-if="!esSistema" type="button" class="mensaje__responder" :aria-label="`Responder: ${m.texto.slice(0, 40)}`" v-tooltip.top="'Responder'" @click="responder(m)">
                    <icon name="reply" size="1.05rem" color="text-current" />
                  </button>
                </div>
              </div>
            </div>
          </template>
        </template>
        <div v-else class="flex flex-col gap-3 p-4">
          <Skeleton v-for="n in 4" :key="n" :width="n % 2 ? '55%' : '40%'" height="2.5rem" border-radius="1rem" :class="{ 'self-end': n % 2 === 0 }" />
        </div>
      </div>
    </div>

    <Transition name="fade">
      <button v-if="pendientes > 0" type="button" class="conv__bajar" @click="bajar(true)">
        <icon name="arrow-downward" size="1rem" color="text-current" />{{ pendientes === 1 ? '1 mensaje nuevo' : `${pendientes} mensajes nuevos` }}
      </button>
    </Transition>

    <p v-if="esSistema" class="conv__solo-lectura"><icon name="lock-outline" size="1rem" color="text-current" />Avisos automáticos: no se puede responder aquí.</p>
    <Redactor v-else ref="redactor" :enviando="enviando" :respondiendo="respondiendo" @enviar="enviar" @cancelar-respuesta="respondiendo = null" />

    <Transition name="fade">
      <div v-if="arrastrando" class="conv__soltar"><icon name="upload-file-outline" size="2.5rem" color="text-current" />Suelte aquí para adjuntar</div>
    </Transition>
    <VisorImagen :fotos="visor.fotos" :inicio="visor.inicio" @cerrar="visor.fotos = []" />
  </section>
</template>

<script setup lang="ts">
import type { Envio } from '@/core/chat/api'
import { useChatStore } from '@/core/chat/store'
import { agrupar, horaCorta, segmentos, tono, ultimo, vistoPor } from '@/core/chat/modelo'
import type { Archivo, Mensaje, Referencia } from '@/core/chat/types'
import { notify } from '@/core/notify'
import ChatAvatar from '@/shared/chat/ChatAvatar.vue'
import ArchivosMensaje from './ArchivosMensaje.vue'
import Redactor from './Redactor.vue'
import Tarjeta from './tarjetas/Tarjeta.vue'
import VisorImagen from './VisorImagen.vue'

const props = defineProps<{ canalId: number; adjuntar?: Referencia | null }>()
const emit = defineEmits<{ volver: [] }>()

const AMBITOS = { administracion: 'Administración', estacion: 'Estación', agencia: 'Agencia' } as const
/** Distancia al final (px) para considerar que se está leyendo lo último. */
const CERCA = 120
/** Páginas hacia atrás que se cargan, como mucho, para llegar a un mensaje citado. */
const MAX_PAGINAS_CITA = 10

const chat = useChatStore()
const lienzo = ref<HTMLElement | null>(null)
const redactor = ref<InstanceType<typeof Redactor> | null>(null)
const cargado = ref(false)
const cargandoPrevios = ref(false)
const enviando = ref(false)
const pendientes = ref(0)
const respondiendo = ref<Mensaje | null>(null)
const resaltado = ref<number | null>(null)
const arrastrando = ref(false)
const visor = reactive<{ fotos: Archivo[]; inicio: number }>({ fotos: [], inicio: 0 })

const canal = computed(() => chat.canal(props.canalId))
const esSistema = computed(() => canal.value?.tipo === 'sistema')
const lista = computed(() => chat.mensajes[props.canalId] ?? [])
const bloques = computed(() => agrupar(lista.value, chat.yo?.id ?? null))
const miembros = computed(() => canal.value?.miembros.map((m) => (m.id === chat.yo?.id ? 'Tú' : m.nombre)).join(', ') ?? '')
const subtitulo = computed(() => {
  const c = canal.value
  if (!c) return ''
  if (c.tipo === 'sistema') return 'Avisos automáticos'
  if (c.tipo === 'grupo') return miembros.value
  return c.contacto?.lugar ?? AMBITOS[c.contacto?.ambito ?? 'administracion']
})

// "Visto": ✓ enviado, ✓✓ visto por todos los demás.
const visto = (id: number) => (canal.value ? vistoPor(canal.value, id) : { todos: false, quienes: [] })
function tituloVisto(id: number) {
  const v = visto(id)
  if (canal.value?.tipo !== 'grupo') return v.todos ? 'Visto' : 'Enviado'
  return v.quienes.length ? `Visto por ${v.quienes.map((p) => p.nombre.split(' ')[0]).join(', ')}` : 'Enviado'
}

const cercaDelFinal = () => {
  const el = lienzo.value
  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < CERCA
}

function bajar(suave = false) {
  pendientes.value = 0
  void nextTick(() => lienzo.value?.scrollTo({ top: lienzo.value.scrollHeight, behavior: suave ? 'smooth' : 'auto' }))
}

// Nuevos al final: seguir abajo si ya se estaba ahí; si no, contarlos.
watch(
  () => ultimo(lista.value)?.id,
  (nuevo, previo) => {
    if (!cargado.value || !nuevo || nuevo === previo) return
    const mio = ultimo(lista.value)?.autor?.id === chat.yo?.id
    if (mio || cercaDelFinal()) bajar(true)
    else pendientes.value += 1
  },
)

async function alDesplazar() {
  const el = lienzo.value
  if (!el) return
  if (cercaDelFinal()) pendientes.value = 0
  if (el.scrollTop > 80 || cargandoPrevios.value || !cargado.value || chat.completos[props.canalId]) return
  await cargarPrevios()
}

/** Trae la página anterior sin mover lo que se está viendo. */
async function cargarPrevios() {
  const el = lienzo.value
  if (!el) return
  cargandoPrevios.value = true
  const alto = el.scrollHeight
  try {
    await chat.cargarAnteriores(props.canalId)
    await nextTick()
    el.scrollTop += el.scrollHeight - alto
  } finally {
    cargandoPrevios.value = false
  }
}

/** Lleva al mensaje citado (cargando hacia atrás si hace falta) y lo resalta. */
async function irA(id: number) {
  for (let i = 0; i < MAX_PAGINAS_CITA && !lista.value.some((m) => m.id === id) && !chat.completos[props.canalId]; i++) await cargarPrevios()
  await nextTick()
  const el = document.getElementById(`m-${id}`)
  const contenedor = lienzo.value
  if (!el || !contenedor) return notify.info('El mensaje citado ya no está disponible.')
  const destino = el.getBoundingClientRect().top - contenedor.getBoundingClientRect().top + contenedor.scrollTop - contenedor.clientHeight / 3
  contenedor.scrollTo({ top: destino, behavior: 'smooth' })
  resaltado.value = id
  setTimeout(() => resaltado.value === id && (resaltado.value = null), 1600)
}

function responder(m: Mensaje) {
  respondiendo.value = m
  redactor.value?.enfocar()
}

function verFotos(fotos: Archivo[], inicio: number) {
  visor.inicio = inicio
  visor.fotos = fotos
}

async function enviar(envio: Envio) {
  enviando.value = true
  try {
    await chat.enviar(props.canalId, envio)
    redactor.value?.limpiar()
    respondiendo.value = null
    bajar(true)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    enviando.value = false
  }
}

// Soltar archivos sobre la conversación -----------------------------------
const llevaArchivos = (e: DragEvent) => !esSistema.value && [...(e.dataTransfer?.types ?? [])].includes('Files')
function alEntrar(e: DragEvent) {
  if (llevaArchivos(e)) arrastrando.value = true
}
function alSalir(e: DragEvent) {
  const dentro = e.relatedTarget instanceof Node && (e.currentTarget as HTMLElement).contains(e.relatedTarget)
  if (!dentro) arrastrando.value = false
}
function alSoltar(e: DragEvent) {
  arrastrando.value = false
  if (!llevaArchivos(e)) return
  void redactor.value?.agregarArchivos([...(e.dataTransfer?.files ?? [])])
}

onMounted(async () => {
  try {
    await chat.abrir(props.canalId)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
    emit('volver')
    return
  }
  cargado.value = true
  bajar()
  if (props.adjuntar) redactor.value?.adjuntar(props.adjuntar)
  redactor.value?.enfocar()
})
onUnmounted(() => chat.cerrar(props.canalId))
</script>

<style scoped>
.conv {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  background: var(--p-surface-50);
}
.conv__cabeza {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 4rem;
  padding: 0.6rem 1rem;
  border-bottom: 1px solid var(--p-surface-200);
  background: var(--p-content-background);
}
.conv__volver {
  display: grid;
  place-items: center;
  margin-left: -0.5rem;
  border-radius: 50%;
  color: var(--p-surface-600);
}
@container main (min-width: 48rem) {
  .conv__volver {
    display: none;
  }
}
.conv__nombre {
  font-weight: 600;
  color: var(--p-surface-800);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.conv__sub {
  font-size: 0.78rem;
  color: var(--p-surface-500);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.conv__lienzo {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}
.conv__pila {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  max-width: 52rem;
  min-height: 100%;
  margin: 0 auto;
  padding: 1rem 0.75rem 1.25rem;
  justify-content: flex-end;
}
.conv__inicio,
.conv__dia {
  margin: 0.75rem 0 0.25rem;
  text-align: center;
  font-size: 0.72rem;
  color: var(--p-surface-500);
}
.conv__dia span {
  padding: 0.2rem 0.65rem;
  border-radius: 999px;
  font-weight: 500;
  background: var(--p-content-background);
  box-shadow: 0 0 0 1px var(--p-surface-200);
}
.conv__vacia {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  margin: auto;
  padding: 2rem 1rem;
  text-align: center;
  font-size: 0.875rem;
  color: var(--p-surface-500);
  p {
    margin: 0;
    line-height: 1.5;
  }
}
.conv__solo-lectura {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  margin: 0;
  padding: 0.9rem 1rem calc(0.9rem + env(safe-area-inset-bottom));
  border-top: 1px solid var(--p-surface-200);
  font-size: 0.8rem;
  color: var(--p-surface-500);
  background: var(--p-content-background);
}
.conv__soltar {
  position: absolute;
  inset: 0.5rem;
  z-index: 5;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border: 2px dashed var(--p-primary-color);
  border-radius: 1rem;
  font-weight: 500;
  color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-content-background) 85%, transparent);
  backdrop-filter: blur(2px);
  pointer-events: none;
}
.racha {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  margin-top: 0.4rem;
}
.racha--mia {
  justify-content: flex-end;
}
.racha__avatar {
  margin-bottom: 0.2rem;
}
.racha__mensajes {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.2rem;
  max-width: min(85%, 30rem);
  min-width: 0;
}
.racha--mia .racha__mensajes {
  align-items: flex-end;
}
.racha__autor {
  margin: 0 0 0.1rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: hsl(calc(var(--tono) * 1deg) 50% 40%);
}
/* Mensaje = burbuja + botón responder (al lado, aparece al pasar o enfocar). */
.mensaje {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  max-width: 100%;
  min-width: 0;
}
.racha--mia .mensaje {
  flex-direction: row-reverse;
}
.mensaje__responder {
  display: grid;
  place-items: center;
  flex: none;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  color: var(--p-surface-500);
  opacity: 0;
  transition: opacity 0.15s var(--ease), background 0.15s var(--ease);
  &:hover {
    color: var(--p-primary-color);
    background: var(--p-surface-100);
  }
  &:focus-visible {
    opacity: 1;
  }
}
.mensaje:hover .mensaje__responder {
  opacity: 1;
}
/* En táctil no hay hover: siempre visible, discreto. */
@media (pointer: coarse) {
  .mensaje__responder {
    opacity: 0.55;
  }
}
.burbuja {
  --radio: 1.1rem;
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  max-width: 100%;
  min-width: 0;
  padding: 0.5rem 0.8rem 0.35rem;
  border-radius: var(--radio);
  color: var(--p-surface-800);
  background: var(--p-content-background);
  box-shadow: 0 1px 1.5px rgb(0 0 0 / 0.06);
  animation: entra 0.18s var(--ease);
  transition: box-shadow 0.3s var(--ease);
}
.racha:not(.racha--mia) .mensaje:not(:last-child) .burbuja {
  border-bottom-left-radius: 0.35rem;
}
.racha:not(.racha--mia) .mensaje + .mensaje .burbuja {
  border-top-left-radius: 0.35rem;
}
.racha--mia .burbuja {
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
}
.racha--mia .mensaje:not(:last-child) .burbuja {
  border-bottom-right-radius: 0.35rem;
}
.racha--mia .mensaje + .mensaje .burbuja {
  border-top-right-radius: 0.35rem;
}
.burbuja--resaltada {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--p-amber-400) 80%, transparent);
}
/* Sin texto: fotos y tarjetas van sueltas, sin burbuja (también las propias). */
.burbuja--suelta,
.racha--mia .burbuja--suelta {
  padding: 0;
  color: var(--p-surface-500);
  background: transparent;
  box-shadow: none;
}
.burbuja--suelta :deep(.tarjeta) {
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.06);
}
.burbuja--suelta.burbuja--resaltada {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--p-amber-400) 80%, transparent);
}
.burbuja__cita {
  display: flex;
  flex-direction: column;
  gap: 0.05rem;
  min-width: 0;
  margin: 0.1rem -0.35rem 0;
  padding: 0.35rem 0.6rem;
  border-left: 3px solid currentColor;
  border-radius: 0.45rem;
  text-align: left;
  background: rgb(0 0 0 / 0.05);
  cursor: pointer;
}
.racha--mia .burbuja__cita {
  background: rgb(255 255 255 / 0.16);
}
.burbuja__cita-autor {
  font-size: 0.72rem;
  font-weight: 600;
}
.burbuja__cita-texto {
  overflow: hidden;
  font-size: 0.8rem;
  opacity: 0.85;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 22rem;
}
.burbuja__texto {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-size: 0.925rem;
  line-height: 1.45;
  a {
    color: inherit;
    text-decoration: underline;
    text-underline-offset: 2px;
  }
}
.burbuja__tarjetas {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  width: min(22rem, 70cqi);
  max-width: 100%;
}
.burbuja__pie {
  display: inline-flex;
  align-items: center;
  align-self: flex-end;
  gap: 0.2rem;
  font-size: 0.66rem;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  time {
    opacity: 0.65;
  }
}
.burbuja--suelta .burbuja__pie {
  padding: 0 0.25rem;
}
.burbuja__visto {
  display: inline-grid;
  opacity: 0.65;
}
.burbuja__visto--si {
  opacity: 1;
  color: var(--p-sky-300);
}
.burbuja--suelta .burbuja__visto--si {
  color: var(--p-primary-color);
}
.conv__bajar {
  position: absolute;
  left: 50%;
  bottom: 5.5rem;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.85rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
  box-shadow: 0 4px 14px rgb(0 0 0 / 0.18);
  transform: translateX(-50%);
  cursor: pointer;
}
@keyframes entra {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
}
@media (prefers-reduced-motion: reduce) {
  .burbuja {
    animation: none;
  }
}
</style>
