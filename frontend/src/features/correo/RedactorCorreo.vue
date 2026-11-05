<!--
  Redactar, como en Gmail: en cuanto se escribe algo nace el borrador y se
  guarda solo mientras se escribe (y al cerrar la pestaña, con `keepalive`).
  Cerrar no lo pierde: queda en Borradores hasta enviarlo o descartarlo a
  propósito. Una respuesta o un reenvío llegan ya armados por el servidor
  (destinatarios, asunto, cita y, al reenviar, los adjuntos).
-->
<template>
  <section class="redactor" @keydown.ctrl.enter.prevent="enviar" @keydown.meta.enter.prevent="enviar">
    <header class="redactor__barra">
      <button type="button" class="accion redactor__volver" aria-label="Cerrar (se guarda el borrador)" @click="cerrar"><icon name="arrow-back" size="1.2rem" color="text-current" /></button>
      <h2 class="redactor__titulo">{{ titulo }}</h2>
      <span class="guardado" :class="`guardado--${estadoGuardado}`" aria-live="polite">
        <template v-if="estadoGuardado === 'guardando'">Guardando…</template>
        <template v-else-if="estadoGuardado === 'guardado'"><icon name="cloud-done-outline" size=".95rem" color="text-current" />Borrador guardado</template>
        <template v-else-if="estadoGuardado === 'error'"><icon name="cloud-off-outline" size=".95rem" color="text-current" />Sin guardar</template>
      </span>
      <button type="button" class="accion redactor__cerrar" aria-label="Cerrar (se guarda el borrador)" v-tooltip.bottom="'Cerrar (se guarda el borrador)'" @click="cerrar"><icon name="close" size="1.2rem" color="text-current" /></button>
    </header>

    <div v-if="cargando" class="p-5"><Skeleton width="100%" height="2rem" class="mb-3" /><Skeleton width="100%" height="2rem" class="mb-3" /><Skeleton width="100%" height="10rem" /></div>

    <div v-else class="redactor__cuerpo">
      <div class="cabeceras">
        <p class="de">
          <span class="de__etiqueta">De</span>
          <span class="de__valor">{{ correo.estado?.nombre }} &lt;{{ correo.estado?.direccion }}&gt;</span>
        </p>
        <CampoDestinatarios ref="campoPara" v-model="r.para" etiqueta-campo="Para" :dominio="dominio">
          <button v-if="!copias" type="button" class="copias" @click.stop="copias = true">Cc Cco</button>
        </CampoDestinatarios>
        <template v-if="copias">
          <CampoDestinatarios ref="campoCc" v-model="r.cc" etiqueta-campo="Cc" :dominio="dominio" />
          <CampoDestinatarios ref="campoCco" v-model="r.cco" etiqueta-campo="Cco" :dominio="dominio" />
        </template>
        <p v-if="hayExternos && !correo.estado?.externo" class="aviso-externo"><icon name="block" size=".95rem" color="text-current" />Solo puede escribir a direcciones de la empresa (@{{ dominio }}).</p>
        <p v-else-if="hayExternos" class="aviso-externo aviso-externo--info"><icon name="public" size=".95rem" color="text-current" />Va también fuera de la empresa.</p>
        <input v-model="r.asunto" class="asunto" type="text" placeholder="Asunto" aria-label="Asunto" maxlength="255" />
      </div>

      <textarea ref="areaTexto" v-model="r.texto" class="texto" aria-label="Mensaje" placeholder="Escriba su mensaje…" />

      <div v-if="adjuntos.length || subidas.length" class="adjuntos">
        <span v-for="a in adjuntos" :key="a.id" class="adjunto">
          <icon :name="a.imagen ? 'image-outline' : 'draft-outline'" size="1.05rem" color="text-current" />
          <span class="adjunto__nombre">{{ a.nombre }}</span>
          <span class="adjunto__tamano">{{ tamano(a.tamano) }}</span>
          <button type="button" class="adjunto__quitar" :aria-label="`Quitar ${a.nombre}`" @click="quitar(a)"><icon name="close" size=".85rem" color="text-current" /></button>
        </span>
        <span v-for="s in subidas" :key="s.clave" class="adjunto adjunto--subiendo">
          <icon name="upload" size="1.05rem" color="text-current" />
          <span class="adjunto__nombre">{{ s.nombre }}</span>
          <span class="adjunto__tamano">{{ Math.round(s.avance * 100) }} %</span>
        </span>
      </div>
    </div>

    <footer v-if="!cargando" class="redactor__pie">
      <Button label="Enviar" :loading="enviando" :disabled="!r.para.length && !r.cc.length && !r.cco.length" @click="enviar">
        <template #icon><icon name="send" size="1rem" color="text-current" class="mr-1.5" /></template>
      </Button>
      <label class="accion" aria-label="Adjuntar archivos" v-tooltip.top="'Adjuntar (hasta 10 MB cada uno)'">
        <icon name="attach-file" size="1.25rem" color="text-current" />
        <input type="file" multiple class="sr-only" @change="adjuntar" />
      </label>
      <span class="flex-1" />
      <span class="atajo">Ctrl + Enter envía</span>
      <button type="button" class="accion" aria-label="Descartar borrador" v-tooltip.top="'Descartar borrador'" @click="descartar"><icon name="delete-outline" size="1.25rem" color="text-current" /></button>
    </footer>
  </section>
</template>

<script setup lang="ts">
import { useConfirm } from "primevue/useconfirm";
import { notify } from "@/core/notify";
import * as api from "@/core/correo/api";
import { mismaRedaccion, redaccionVacia, tamano } from "@/core/correo/modelo";
import { useCorreoStore } from "@/core/correo/store";
import type { Adjunto, Correo, Redaccion } from "@/core/correo/types";
import CampoDestinatarios from "./CampoDestinatarios.vue";

const props = defineProps<{ borrador: number | null }>();
const emit = defineEmits<{ creado: [id: number]; cerrar: [guardado: boolean]; enviado: [correo: Correo] }>();

/** Espera tras la última tecla antes de guardar. */
const PAUSA_MS = 800;

const correo = useCorreoStore();
const confirm = useConfirm();
const id = ref<number | null>(props.borrador);
const r = reactive<Redaccion>({ para: [], cc: [], cco: [], asunto: "", texto: "" });
const adjuntos = ref<Adjunto[]>([]);
const subidas = ref<{ clave: number; nombre: string; avance: number }[]>([]);
const copias = ref(false);
const cargando = ref(props.borrador !== null);
const enviando = ref(false);
const estadoGuardado = ref<"nada" | "guardando" | "guardado" | "error">("nada");
const origen = ref<number | null>(null);
const campoPara = ref<InstanceType<typeof CampoDestinatarios> | null>(null);
const campoCc = ref<InstanceType<typeof CampoDestinatarios> | null>(null);
const campoCco = ref<InstanceType<typeof CampoDestinatarios> | null>(null);
const areaTexto = ref<HTMLTextAreaElement | null>(null);

/** Lo último que quedó en el servidor (para no guardar lo mismo dos veces). */
let guardada: Redaccion = copia();
let descartado = false;
let enviado = false;

const dominio = computed(() => correo.estado?.direccion.split("@")[1] ?? "sistemafdn.com");
const hayExternos = computed(() => [...r.para, ...r.cc, ...r.cco].some((d) => !d.direccion.endsWith(`@${dominio.value}`)));
const titulo = computed(() => (origen.value ? (r.asunto.toLowerCase().startsWith("rv:") ? "Reenviar" : "Responder") : "Mensaje nuevo"));

function copia(): Redaccion {
  return { para: r.para.map((d) => ({ ...d })), cc: r.cc.map((d) => ({ ...d })), cco: r.cco.map((d) => ({ ...d })), asunto: r.asunto, texto: r.texto };
}

onMounted(async () => {
  if (props.borrador !== null) {
    try {
      const b = await api.fetchCorreo(props.borrador);
      if (b.carpeta !== "borradores") throw new Error("Ese correo ya se envió.");
      Object.assign(r, { para: b.para, cc: b.cc, cco: b.cco, asunto: b.asunto, texto: b.texto });
      adjuntos.value = b.adjuntos;
      origen.value = b.origen;
      copias.value = b.cc.length > 0 || b.cco.length > 0;
      guardada = copia();
      estadoGuardado.value = "guardado";
    } catch (e) {
      notify.error(e instanceof Error ? e.message : "No se pudo abrir el borrador.");
      return emit("cerrar", false);
    } finally {
      cargando.value = false;
    }
  }
  await nextTick();
  // Una respuesta empieza arriba de la cita; un correo nuevo, por el destinatario.
  if (r.para.length || r.cc.length) {
    areaTexto.value?.focus();
    areaTexto.value?.setSelectionRange(0, 0);
    areaTexto.value?.scrollTo({ top: 0 });
  } else campoPara.value?.enfocar();
  window.addEventListener("pagehide", alIrse);
});

// Guardado mientras se escribe -------------------------------------------------
let temporizador: ReturnType<typeof setTimeout> | undefined;
/** Los guardados van en fila: nunca dos a la vez (y nunca dos borradores nuevos). */
let fila: Promise<void> = Promise.resolve();

watch(
  r,
  () => {
    if (cargando.value || enviado || descartado) return;
    clearTimeout(temporizador);
    temporizador = setTimeout(() => void guardar(), id.value === null ? PAUSA_MS / 2 : PAUSA_MS);
  },
  { deep: true },
);

function guardar(forzarCreacion = false): Promise<void> {
  clearTimeout(temporizador);
  fila = fila.then(async () => {
    if (enviado || descartado) return;
    const ahora = copia();
    if (id.value === null) {
      if (redaccionVacia(ahora) && !forzarCreacion) return;
    } else if (mismaRedaccion(ahora, guardada)) return;
    estadoGuardado.value = "guardando";
    try {
      if (id.value === null) {
        const b = await api.crearBorrador(ahora);
        id.value = b.id;
        emit("creado", b.id);
        void correo.refrescarEstado(true);
      } else await api.guardarBorrador(id.value, ahora);
      guardada = ahora;
      estadoGuardado.value = mismaRedaccion(copia(), guardada) ? "guardado" : "guardando";
    } catch {
      estadoGuardado.value = "error";
    }
  });
  return fila;
}

/** Se cierra la pestaña: lo último sale aunque la página se corte. */
function alIrse() {
  if (id.value !== null && !enviado && !descartado && !mismaRedaccion(copia(), guardada)) void api.guardarAlSalir(id.value, copia());
}

onBeforeUnmount(() => {
  window.removeEventListener("pagehide", alIrse);
  clearTimeout(temporizador);
  if (enviado || descartado) return;
  if (id.value !== null) alIrse();
  else if (!redaccionVacia(copia())) void api.crearBorrador(copia()).then(() => correo.refrescarEstado(true)).catch(() => undefined);
});

// Adjuntos ------------------------------------------------------------------------
let claves = 0;
async function adjuntar(e: Event) {
  const input = e.target as HTMLInputElement;
  const archivos = [...(input.files ?? [])];
  input.value = "";
  if (!archivos.length) return;
  if (id.value === null) await guardar(true);
  if (id.value === null) return notify.error("No se pudo crear el borrador para adjuntar.");
  for (const archivo of archivos) {
    const s = reactive({ clave: ++claves, nombre: archivo.name, avance: 0 });
    subidas.value.push(s);
    try {
      adjuntos.value.push(await api.subirAdjunto(id.value, archivo, (f) => (s.avance = f)));
    } catch (err) {
      notify.error(`${archivo.name}: ${err instanceof Error ? err.message : "no se pudo subir."}`);
    } finally {
      subidas.value = subidas.value.filter((x) => x.clave !== s.clave);
    }
  }
}

async function quitar(a: Adjunto) {
  if (id.value === null) return;
  adjuntos.value = adjuntos.value.filter((x) => x.id !== a.id);
  await api.quitarAdjunto(id.value, a.id).catch(() => notify.error("No se pudo quitar el adjunto."));
}

// Enviar, cerrar, descartar ---------------------------------------------------------
async function enviar() {
  if (enviando.value || subidas.value.length) return;
  campoPara.value?.confirmar();
  campoCc.value?.confirmar();
  campoCco.value?.confirmar();
  await nextTick();
  if (!r.para.length && !r.cc.length && !r.cco.length) return notify.warning("Agregue al menos un destinatario.");
  if (!r.asunto.trim()) {
    confirm.require({
      header: "Enviar sin asunto",
      message: "¿Enviar este correo sin asunto?",
      acceptLabel: "Enviar",
      rejectLabel: "Volver",
      rejectProps: { severity: "secondary", text: true },
      accept: () => void enviarYa(),
    });
    return;
  }
  await enviarYa();
}

async function enviarYa() {
  enviando.value = true;
  try {
    if (id.value === null) await guardar(true);
    await fila;
    if (id.value === null) throw new Error("No se pudo guardar el borrador.");
    const salida = await api.enviarBorrador(id.value, copia());
    enviado = true;
    void correo.refrescarEstado(true);
    emit("enviado", salida);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : "No se pudo enviar.");
  } finally {
    enviando.value = false;
  }
}

async function cerrar() {
  await guardar();
  emit("cerrar", id.value !== null && !redaccionVacia(copia()));
}

function descartar() {
  const quitarlo = async () => {
    descartado = true;
    clearTimeout(temporizador);
    await fila;
    if (id.value !== null) await api.descartarBorrador(id.value).catch(() => undefined);
    void correo.refrescarEstado(true);
    if (correo.vista === "borradores") void correo.cargar(true);
    notify.info("Borrador descartado.", 2500);
    emit("cerrar", false);
  };
  if (redaccionVacia(copia()) && !adjuntos.value.length) return void quitarlo();
  confirm.require({
    header: "Descartar borrador",
    message: "Se elimina lo escrito y sus adjuntos.",
    acceptLabel: "Descartar",
    rejectLabel: "Cancelar",
    acceptProps: { severity: "danger" },
    rejectProps: { severity: "secondary", text: true },
    accept: () => void quitarlo(),
  });
}
</script>

<style scoped>
.redactor {
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: var(--p-content-background);
}
.redactor__barra {
  flex: none;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  height: 3rem;
  padding: 0 0.6rem 0 1rem;
  border-bottom: 1px solid var(--p-surface-200);
}
.redactor__volver {
  margin-left: -0.5rem;
}
.redactor__cerrar {
  display: none;
}
@container correo (min-width: 40rem) {
  .redactor__volver {
    display: none;
  }
  .redactor__cerrar {
    display: inline-grid;
  }
}
.redactor__titulo {
  flex: 1;
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--p-surface-800);
}
.guardado {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.75rem;
  color: var(--p-surface-500);
}
.guardado--error {
  color: var(--c-danger, #dc2626);
}
.redactor__cuerpo {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 0 1rem;
  overflow-y: auto;
}
.cabeceras {
  flex: none;
}
.de {
  display: flex;
  gap: 0.6rem;
  margin: 0;
  padding: 0.65rem 0;
  border-bottom: 1px solid var(--p-surface-200);
  font-size: 0.82rem;
}
.de__etiqueta {
  flex: none;
  width: 2.6rem;
  color: var(--p-surface-500);
}
.de__valor {
  overflow: hidden;
  color: var(--p-surface-600);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.copias {
  flex: none;
  align-self: center;
  padding: 0.2rem 0.4rem;
  border-radius: 0.4rem;
  font-size: 0.78rem;
  color: var(--p-surface-500);
  cursor: pointer;
  &:hover {
    color: var(--p-surface-900);
    background: var(--p-surface-100);
  }
}
.aviso-externo {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0.5rem 0 0;
  padding: 0.4rem 0.6rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  color: #991b1b;
  background: #fee2e2;
}
.aviso-externo--info {
  color: #9a3412;
  background: #fff7ed;
}
.asunto {
  width: 100%;
  height: 2.75rem;
  border: 0;
  border-bottom: 1px solid var(--p-surface-200);
  outline: 0;
  font-size: 0.95rem;
  font-weight: 500;
  color: var(--p-surface-900);
  background: transparent;
  &:focus {
    border-bottom-color: color-mix(in srgb, var(--p-primary-color) 60%, transparent);
  }
}
.texto {
  flex: 1;
  min-height: 12rem;
  padding: 0.9rem 0;
  border: 0;
  outline: 0;
  resize: none;
  font: inherit;
  font-size: 0.9rem;
  line-height: 1.6;
  color: var(--p-surface-900);
  background: transparent;
}
.adjuntos {
  flex: none;
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  padding: 0.25rem 0 0.9rem;
}
.adjunto {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  max-width: 100%;
  height: 2rem;
  padding: 0 0.25rem 0 0.6rem;
  border-radius: 0.55rem;
  font-size: 0.78rem;
  color: var(--p-surface-700);
  background: var(--p-surface-100);
}
.adjunto--subiendo {
  padding-right: 0.6rem;
  color: var(--p-surface-500);
}
.adjunto__nombre {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.adjunto__tamano {
  flex: none;
  color: var(--p-surface-500);
}
.adjunto__quitar {
  display: grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  color: var(--p-surface-500);
  cursor: pointer;
  &:hover {
    color: var(--p-surface-900);
    background: var(--p-surface-200);
  }
}
.redactor__pie {
  flex: none;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.6rem 0.75rem calc(0.6rem + env(safe-area-inset-bottom));
  border-top: 1px solid var(--p-surface-200);
}
.atajo {
  font-size: 0.75rem;
  color: var(--p-surface-400);
}
@media (pointer: coarse) {
  .atajo {
    display: none;
  }
}
.accion {
  display: grid;
  place-items: center;
  width: 2.3rem;
  height: 2.3rem;
  border-radius: 0.6rem;
  color: var(--p-surface-500);
  cursor: pointer;
  &:hover {
    color: var(--p-surface-800);
    background: var(--p-surface-100);
  }
}
</style>
