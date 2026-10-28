<!--
  Una conversación: cabecera, mensajes por día y por racha de autor (texto,
  fotos y documentos, lo compartido plegado por tipo, cita del mensaje al que
  responde y "visto" en los propios) y el redactor. Abre pegada al final; si
  llega algo mientras se lee más arriba, ofrece bajar en lugar de saltar. Se
  pueden soltar archivos encima. Los avisos del sistema son de solo lectura,
  y en un grupo dirigido solo escriben el dueño y los que él elige. Cada
  mensaje tiene sus acciones (responder, en un grupo responder en privado,
  reenviar, editar el propio, copiar, eliminar); editar carga el mensaje en
  el redactor y lo enviado sale como respuesta al original, marcado "editado".
  Con el dedo: deslizar un mensaje a la derecha lo responde, mantenerlo
  pulsado abre sus acciones y, con la app instalada en el teléfono, deslizar
  desde el borde izquierdo vuelve a la bandeja (`gestosTactiles.ts`).
-->
<template>
  <section ref="raiz" class="conv" @dragenter.prevent="alEntrar" @dragover.prevent @dragleave="alSalir" @drop.prevent="alSoltar">
    <header class="conv__cabeza">
      <button type="button" class="conv__volver tap-target" aria-label="Volver a la bandeja" @click="emit('volver')">
        <icon name="arrow-back" color="text-current" />
      </button>
      <template v-if="canal">
        <ChatAvatar :id="canal.id" :nombre="canal.nombre" :grupo="canal.tipo === 'grupo'" :sistema="esSistema" :ambito="canal.contacto?.ambito" :foto="canal.tipo === 'grupo' ? canal.foto : canal.contacto?.foto" :en-linea="enLinea" />
        <div class="min-w-0">
          <div class="conv__nombre">{{ canal.nombre }}</div>
          <div class="conv__sub" :title="miembros">
            <span v-if="enLinea" class="conv__en-linea">En línea</span><span v-if="enLinea && subtitulo"> · </span><span v-if="canal.modo === 'dirigido'" class="conv__dirigido"><icon name="campaign-outline" size=".85rem" color="text-current" />Dirigido · </span>{{ subtitulo }}
          </div>
        </div>
        <template v-if="canal.tipo === 'grupo'">
          <button type="button" class="conv__foto-grupo tap-target" :aria-label="canal.administro ? 'Editar grupo' : 'Información del grupo'" v-tooltip.bottom="canal.administro ? 'Editar grupo' : 'Información del grupo'" @click="editandoGrupo = true">
            <icon :name="canal.administro ? 'edit-outline' : 'info-outline'" size="1.05rem" color="text-current" />
          </button>
          <button v-if="puedeCambiarFoto" type="button" class="conv__foto-grupo conv__foto-grupo--siguiente tap-target" aria-label="Foto del grupo" aria-haspopup="true" aria-controls="conv-foto-grupo" v-tooltip.bottom="'Foto del grupo'" @click="menuFoto?.toggle($event)">
            <icon name="photo-camera" size="1.05rem" color="text-current" />
          </button>
          <Menu id="conv-foto-grupo" ref="menuFoto" :model="opcionesFoto" popup />
          <input ref="selectorFoto" type="file" accept="image/*" class="hidden" @change="alElegirFoto" />
        </template>
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
            <p v-else>
              Escriba el primer mensaje.<br /><span class="text-xs">Puede mandar fotos con el clip, adjuntar boletos, salidas o cualquier registro con <b>+</b>, o enviarlos desde sus listados.</span>
            </p>
          </div>
          <template v-for="b in bloques" :key="b.dia">
            <div class="conv__dia">
              <span>{{ b.etiqueta }}</span>
            </div>
            <div v-for="r in b.rachas" :key="r.mensajes[0]!.id" class="racha" :class="{ 'racha--mia': r.mio }">
              <ChatAvatar v-if="!r.mio && canal?.tipo === 'grupo'" :id="r.autor?.id ?? 0" :nombre="r.autor?.nombre ?? 'Sistema'" :foto="r.autor?.foto" tamano="sm" class="racha__avatar" />
              <div class="racha__mensajes">
                <span v-if="!r.mio && canal?.tipo === 'grupo'" class="racha__autor" :style="{ '--tono': tono(r.autor?.id ?? 0) * 45 }"
                  >{{ r.autor?.nombre ?? "Sistema" }}<span v-if="r.via" class="racha__via" v-tooltip.top="`Escribe como miembro del grupo ${r.via.nombre}`"><icon name="groups-outline" size=".8rem" color="text-current" />{{ r.via.nombre }}</span></span
                >
                <div v-for="m in r.mensajes" :key="m.id" class="mensaje" :data-id="m.id">
                  <span v-if="puedeEscribir" class="mensaje__deslizar" aria-hidden="true"><icon name="reply" size="1.1rem" color="text-current" /></span>
                  <div :id="`m-${m.id}`" class="burbuja" :class="{ 'burbuja--suelta': !m.texto && !m.respuesta && !m.reenviado && !m.eliminado, 'burbuja--eliminada': m.eliminado, 'burbuja--resaltada': resaltado === m.id }">
                    <p v-if="m.eliminado" class="burbuja__eliminado"><icon name="block" size=".95rem" color="text-current" />Se eliminó este mensaje</p>
                    <template v-else>
                      <span v-if="m.reenviado" class="burbuja__marca"><icon name="forward" size=".85rem" color="text-current" />Reenviado</span>
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
                      <TarjetasMensaje v-if="m.adjuntos.length" :adjuntos="m.adjuntos" class="burbuja__tarjetas" />
                    </template>
                    <span class="burbuja__pie">
                      <span v-if="m.editado" class="burbuja__editado">editado</span>
                      <time :datetime="m.fecha">{{ horaCorta(m.fecha) }}</time>
                      <span v-if="r.mio" v-tooltip.left="tituloVisto(m.id)" class="burbuja__visto" :class="{ 'burbuja__visto--si': visto(m.id).todos }" :aria-label="tituloVisto(m.id)">
                        <icon :name="visto(m.id).todos ? 'done-all' : 'done'" size=".95rem" color="text-current" :weight="500" />
                      </span>
                    </span>
                  </div>
                  <span v-if="!m.eliminado" class="mensaje__acciones">
                    <button v-if="puedeEscribir" type="button" class="mensaje__boton" :aria-label="`Responder: ${m.texto.slice(0, 40)}`" v-tooltip.top="'Responder'" @click="responder(m)">
                      <icon name="reply" size="1.05rem" color="text-current" />
                    </button>
                    <button v-if="!esSistema" type="button" class="mensaje__boton" aria-label="Más acciones" aria-haspopup="true" aria-controls="conv-acciones" v-tooltip.top="'Más'" @click="abrirAcciones($event, m)">
                      <icon name="more-vert" size="1.05rem" color="text-current" />
                    </button>
                  </span>
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
      <button v-if="pendientes > 0" type="button" class="conv__bajar" @click="bajar(true)"><icon name="arrow-downward" size="1rem" color="text-current" />{{ pendientes === 1 ? "1 mensaje nuevo" : `${pendientes} mensajes nuevos` }}</button>
    </Transition>

    <p v-if="esSistema" class="conv__solo-lectura"><icon name="lock-outline" size="1rem" color="text-current" />Avisos automáticos: no se puede responder aquí.</p>
    <p v-else-if="canal && !canal.puedeEscribir" class="conv__solo-lectura"><icon name="campaign-outline" size="1rem" color="text-current" />Grupo dirigido: solo escriben el dueño y quienes él elija.</p>
    <Redactor v-else ref="redactor" :enviando="enviando" :respondiendo="respondiendo" :editando="editando" @enviar="enviar" @cancelar-respuesta="respondiendo = null" @cancelar-edicion="editando = null" />

    <Transition name="fade">
      <div v-if="arrastrando" class="conv__soltar"><icon name="upload-file-outline" size="2.5rem" color="text-current" />Suelte aquí para adjuntar</div>
    </Transition>
    <HojaAcciones :visible="menuDe !== null" :acciones="accionesMenu" :titulo="menuDe ? extractoMensaje(menuDe) : ''" @update:visible="!$event && (menuDe = null)" />
    <Menu id="conv-acciones" ref="menuAcciones" :model="opcionesMensaje" popup>
      <template #item="{ item, props: p }">
        <a v-bind="p.action" class="flex items-center gap-2.5" :style="item.peligro ? { color: 'var(--c-danger)' } : undefined">
          <icon :name="String(item.icono)" size="1.1rem" color="text-current" />
          <span class="text-sm">{{ item.label }}</span>
        </a>
      </template>
    </Menu>
    <EnviarPorChatDialog :visible="reenviando !== null" :mensaje="reenviando" @update:visible="!$event && (reenviando = null)" />
    <GrupoDialog v-if="canal?.tipo === 'grupo'" v-model:visible="editandoGrupo" :canal-id="canalId" @salio="emit('volver')" />
    <VisorImagen :fotos="visor.fotos" :inicio="visor.inicio" @cerrar="visor.fotos = []" />
  </section>
</template>

<script setup lang="ts">
import type { MenuItem } from "primevue/menuitem";
import type Menu from "primevue/menu";
import type { Envio } from "@/core/chat/api";
import { useChatStore } from "@/core/chat/store";
import { agrupar, extractoMensaje, horaCorta, segmentos, tono, ultimo, vistoPor } from "@/core/chat/modelo";
import type { Archivo, Mensaje, Referencia } from "@/core/chat/types";
import { notify } from "@/core/notify";
import { useConfirm } from "primevue/useconfirm";
import ChatAvatar from "@/shared/chat/ChatAvatar.vue";
import EnviarPorChatDialog from "@/shared/chat/EnviarPorChatDialog.vue";
import ArchivosMensaje from "./ArchivosMensaje.vue";
import { appInstalada, useGestosMensajes, useVolverDesdeElBorde } from "./gestosTactiles";
import GrupoDialog from "./GrupoDialog.vue";
import HojaAcciones, { type AccionHoja } from "./HojaAcciones.vue";
import { prepararArchivo } from "./imagen";
import Redactor from "./Redactor.vue";
import TarjetasMensaje from "./tarjetas/TarjetasMensaje.vue";
import { useVentanaChat } from "./ventana";
import VisorImagen from "./VisorImagen.vue";

/** `activa`: a la vista (minimizada o cerrada no marca leído lo que llega). */
const props = withDefaults(defineProps<{ canalId: number; adjuntar?: Referencia | null; activa?: boolean }>(), { adjuntar: null, activa: true });
const emit = defineEmits<{ volver: []; adjuntado: []; abrir: [canal: number] }>();

const AMBITOS = { administracion: "Administración", estacion: "Estación", agencia: "Agencia" } as const;
/** Distancia al final (px) para considerar que se está leyendo lo último. */
const CERCA = 120;
/** Páginas hacia atrás que se cargan, como mucho, para llegar a un mensaje citado. */
const MAX_PAGINAS_CITA = 10;

const chat = useChatStore();
const ventana = useVentanaChat();
const raiz = ref<HTMLElement | null>(null);
const lienzo = ref<HTMLElement | null>(null);
const redactor = ref<InstanceType<typeof Redactor> | null>(null);
const cargado = ref(false);
const cargandoPrevios = ref(false);
const enviando = ref(false);
const pendientes = ref(0);
const respondiendo = ref<Mensaje | null>(null);
/** Mensaje propio cargado en el redactor para editarlo. */
const editando = ref<Mensaje | null>(null);
const reenviando = ref<Mensaje | null>(null);
const editandoGrupo = ref(false);
const menuAcciones = ref<InstanceType<typeof Menu> | null>(null);
/** Mensaje del menú de acciones abierto (escritorio). */
const accionesDe = ref<Mensaje | null>(null);
const confirm = useConfirm();
const resaltado = ref<number | null>(null);
const arrastrando = ref(false);
const visor = reactive<{ fotos: Archivo[]; inicio: number }>({ fotos: [], inicio: 0 });
/** Mensaje mantenido pulsado: sus acciones en la hoja. */
const menuDe = ref<Mensaje | null>(null);
/** Foto del grupo: menú del botón de la cabecera y el selector de archivo que abre. */
const menuFoto = ref<InstanceType<typeof Menu> | null>(null);
const selectorFoto = ref<HTMLInputElement | null>(null);
const opcionesFoto = computed<MenuItem[]>(() => [{ label: "Cambiar foto", command: () => selectorFoto.value?.click() }, ...(canal.value?.foto ? [{ label: "Quitar foto", command: () => cambiarFotoGrupo(null) }] : [])]);

async function alElegirFoto(e: Event) {
  const input = e.target as HTMLInputElement;
  const archivo = input.files?.[0];
  input.value = "";
  if (archivo) await cambiarFotoGrupo(archivo);
}

async function cambiarFotoGrupo(archivo: File | null) {
  try {
    if (archivo) {
      const { blob, nombre } = await prepararArchivo(archivo);
      await chat.fotoGrupo(props.canalId, blob, nombre);
      notify.success("Foto del grupo actualizada.");
    } else {
      await chat.fotoGrupo(props.canalId, null);
      notify.success("Foto del grupo quitada.");
    }
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  }
}

const canal = computed(() => chat.canal(props.canalId));
const esSistema = computed(() => canal.value?.tipo === "sistema");
const puedeEscribir = computed(() => canal.value?.puedeEscribir ?? false);
/** En un abierto la foto la cambia cualquiera; en un dirigido, el dueño. */
const puedeCambiarFoto = computed(() => canal.value?.modo === "abierto" || !!canal.value?.administro);
const lista = computed(() => chat.mensajes[props.canalId] ?? []);
const bloques = computed(() => agrupar(lista.value, chat.yo?.id ?? null));
const miembros = computed(() => canal.value?.miembros.map((m) => (m.id === chat.yo?.id ? "Tú" : m.nombre)).join(", ") ?? "");
/** Solo en las directas: null (sin indicador) en grupos y avisos del sistema. */
const enLinea = computed(() => (canal.value?.tipo === "directo" && canal.value.contacto ? chat.estaEnLinea(canal.value.contacto.id) : null));

const subtitulo = computed(() => {
  const c = canal.value;
  if (!c) return "";
  if (c.tipo === "sistema") return "Avisos automáticos";
  if (c.tipo === "grupo") return miembros.value;
  return c.contacto?.lugar ?? AMBITOS[c.contacto?.ambito ?? "administracion"];
});

// "Visto": ✓ enviado, ✓✓ visto por todos los demás.
const visto = (id: number) => (canal.value ? vistoPor(canal.value, id) : { todos: false, quienes: [] });
function tituloVisto(id: number) {
  const v = visto(id);
  if (canal.value?.tipo !== "grupo") return v.todos ? "Visto" : "Enviado";
  return v.quienes.length ? `Visto por ${v.quienes.map((p) => p.nombre.split(" ")[0]).join(", ")}` : "Enviado";
}

const cercaDelFinal = () => {
  const el = lienzo.value;
  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < CERCA;
};

function bajar(suave = false) {
  pendientes.value = 0;
  void nextTick(() => lienzo.value?.scrollTo({ top: lienzo.value.scrollHeight, behavior: suave ? "smooth" : "auto" }));
}

// Nuevos al final: seguir abajo si ya se estaba ahí; si no, contarlos.
watch(
  () => ultimo(lista.value)?.id,
  (nuevo, previo) => {
    if (!cargado.value || !nuevo || nuevo === previo) return;
    const mio = ultimo(lista.value)?.autor?.id === chat.yo?.id;
    if (mio || cercaDelFinal()) bajar(true);
    else pendientes.value += 1;
  },
);

async function alDesplazar() {
  const el = lienzo.value;
  if (!el) return;
  if (cercaDelFinal()) pendientes.value = 0;
  if (el.scrollTop > 80 || cargandoPrevios.value || !cargado.value || chat.completos[props.canalId]) return;
  await cargarPrevios();
}

/** Trae la página anterior sin mover lo que se está viendo. */
async function cargarPrevios() {
  const el = lienzo.value;
  if (!el) return;
  cargandoPrevios.value = true;
  const alto = el.scrollHeight;
  try {
    await chat.cargarAnteriores(props.canalId);
    await nextTick();
    el.scrollTop += el.scrollHeight - alto;
  } finally {
    cargandoPrevios.value = false;
  }
}

/** Lleva al mensaje citado (cargando hacia atrás si hace falta) y lo resalta. */
async function irA(id: number) {
  for (let i = 0; i < MAX_PAGINAS_CITA && !lista.value.some((m) => m.id === id) && !chat.completos[props.canalId]; i++) await cargarPrevios();
  await nextTick();
  const el = document.getElementById(`m-${id}`);
  const contenedor = lienzo.value;
  if (!el || !contenedor) return notify.info("El mensaje citado ya no está disponible.");
  const destino = el.getBoundingClientRect().top - contenedor.getBoundingClientRect().top + contenedor.scrollTop - contenedor.clientHeight / 3;
  contenedor.scrollTo({ top: destino, behavior: "smooth" });
  resaltado.value = id;
  setTimeout(() => resaltado.value === id && (resaltado.value = null), 1600);
}

function responder(m: Mensaje) {
  if (m.eliminado || !puedeEscribir.value) return;
  editando.value = null;
  respondiendo.value = m;
  redactor.value?.enfocar();
}

/**
 * Reenvía el mensaje a un directo con su autor (lo crea si no existe) y abre
 * ese directo con el reenvío citado, para responderle en privado.
 */
async function responderEnPrivado(m: Mensaje) {
  if (!m.autor) return;
  try {
    const [directo] = await chat.reenviar(m.id, { usuarios: [m.autor.id], canales: [] });
    if (directo === undefined) return;
    ventana.citarAlAbrir = directo;
    emit("abrir", directo);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  }
}

/** Al abrir el directo de «Responder en privado», cita el reenvío recién hecho. */
function citarReenvio() {
  const citar = ventana.citarAlAbrir === props.canalId;
  ventana.citarAlAbrir = null;
  if (!citar) return;
  const reenvio = [...lista.value].reverse().find((x) => x.reenviado && x.autor?.id === chat.yo?.id);
  if (reenvio) responder(reenvio);
}

function editar(m: Mensaje) {
  respondiendo.value = null;
  editando.value = m;
}

function eliminar(m: Mensaje) {
  confirm.require({
    header: "Eliminar mensaje",
    message: m.autor?.id === chat.yo?.id ? "Se eliminará para todos los de la conversación." : `Se eliminará el mensaje de ${m.autor?.nombre ?? "otro miembro"} para todos los del grupo.`,
    acceptProps: { label: "Eliminar", severity: "danger" },
    rejectProps: { label: "Cancelar", severity: "secondary" },
    accept: async () => {
      try {
        await chat.eliminar(m);
        if (editando.value?.id === m.id) editando.value = null;
        if (respondiendo.value?.id === m.id) respondiendo.value = null;
      } catch (e) {
        notify.error(e instanceof Error ? e.message : String(e));
      }
    },
  });
}

/** Lo que se puede hacer con un mensaje: el menú (escritorio) y la hoja (teléfono). */
function acciones(m: Mensaje): AccionHoja[] {
  if (m.eliminado) return [];
  const mio = m.autor !== null && m.autor.id === chat.yo?.id;
  return [...(puedeEscribir.value ? [{ etiqueta: "Responder", icono: "reply", accion: () => responder(m) }] : []), ...(canal.value?.tipo === "grupo" && m.autor && !mio ? [{ etiqueta: "Responder en privado", icono: "lock-outline", accion: () => void responderEnPrivado(m) }] : []), ...(!esSistema.value ? [{ etiqueta: "Reenviar", icono: "forward", accion: () => (reenviando.value = m) }] : []), ...(mio && puedeEscribir.value ? [{ etiqueta: "Editar", icono: "edit-outline", accion: () => editar(m) }] : []), ...(m.texto ? [{ etiqueta: "Copiar texto", icono: "content-copy-outline", accion: () => void copiar(m.texto) }] : []), ...(m.respuesta ? [{ etiqueta: m.editado ? "Ver el original" : "Ver el mensaje citado", icono: "format-quote-outline", accion: () => void irA(m.respuesta!.id) }] : []), ...(m.autor && (mio || canal.value?.administro) ? [{ etiqueta: "Eliminar", icono: "delete-outline", peligro: true, accion: () => eliminar(m) }] : [])];
}

const opcionesMensaje = computed<MenuItem[]>(() => (accionesDe.value ? acciones(accionesDe.value) : []).map((a) => ({ label: a.etiqueta, icono: a.icono, peligro: a.peligro, command: a.accion })));
function abrirAcciones(e: Event, m: Mensaje) {
  accionesDe.value = m;
  menuAcciones.value?.toggle(e);
}

// Gestos táctiles ----------------------------------------------------------
const mensajePorId = (id: number) => lista.value.find((m) => m.id === id);
useGestosMensajes(lienzo, {
  puedeResponder: () => puedeEscribir.value,
  responder: (id) => {
    const m = mensajePorId(id);
    if (m) responder(m);
  },
  acciones: (id) => (menuDe.value = mensajePorId(id) ?? null),
});
useVolverDesdeElBorde(
  raiz,
  () => ventana.movil && appInstalada(),
  () => emit("volver"),
);

const accionesMenu = computed<AccionHoja[]>(() => (menuDe.value ? acciones(menuDe.value) : []));

async function copiar(texto: string) {
  try {
    await navigator.clipboard.writeText(texto);
    notify.success("Texto copiado.");
  } catch {
    notify.error("No se pudo copiar el texto.");
  }
}

function verFotos(fotos: Archivo[], inicio: number) {
  visor.inicio = inicio;
  visor.fotos = fotos;
}

async function enviar(envio: Envio) {
  enviando.value = true;
  try {
    await chat.enviar(props.canalId, envio);
    redactor.value?.limpiar();
    respondiendo.value = null;
    editando.value = null;
    bajar(true);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  } finally {
    enviando.value = false;
  }
}

// Soltar archivos sobre la conversación -----------------------------------
const llevaArchivos = (e: DragEvent) => !esSistema.value && [...(e.dataTransfer?.types ?? [])].includes("Files");
function alEntrar(e: DragEvent) {
  if (llevaArchivos(e)) arrastrando.value = true;
}
function alSalir(e: DragEvent) {
  const dentro = e.relatedTarget instanceof Node && (e.currentTarget as HTMLElement).contains(e.relatedTarget);
  if (!dentro) arrastrando.value = false;
}
function alSoltar(e: DragEvent) {
  arrastrando.value = false;
  if (!llevaArchivos(e)) return;
  void redactor.value?.agregarArchivos([...(e.dataTransfer?.files ?? [])]);
}

onMounted(async () => {
  try {
    await (props.activa ? chat.abrir(props.canalId) : chat.cargar(props.canalId));
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
    emit("volver");
    return;
  }
  cargado.value = true;
  citarReenvio();
  bajar();
  recibirAdjunto();
  if (props.activa) redactor.value?.enfocar();
});
onUnmounted(() => chat.cerrar(props.canalId));

/** Un registro que llega para enviar (`?adjuntar=`, "Reportar por chat"). */
function recibirAdjunto() {
  if (!props.adjuntar || !cargado.value) return;
  redactor.value?.adjuntar(props.adjuntar);
  emit("adjuntado");
}
watch(() => props.adjuntar, recibirAdjunto);

// Al volver a la vista: lo que llegó queda leído; al salir, deja de marcarse.
watch(
  () => props.activa,
  (activa) => {
    if (!cargado.value) return;
    if (!activa) return chat.cerrar(props.canalId);
    void chat.abrir(props.canalId);
    if (cercaDelFinal()) bajar();
    redactor.value?.enfocar();
  },
);
</script>

<style scoped>
.conv__en-linea {
  color: var(--p-green-600);
  font-weight: 500;
}
.conv {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  background: var(--p-gray-100);
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
@container chat (min-width: 40rem) {
  .conv__volver {
    display: none;
  }
}
.conv__foto-grupo {
  display: grid;
  place-items: center;
  flex: none;
  margin-left: auto;
  &.conv__foto-grupo--siguiente {
    margin-left: 0;
  }
  border-radius: 50%;
  color: var(--p-surface-500);
  &:hover {
    color: var(--p-surface-800);
    background: var(--p-surface-100);
  }
}
.conv__dirigido {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  font-weight: 500;
  vertical-align: bottom;
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
  color: light-dark(hsl(calc(var(--tono) * 1deg) 50% 40%), hsl(calc(var(--tono) * 1deg) 60% 72%));
}
.racha__via {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  margin-left: 0.4rem;
  padding: 0 0.4rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 500;
  color: var(--p-surface-500);
  background: var(--p-surface-200);
  vertical-align: 1px;
}
/* Mensaje = burbuja + acciones (al lado, aparecen al pasar o enfocar). */
.mensaje {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  max-width: 100%;
  min-width: 0;
}
.racha--mia .mensaje {
  flex-direction: row-reverse;
}
/* Aparece a la izquierda mientras se desliza el mensaje para responderlo (`--arrastre` 0 → 1). */
.mensaje__deslizar {
  position: absolute;
  top: 50%;
  left: -2.5rem;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  color: var(--p-primary-color);
  background: var(--p-content-background);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.12);
  opacity: var(--arrastre, 0);
  transform: translateY(-50%) scale(calc(0.6 + var(--arrastre, 0) * 0.4));
  pointer-events: none;
}
.mensaje__acciones {
  display: flex;
  flex: none;
  opacity: 0;
  transition: opacity 0.15s var(--ease);
  &:focus-within {
    opacity: 1;
  }
}
.racha--mia .mensaje__acciones {
  flex-direction: row-reverse;
}
.mensaje:hover .mensaje__acciones {
  opacity: 1;
}
.mensaje__boton {
  display: grid;
  place-items: center;
  flex: none;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  color: var(--p-surface-500);
  transition: background 0.15s var(--ease);
  &:hover {
    color: var(--p-primary-color);
    background: var(--p-surface-100);
  }
}
/* En táctil se responde deslizando o manteniendo pulsado: sin botón, el
   desplazamiento vertical sigue siendo del navegador y el texto no se
   selecciona al mantener (para copiarlo está la hoja). */
@media (pointer: coarse) {
  .mensaje__acciones {
    display: none;
  }
  /* Lo horizontal es de los gestos (responder, volver desde el borde). */
  .conv__cabeza,
  .conv__lienzo {
    touch-action: pan-y;
  }
  .mensaje {
    touch-action: pan-y;
    user-select: none;
    -webkit-user-select: none;
    -webkit-touch-callout: none;
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
  background: var(--p-surface-0);
  /*background: var(--p-content-background);*/
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
/* Mis mensajes: verde de WhatsApp, a la derecha; el texto oscuro se mantiene también en el tema oscuro. */
.racha--mia .burbuja {
  color: #111b21;
  background: #d9fdd3;
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
.burbuja--suelta :deep(.grupo) {
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.06);
}
.burbuja--suelta.burbuja--resaltada {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--p-amber-400) 80%, transparent);
}
.burbuja--eliminada,
.racha--mia .burbuja--eliminada {
  color: var(--p-surface-500);
  background: transparent;
  box-shadow: inset 0 0 0 1px var(--p-surface-300);
}
.burbuja__eliminado {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  font-size: 0.85rem;
  font-style: italic;
}
.burbuja__marca {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.72rem;
  font-style: italic;
  opacity: 0.75;
}
.burbuja__editado {
  margin-right: 0.15rem;
  font-style: italic;
  opacity: 0.65;
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
  background: color-mix(in srgb, currentColor 6%, transparent);
  cursor: pointer;
}
.racha--mia .burbuja__cita {
  background: rgb(0 0 0 / 0.06);
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
