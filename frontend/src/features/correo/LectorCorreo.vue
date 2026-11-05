<!--
  Un correo abierto. El HTML va en un `<iframe sandbox>` sin scripts (el
  documento lleva su propia CSP: nada de fuera salvo, si se pide, las
  imágenes) y crece con su contenido. Lo que llegó sin HTML se muestra como
  texto, con la cita atenuada. Abrirlo lo marca leído.
-->
<template>
  <article class="lector">
    <header class="lector__barra">
      <button type="button" class="accion lector__volver" aria-label="Volver a la lista" @click="emit('volver')"><icon name="arrow-back" size="1.2rem" color="text-current" /></button>
      <template v-if="c">
        <template v-if="c.carpeta !== 'papelera'">
          <button type="button" class="accion" aria-label="Responder" v-tooltip.bottom="'Responder'" @click="emit('responder', c, 'responder')"><icon name="reply" size="1.2rem" color="text-current" /></button>
          <button v-if="variosDestinatarios" type="button" class="accion" aria-label="Responder a todos" v-tooltip.bottom="'Responder a todos'" @click="emit('responder', c, 'responderTodos')"><icon name="reply-all" size="1.2rem" color="text-current" /></button>
          <button type="button" class="accion" aria-label="Reenviar" v-tooltip.bottom="'Reenviar'" @click="emit('responder', c, 'reenviar')"><icon name="forward" size="1.2rem" color="text-current" /></button>
          <span class="separador" />
          <button type="button" class="accion" :class="{ 'accion--estrella': c.destacado }" :aria-label="c.destacado ? 'Quitar de destacados' : 'Destacar'" :aria-pressed="c.destacado" v-tooltip.bottom="c.destacado ? 'Quitar de destacados' : 'Destacar'" @click="hacer(c.destacado ? 'quitarDestacado' : 'destacar')">
            <icon :name="c.destacado ? 'star' : 'star-outline'" size="1.2rem" color="text-current" />
          </button>
          <button v-if="c.carpeta === 'entrada'" type="button" class="accion" aria-label="Marcar como no leído" v-tooltip.bottom="'Marcar como no leído'" @click="noLeido"><icon name="mark-email-unread-outline" size="1.2rem" color="text-current" /></button>
          <button type="button" class="accion" aria-label="A la papelera" v-tooltip.bottom="'A la papelera'" @click="hacer('papelera', true)"><icon name="delete-outline" size="1.2rem" color="text-current" /></button>
        </template>
        <template v-else>
          <button type="button" class="accion accion--texto" @click="hacer('restaurar', true)"><icon name="restore-from-trash-outline" size="1.15rem" color="text-current" />Restaurar</button>
          <button type="button" class="accion accion--texto accion--peligro" @click="hacer('eliminar', true)"><icon name="delete-forever-outline" size="1.15rem" color="text-current" />Eliminar para siempre</button>
        </template>
      </template>
    </header>

    <div class="lector__cuerpo">
      <div v-if="!c && !error" class="p-6">
        <Skeleton width="70%" height="1.6rem" class="mb-5" />
        <div class="mb-6 flex gap-3"><Skeleton shape="circle" size="2.5rem" /><div class="flex-1"><Skeleton width="40%" class="mb-2" /><Skeleton width="25%" /></div></div>
        <Skeleton width="100%" class="mb-2" /><Skeleton width="95%" class="mb-2" /><Skeleton width="60%" />
      </div>
      <p v-else-if="error" class="lector__error">{{ error }}</p>

      <template v-else-if="c">
        <h1 class="asunto">
          {{ c.asunto || "(sin asunto)" }}
          <span v-if="c.externo" class="externo">{{ c.carpeta === "enviados" ? "Va fuera" : "Externo" }}</span>
        </h1>

        <div v-if="c.envio === 'fallido'" class="banda banda--error">
          <icon name="error-outline" size="1.15rem" color="text-current" />
          <span class="flex-1">No se pudo entregar a los destinatarios de fuera<template v-if="c.errorEnvio">: {{ c.errorEnvio }}</template></span>
          <button type="button" class="enlace" :disabled="reintentando" @click="reintentar">Reintentar</button>
        </div>
        <div v-else-if="c.envio === 'pendiente'" class="banda"><icon name="schedule-send-outline" size="1.15rem" color="text-current" />Saliendo hacia los destinatarios de fuera…</div>

        <div class="remitente">
          <ChatAvatar :id="tono(c.de.direccion)" :nombre="etiqueta(c.de)" tamano="md" />
          <div class="remitente__datos">
            <div class="remitente__linea">
              <span class="remitente__nombre">{{ etiqueta(c.de) }}</span>
              <span v-if="c.de.nombre" class="remitente__dir">&lt;{{ c.de.direccion }}&gt;</span>
              <span class="remitente__fecha" :title="fechaCompleta(c.fecha)">{{ fechaCompleta(c.fecha) }}</span>
            </div>
            <button type="button" class="destinatarios" :aria-expanded="detalles" @click="detalles = !detalles">
              <span class="truncate">para {{ resumenPara }}</span>
              <icon :name="detalles ? 'arrow-drop-up' : 'arrow-drop-down'" size="1.1rem" color="text-current" />
            </button>
            <dl v-if="detalles" class="detalle">
              <dt>De</dt><dd>{{ formato(c.de) }}</dd>
              <template v-if="c.responderA && c.responderA !== c.de.direccion"><dt>Responder a</dt><dd>{{ c.responderA }}</dd></template>
              <template v-if="c.para.length"><dt>Para</dt><dd>{{ c.para.map(formato).join(", ") }}</dd></template>
              <template v-if="c.cc.length"><dt>Cc</dt><dd>{{ c.cc.map(formato).join(", ") }}</dd></template>
              <template v-if="c.cco.length"><dt>Cco</dt><dd>{{ c.cco.map(formato).join(", ") }}</dd></template>
              <dt>Fecha</dt><dd>{{ fechaCompleta(c.fecha) }}</dd>
            </dl>
          </div>
        </div>

        <div v-if="c.html && remotas && !imagenes" class="banda banda--suave">
          <icon name="hide-image-outline" size="1.15rem" color="text-current" />
          <span class="flex-1">Las imágenes de fuera están ocultas (pueden avisarle al remitente que lo abrió).</span>
          <button type="button" class="enlace" @click="imagenes = true">Mostrar imágenes</button>
        </div>

        <iframe v-if="c.html" ref="marco" class="contenido-html" :srcdoc="documento" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" title="Contenido del correo" @load="ajustar" />
        <div v-else class="contenido-texto">
          <template v-for="(b, i) in bloques" :key="i"><span :class="{ cita: b.cita }">{{ b.texto }}</span>{{ "\n" }}</template>
        </div>

        <section v-if="c.adjuntos.length" class="adjuntos" aria-label="Adjuntos">
          <h2>{{ c.adjuntos.length === 1 ? "1 adjunto" : `${c.adjuntos.length} adjuntos` }}</h2>
          <div class="adjuntos__lista">
            <div v-for="a in c.adjuntos" :key="a.id" class="adjunto">
              <a class="adjunto__abrir" :href="urlAdjunto(a, !abreEnLinea(a.tipo))" target="_blank" rel="noopener" :title="a.nombre">
                <img v-if="a.imagen" :src="urlAdjunto(a)" alt="" class="adjunto__miniatura" loading="lazy" />
                <span v-else class="adjunto__icono"><icon :name="iconoDe(a.tipo)" size="1.4rem" color="text-current" /></span>
                <span class="adjunto__datos">
                  <span class="adjunto__nombre">{{ a.nombre }}</span>
                  <span class="adjunto__tamano">{{ tamano(a.tamano) }}</span>
                </span>
              </a>
              <a class="adjunto__bajar" :href="urlAdjunto(a, true)" :aria-label="`Descargar ${a.nombre}`" v-tooltip.bottom="'Descargar'"><icon name="download" size="1.1rem" color="text-current" /></a>
            </div>
          </div>
        </section>

        <div v-if="c.carpeta !== 'papelera'" class="respuestas">
          <button type="button" class="respuesta" @click="emit('responder', c, 'responder')"><icon name="reply" size="1.05rem" color="text-current" />Responder</button>
          <button v-if="variosDestinatarios" type="button" class="respuesta" @click="emit('responder', c, 'responderTodos')"><icon name="reply-all" size="1.05rem" color="text-current" />Responder a todos</button>
          <button type="button" class="respuesta" @click="emit('responder', c, 'reenviar')"><icon name="forward" size="1.05rem" color="text-current" />Reenviar</button>
        </div>
      </template>
    </div>
  </article>
</template>

<script setup lang="ts">
import { notify } from "@/core/notify";
import { config } from "@/core/config";
import { fetchCorreo, htmlConBase, reintentar as reintentarEnvio, urlAdjunto } from "@/core/correo/api";
import { corto, documentoAislado, etiqueta, fechaCompleta, tamano, tieneImagenesRemotas } from "@/core/correo/modelo";
import { useCorreoStore } from "@/core/correo/store";
import type { Accion, Correo, Direccion, ModoRespuesta } from "@/core/correo/types";
import ChatAvatar from "@/shared/chat/ChatAvatar.vue";

const props = defineProps<{ id: number }>();
const emit = defineEmits<{ volver: []; responder: [correo: Correo, modo: ModoRespuesta]; editar: [id: number]; fuera: [] }>();

const store = useCorreoStore();
const c = ref<Correo | null>(null);
const error = ref<string | null>(null);
const detalles = ref(false);
const imagenes = ref(false);
const reintentando = ref(false);
const marco = ref<HTMLIFrameElement | null>(null);

const remotas = computed(() => !!c.value?.html && tieneImagenesRemotas(c.value.html));
const base = computed(() => new URL(config.restUrl, window.location.origin).origin);
const documento = computed(() => (c.value?.html ? documentoAislado(htmlConBase(c.value.html), imagenes.value, base.value) : ""));
const variosDestinatarios = computed(() => !!c.value && c.value.para.length + c.value.cc.length > 1);
const resumenPara = computed(() => {
  if (!c.value) return "";
  const todos = [...c.value.para, ...c.value.cc];
  const mia = store.estado?.direccion;
  const nombres = todos.map((d) => (d.direccion === mia ? "mí" : corto(d)));
  return nombres.length > 3 ? `${nombres.slice(0, 3).join(", ")} y ${nombres.length - 3} más` : nombres.join(", ") || "(sin destinatarios)";
});
const bloques = computed(() => (c.value?.texto ?? "").replace(/\r\n/g, "\n").split("\n").map((l) => ({ texto: l, cita: l.startsWith(">") })));

async function cargar() {
  try {
    c.value = await fetchCorreo(props.id, true);
    if (c.value.carpeta === "borradores") return emit("editar", props.id);
    store.marcarLeidoLocal(props.id);
  } catch (e) {
    error.value = e instanceof Error ? e.message : "No se pudo abrir el correo.";
  }
}
onMounted(cargar);

/** El iframe crece con su contenido (mismo origen, sin scripts: se puede medir desde aquí). */
let observador: ResizeObserver | null = null;
function ajustar() {
  const doc = marco.value?.contentDocument;
  if (!doc?.body || !marco.value) return;
  const medir = () => marco.value && (marco.value.style.height = `${Math.max(doc.documentElement.scrollHeight, 60)}px`);
  medir();
  observador?.disconnect();
  observador = new ResizeObserver(medir);
  observador.observe(doc.body);
  // Las imágenes cambian el alto al cargar.
  doc.querySelectorAll("img").forEach((img) => img.addEventListener("load", medir, { once: true }));
}
onBeforeUnmount(() => observador?.disconnect());
watch(imagenes, () => nextTick(ajustar));

async function hacer(a: Accion, sale = false) {
  if (!c.value) return;
  try {
    await store.aplicar([c.value.id], a);
    if (sale) {
      notify.info(a === "papelera" ? "Enviado a la papelera." : a === "restaurar" ? "Restaurado." : "Eliminado.", 2500);
      emit("fuera");
    } else if (a === "destacar" || a === "quitarDestacado") c.value = { ...c.value, destacado: a === "destacar" };
  } catch (e) {
    notify.error(e instanceof Error ? e.message : "No se pudo.");
  }
}

async function noLeido() {
  await hacer("noLeido");
  void store.refrescarEstado(true);
  emit("fuera");
}

async function reintentar() {
  if (!c.value) return;
  reintentando.value = true;
  try {
    c.value = await reintentarEnvio(c.value.id);
    notify.info("Se vuelve a intentar la entrega.", 2500);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : "No se pudo reintentar.");
  } finally {
    reintentando.value = false;
  }
}

const formato = (d: Direccion) => (d.nombre ? `${d.nombre} <${d.direccion}>` : d.direccion);
const abreEnLinea = (tipo: string) => tipo.startsWith("image/") || tipo === "application/pdf";

function iconoDe(tipo: string): string {
  if (tipo === "application/pdf") return "picture-as-pdf-outline";
  if (tipo.includes("sheet") || tipo.includes("excel") || tipo === "text/csv") return "table-chart-outline";
  if (tipo.includes("word") || tipo.startsWith("text/")) return "description-outline";
  if (tipo.includes("zip") || tipo.includes("compressed")) return "folder-zip-outline";
  return "draft-outline";
}

function tono(direccion: string): number {
  let h = 0;
  for (const ch of direccion) h = (h * 31 + ch.charCodeAt(0)) | 0;
  return Math.abs(h);
}
</script>

<style scoped>
.lector {
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: var(--p-content-background);
}
.lector__barra {
  flex: none;
  display: flex;
  align-items: center;
  gap: 0.15rem;
  height: 3rem;
  padding: 0 0.6rem;
  border-bottom: 1px solid var(--p-surface-200);
}
.lector__volver {
  margin-right: 0.35rem;
}
@container correo (min-width: 40rem) {
  .lector__volver {
    display: none;
  }
}
.separador {
  width: 1px;
  height: 1.25rem;
  margin: 0 0.35rem;
  background: var(--p-surface-200);
}
.accion {
  display: inline-grid;
  grid-auto-flow: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  min-width: 2.2rem;
  height: 2.2rem;
  border-radius: 0.6rem;
  color: var(--p-surface-500);
  cursor: pointer;
  &:hover {
    color: var(--p-surface-800);
    background: var(--p-surface-100);
  }
}
.accion--texto {
  padding: 0 0.7rem;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--p-surface-700);
}
.accion--peligro {
  color: var(--c-danger, #dc2626);
}
.accion--estrella {
  color: #f59e0b;
}
.lector__cuerpo {
  flex: 1;
  min-height: 0;
  padding: 1.25rem clamp(1rem, 4cqi, 2rem) 2rem;
  overflow-y: auto;
}
.lector__error {
  padding: 2rem;
  text-align: center;
  color: var(--p-surface-500);
}
.asunto {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
  margin: 0 0 1rem;
  font-family: "Space Grotesk", sans-serif;
  font-size: 1.3rem;
  font-weight: 600;
  line-height: 1.3;
  color: var(--p-surface-900);
  overflow-wrap: anywhere;
}
.externo {
  padding: 0 0.4rem;
  border-radius: 0.35rem;
  font-family: system-ui, sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.25rem;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #9a3412;
  background: #ffedd5;
}
.banda {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1rem;
  padding: 0.6rem 0.8rem;
  border-radius: 0.7rem;
  font-size: 0.82rem;
  color: var(--p-surface-700);
  background: var(--p-surface-100);
}
.banda--error {
  color: #991b1b;
  background: #fee2e2;
}
.banda--suave {
  color: var(--p-surface-600);
  background: var(--p-surface-50);
  border: 1px solid var(--p-surface-200);
}
.enlace {
  flex: none;
  font-weight: 600;
  color: var(--p-primary-color);
  cursor: pointer;
  &:hover {
    text-decoration: underline;
  }
}
.banda--error .enlace {
  color: #991b1b;
}
.remitente {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
.remitente__datos {
  flex: 1;
  min-width: 0;
}
.remitente__linea {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0 0.4rem;
}
.remitente__nombre {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--p-surface-900);
}
.remitente__dir {
  font-size: 0.8rem;
  color: var(--p-surface-500);
}
.remitente__fecha {
  margin-left: auto;
  font-size: 0.75rem;
  color: var(--p-surface-500);
}
.destinatarios {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  font-size: 0.8rem;
  color: var(--p-surface-500);
  cursor: pointer;
  &:hover {
    color: var(--p-surface-800);
  }
}
.detalle {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0.25rem 0.75rem;
  margin: 0.5rem 0 0;
  padding: 0.65rem 0.8rem;
  border-radius: 0.6rem;
  font-size: 0.78rem;
  background: var(--p-surface-50);
  border: 1px solid var(--p-surface-200);
  dt {
    color: var(--p-surface-500);
    text-align: right;
  }
  dd {
    margin: 0;
    color: var(--p-surface-700);
    overflow-wrap: anywhere;
  }
}
.contenido-html {
  display: block;
  width: 100%;
  height: 120px;
  border: 0;
  border-radius: 0.5rem;
  background: #fff;
}
.contenido-texto {
  font-size: 0.9rem;
  line-height: 1.6;
  color: var(--p-surface-800);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.cita {
  color: var(--p-surface-500);
}
.adjuntos {
  margin-top: 1.75rem;
  padding-top: 1rem;
  border-top: 1px solid var(--p-surface-200);
  h2 {
    margin: 0 0 0.6rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--p-surface-600);
  }
}
.adjuntos__lista {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr));
  gap: 0.5rem;
}
.adjunto {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
  padding: 0.5rem 0.4rem 0.5rem 0.5rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.7rem;
  color: inherit;
  text-decoration: none;
  &:hover {
    border-color: var(--p-surface-300);
    background: var(--p-surface-50);
  }
}
.adjunto__abrir {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}
.adjunto__miniatura,
.adjunto__icono {
  flex: none;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.45rem;
}
.adjunto__miniatura {
  object-fit: cover;
}
.adjunto__icono {
  display: grid;
  place-items: center;
  color: var(--p-primary-700);
  background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
}
.adjunto__datos {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.adjunto__nombre {
  overflow: hidden;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--p-surface-800);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.adjunto__tamano {
  font-size: 0.75rem;
  color: var(--p-surface-500);
}
.adjunto__bajar {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  color: var(--p-surface-500);
  &:hover {
    color: var(--p-surface-800);
    background: var(--p-surface-200);
  }
}
.respuestas {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 2rem;
}
.respuesta {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  height: 2.2rem;
  padding: 0 1rem;
  border: 1px solid var(--p-surface-300);
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--p-surface-700);
  cursor: pointer;
  &:hover {
    background: var(--p-surface-100);
  }
}
</style>
