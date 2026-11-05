<!--
  Correo de la empresa (ADR-048), dentro de la página o la ventana del chat.
  Por el ancho del propio contenedor (no de la página):
  - 60rem o más: carpetas a la izquierda, lista y el correo (o el redactor).
  - 40rem o más: lista (carpetas arriba) y el correo.
  - Menos: una cosa a la vez, deslizándose.
  Redactar no es un diálogo: ocupa el lugar del correo y su borrador se
  guarda solo; cerrarlo no lo pierde (queda en Borradores).
-->
<template>
  <div class="correo">
    <div class="correo__rejilla" :class="{ 'correo--abierto': abierto }">
      <nav class="carpetas" aria-label="Carpetas">
        <button type="button" class="redactar" @click="redactar()"><icon name="edit-square-outline" size="1.15rem" color="text-current" /><span>Redactar</span></button>
        <button v-for="v in VISTAS" :key="v.vista" type="button" class="carpeta" :class="{ 'carpeta--activa': store.vista === v.vista }" @click="elegir(v.vista)">
          <icon :name="store.vista === v.vista ? v.icono.replace('-outline', '') : v.icono" size="1.15rem" color="text-current" />
          <span class="carpeta__nombre">{{ v.nombre }}</span>
          <span v-if="contador(v.vista)" class="carpeta__n" :class="{ 'carpeta__n--alerta': v.vista === 'enviados' }">{{ contador(v.vista) }}</span>
        </button>
        <p v-if="store.estado" class="carpetas__cuenta" :title="store.estado.direccion"><icon name="alternate-email" size=".9rem" color="text-current" />{{ store.estado.direccion }}</p>
      </nav>

      <ListaCorreos class="correo__lista" :activo="correo" @abrir="abrir" @redactar="redactar()" @elegir="elegir" />

      <RedactorCorreo v-if="ventana.redactando" :key="`r${claveRedactor}`" class="correo__panel" :borrador="ventana.borrador" @creado="ventana.borrador = $event" @cerrar="cerrarRedactor" @enviado="alEnviar" />
      <LectorCorreo v-else-if="correo" :key="correo" class="correo__panel" :id="correo" @volver="emit('abrir', null)" @responder="responder" @editar="editarBorrador" @fuera="emit('abrir', null)" />
      <div v-else class="correo__portada">
        <span class="portada__icono"><icon name="mail-outline" size="2.25rem" color="text-current" /></span>
        <h2>Correo de la empresa</h2>
        <p v-if="store.estado">Su dirección es <strong>{{ store.estado.direccion }}</strong>. Lo que le escriban a ella, de la casa o de fuera, llega aquí, junto a sus mensajes.</p>
        <Button label="Redactar" size="small" @click="redactar()"><template #icon><icon name="edit-square-outline" size="1.05rem" color="text-current" class="mr-1.5" /></template></Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { notify } from "@/core/notify";
import { crearBorrador } from "@/core/correo/api";
import { VISTAS } from "@/core/correo/modelo";
import { useCorreoStore } from "@/core/correo/store";
import type { Correo, ModoRespuesta, Vista } from "@/core/correo/types";
import { useVentanaChat } from "@/features/chat/ventana";
import LectorCorreo from "./LectorCorreo.vue";
import ListaCorreos from "./ListaCorreos.vue";
import RedactorCorreo from "./RedactorCorreo.vue";

const props = defineProps<{ correo: number | null }>();
const emit = defineEmits<{ abrir: [id: number | null] }>();

const store = useCorreoStore();
const ventana = useVentanaChat();
/** Cambia para montar un redactor nuevo aunque el anterior no tuviera borrador. */
const claveRedactor = ref(0);

const abierto = computed(() => ventana.redactando || props.correo !== null);

onMounted(() => {
  if (!store.listo) void store.cargar();
  void store.refrescarEstado(true);
});

function contador(v: Vista): number {
  const c = store.estado?.carpetas;
  if (!c) return 0;
  if (v === "entrada") return c.entrada.noLeidos;
  if (v === "borradores") return c.borradores.total;
  if (v === "enviados") return c.enviados.fallidos;
  return 0;
}

function elegir(v: Vista) {
  store.ver(v);
  if (ventana.redactando) return;
  emit("abrir", null);
}

function abrir(id: number) {
  const fila = store.correos.find((c) => c.id === id);
  if (fila?.carpeta === "borradores") return editarBorrador(id);
  ventana.redactando = false;
  emit("abrir", id);
}

function redactar() {
  ventana.borrador = null;
  ventana.redactando = true;
  claveRedactor.value++;
}

function editarBorrador(id: number) {
  ventana.borrador = id;
  ventana.redactando = true;
  claveRedactor.value++;
}

async function responder(origen: Correo, modo: ModoRespuesta) {
  try {
    const b = await crearBorrador({ origen: origen.id, modo });
    editarBorrador(b.id);
    void store.refrescarEstado(true);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : "No se pudo preparar la respuesta.");
  }
}

function cerrarRedactor(guardado: boolean) {
  ventana.redactando = false;
  ventana.borrador = null;
  if (guardado) notify.info("Borrador guardado.", 2500);
  if (store.vista === "borradores") void store.cargar(true);
}

function alEnviar(enviado: Correo) {
  ventana.redactando = false;
  ventana.borrador = null;
  notify.success(enviado.envio === "pendiente" ? "Enviado. Saliendo hacia los destinatarios de fuera…" : "Enviado.", 3000);
  void store.cargar(true);
}
</script>

<style scoped>
.correo {
  container: correo / inline-size;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.correo__rejilla {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-height: 0;
  overflow: hidden;
  background: var(--p-content-background);
}
.carpetas,
.correo__panel,
.correo__portada {
  display: none;
}
.correo--abierto .correo__lista {
  display: none;
}
.correo--abierto .correo__panel {
  display: flex;
}
@media (prefers-reduced-motion: no-preference) {
  .correo--abierto .correo__panel {
    animation: correo-entra 0.22s ease-out;
  }
}
@keyframes correo-entra {
  from {
    transform: translateX(100%);
  }
}

@container correo (min-width: 40rem) {
  .correo--abierto .correo__panel {
    animation: none;
  }
  .correo__rejilla {
    grid-template-columns: minmax(17rem, 24rem) minmax(0, 1fr);
  }
  .correo__lista,
  .correo--abierto .correo__lista {
    display: flex;
    border-right: 1px solid var(--p-surface-200);
  }
  .correo__portada {
    display: flex;
  }
}
@container correo (min-width: 60rem) {
  .correo__rejilla {
    grid-template-columns: 12.5rem minmax(18rem, 25rem) minmax(0, 1fr);
  }
  .carpetas {
    display: flex;
  }
}

.carpetas {
  flex-direction: column;
  gap: 0.15rem;
  min-height: 0;
  padding: 0.75rem 0.6rem;
  overflow-y: auto;
  background: var(--p-surface-50);
  border-right: 1px solid var(--p-surface-200);
}
.redactar {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  margin: 0 0 0.75rem;
  padding: 0.7rem 1rem;
  border-radius: 1rem;
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--p-primary-800);
  background: color-mix(in srgb, var(--p-primary-color) 14%, var(--p-content-background));
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.06);
  cursor: pointer;
  transition: box-shadow 0.15s, transform 0.15s;
  &:hover {
    box-shadow: 0 4px 12px rgb(0 0 0 / 0.12);
  }
  &:active {
    transform: scale(0.98);
  }
}
.carpeta {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  height: 2.25rem;
  padding: 0 0.6rem 0 0.75rem;
  border-radius: 999px;
  font-size: 0.875rem;
  color: var(--p-surface-700);
  cursor: pointer;
  &:hover {
    background: var(--p-surface-100);
  }
}
.carpeta--activa {
  font-weight: 600;
  color: var(--p-primary-800);
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  &:hover {
    background: color-mix(in srgb, var(--p-primary-color) 16%, transparent);
  }
}
.carpeta__nombre {
  flex: 1;
  text-align: left;
}
.carpeta__n {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--p-surface-600);
}
.carpeta__n--alerta {
  color: var(--c-danger, #dc2626);
}
.carpetas__cuenta {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  margin: auto 0 0;
  padding: 0.75rem 0.5rem 0;
  overflow: hidden;
  font-size: 0.75rem;
  color: var(--p-surface-500);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.correo__panel {
  flex-direction: column;
  min-height: 0;
  min-width: 0;
}
.correo__portada {
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
  color: var(--p-surface-500);
  background: var(--p-surface-50);
  h2 {
    margin: 0;
    font-family: "Space Grotesk", sans-serif;
    font-size: 1.25rem;
    color: var(--p-surface-700);
  }
  p {
    max-width: 26rem;
    margin: 0 0 0.5rem;
    font-size: 0.9rem;
    line-height: 1.55;
  }
  strong {
    color: var(--p-surface-700);
  }
}
.portada__icono {
  display: grid;
  place-items: center;
  width: 4.5rem;
  height: 4.5rem;
  border-radius: 50%;
  color: var(--p-primary-700);
  background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
}
</style>
