<!--
  Lista del correo: búsqueda, filtros (no leídos, con adjuntos, de la casa o
  de fuera) y los correos de la vista. Las carpetas van aquí arriba cuando
  no caben a la izquierda (contenedor angosto). Destacar, marcar leído y
  mandar a la papelera están en la propia fila.
-->
<template>
  <section class="lista">
    <header class="lista__cabeza">
      <div class="vistas" role="tablist" aria-label="Carpetas">
        <button v-for="v in VISTAS" :key="v.vista" type="button" role="tab" class="vista" :class="{ 'vista--activa': store.vista === v.vista }" :aria-selected="store.vista === v.vista" @click="emit('elegir', v.vista)">
          <icon :name="v.icono" size="1rem" color="text-current" />
          <span>{{ v.nombre }}</span>
          <span v-if="v.vista === 'entrada' && store.noLeidos" class="vista__n">{{ store.noLeidos > 99 ? "99+" : store.noLeidos }}</span>
        </button>
      </div>
      <div class="fila-busqueda">
        <label class="buscar">
          <icon name="search" size="1.1rem" color="text-current" />
          <input v-model="busqueda" type="search" :placeholder="`Buscar en ${nombreVista.toLowerCase()}`" aria-label="Buscar correos" />
          <button v-if="busqueda" type="button" class="tap-target" aria-label="Limpiar búsqueda" @click="busqueda = ''"><icon name="close" size="1rem" color="text-current" /></button>
        </label>
        <button type="button" class="redactar-mini" aria-label="Redactar" v-tooltip.bottom="'Redactar'" @click="emit('redactar')"><icon name="edit-square-outline" size="1.2rem" color="text-current" /></button>
      </div>
      <div class="filtros" role="group" aria-label="Filtros">
        <button type="button" class="filtro" :class="{ 'filtro--on': store.filtros.noLeidos }" :aria-pressed="store.filtros.noLeidos" @click="store.filtrar({ noLeidos: !store.filtros.noLeidos })"><icon name="mark-email-unread-outline" size=".95rem" color="text-current" />No leídos</button>
        <button type="button" class="filtro" :class="{ 'filtro--on': store.filtros.adjuntos }" :aria-pressed="store.filtros.adjuntos" @click="store.filtrar({ adjuntos: !store.filtros.adjuntos })"><icon name="attach-file" size=".95rem" color="text-current" />Con adjuntos</button>
        <button type="button" class="filtro" :class="{ 'filtro--on': store.filtros.origen === 'internos' }" :aria-pressed="store.filtros.origen === 'internos'" @click="store.filtrar({ origen: store.filtros.origen === 'internos' ? null : 'internos' })"><icon name="corporate-fare" size=".95rem" color="text-current" />De la casa</button>
        <button type="button" class="filtro" :class="{ 'filtro--on': store.filtros.origen === 'externos' }" :aria-pressed="store.filtros.origen === 'externos'" @click="store.filtrar({ origen: store.filtros.origen === 'externos' ? null : 'externos' })"><icon name="public" size=".95rem" color="text-current" />De fuera</button>
      </div>
      <div v-if="store.vista === 'papelera' && store.correos.length" class="aviso-papelera">
        <span>Lo que está en la papelera se puede restaurar.</span>
        <button type="button" class="enlace" @click="vaciar">Vaciar papelera</button>
      </div>
    </header>

    <div ref="contenedor" class="lista__cuerpo" @scroll.passive="alDesplazar">
      <template v-if="!store.listo">
        <div v-for="n in 7" :key="n" class="fila fila--esqueleto">
          <Skeleton shape="circle" size="2.25rem" />
          <div class="flex-1"><Skeleton width="45%" class="mb-2" /><Skeleton width="80%" class="mb-1.5" /><Skeleton width="60%" /></div>
        </div>
      </template>

      <template v-else>
        <div v-for="c in store.correos" :key="c.id" class="fila" :class="{ 'fila--activa': c.id === activo, 'fila--nueva': !c.leido }" role="button" tabindex="0" @click="emit('abrir', c.id)" @keydown.enter="emit('abrir', c.id)">
          <ChatAvatar :id="tono(c.carpeta === 'entrada' ? c.de.direccion : (c.para[0]?.direccion ?? ''))" :nombre="c.carpeta === 'entrada' || c.carpeta === 'papelera' ? etiqueta(c.de) : (c.para[0] ? etiqueta(c.para[0]) : '?')" tamano="md" />
          <span class="fila__texto">
            <span class="fila__linea">
              <span class="fila__quien" :class="{ 'fila__quien--borrador': c.carpeta === 'borradores' }">
                <template v-if="c.carpeta === 'borradores'"><span class="borrador">Borrador</span> </template>{{ quien(c) }}
              </span>
              <span class="fila__fecha">{{ fechaLista(c.fecha) }}</span>
            </span>
            <span class="fila__linea">
              <span class="fila__asunto">{{ c.asunto || "(sin asunto)" }}</span>
              <span class="fila__marcas">
                <icon v-if="c.conAdjuntos" name="attach-file" size=".95rem" color="text-current" v-tooltip.bottom="'Con adjuntos'" />
                <span v-if="c.externo && c.carpeta !== 'borradores'" class="externo" v-tooltip.bottom="c.carpeta === 'enviados' ? 'Va también fuera de la empresa' : 'De fuera de la empresa'">Externo</span>
                <icon v-if="c.envio === 'pendiente'" name="schedule-send-outline" size=".95rem" color="text-current" v-tooltip.bottom="'Saliendo…'" />
                <icon v-if="c.envio === 'fallido'" name="error" size=".95rem" class="text-red-500" v-tooltip.bottom="'No se pudo entregar fuera'" />
              </span>
            </span>
            <span class="fila__extracto">{{ c.extracto || " " }}</span>
          </span>
          <span class="fila__acciones" @click.stop>
            <button v-if="c.carpeta !== 'borradores' && c.carpeta !== 'papelera'" type="button" class="accion accion--estrella" :class="{ 'accion--on': c.destacado }" :aria-label="c.destacado ? 'Quitar de destacados' : 'Destacar'" :aria-pressed="c.destacado" @click="accion(c.id, c.destacado ? 'quitarDestacado' : 'destacar')">
              <icon :name="c.destacado ? 'star' : 'star-outline'" size="1.1rem" color="text-current" />
            </button>
            <span class="fila__hover">
              <button v-if="c.carpeta === 'entrada'" type="button" class="accion" :aria-label="c.leido ? 'Marcar como no leído' : 'Marcar como leído'" v-tooltip.bottom="c.leido ? 'Marcar como no leído' : 'Marcar como leído'" @click="accion(c.id, c.leido ? 'noLeido' : 'leido')">
                <icon :name="c.leido ? 'mark-email-unread-outline' : 'drafts-outline'" size="1.05rem" color="text-current" />
              </button>
              <button v-if="c.carpeta === 'papelera'" type="button" class="accion" aria-label="Restaurar" v-tooltip.bottom="'Restaurar'" @click="accion(c.id, 'restaurar')">
                <icon name="restore-from-trash-outline" size="1.05rem" color="text-current" />
              </button>
              <button type="button" class="accion" :aria-label="c.carpeta === 'papelera' ? 'Eliminar para siempre' : c.carpeta === 'borradores' ? 'Descartar borrador' : 'A la papelera'" v-tooltip.bottom="c.carpeta === 'papelera' ? 'Eliminar para siempre' : c.carpeta === 'borradores' ? 'Descartar' : 'A la papelera'" @click="accion(c.id, c.carpeta === 'papelera' ? 'eliminar' : 'papelera')">
                <icon :name="c.carpeta === 'papelera' ? 'delete-forever-outline' : 'delete-outline'" size="1.05rem" color="text-current" />
              </button>
            </span>
          </span>
        </div>

        <div v-if="!store.correos.length" class="vacio">
          <icon :name="hayFiltros(store.filtros) ? 'search-off' : VACIOS[store.vista].icono" size="2.25rem" color="text-current" />
          <p>{{ hayFiltros(store.filtros) ? "Nada coincide con la búsqueda o los filtros." : VACIOS[store.vista].texto }}</p>
          <button v-if="hayFiltros(store.filtros)" type="button" class="enlace" @click="limpiar">Quitar filtros</button>
        </div>
        <button v-else-if="store.mas" type="button" class="mas" :disabled="store.cargando" @click="store.cargarMas()">{{ store.cargando ? "Cargando…" : "Ver más" }}</button>
      </template>
    </div>
  </section>
</template>

<script setup lang="ts">
import { useConfirm } from "primevue/useconfirm";
import { notify } from "@/core/notify";
import { SIN_FILTROS, VISTAS, etiqueta, fechaLista, hayFiltros, quien } from "@/core/correo/modelo";
import { useCorreoStore } from "@/core/correo/store";
import type { Accion, Vista } from "@/core/correo/types";
import ChatAvatar from "@/shared/chat/ChatAvatar.vue";

defineProps<{ activo: number | null }>();
const emit = defineEmits<{ abrir: [id: number]; redactar: []; elegir: [vista: Vista] }>();

const VACIOS: Record<Vista, { icono: string; texto: string }> = {
  entrada: { icono: "inbox-outline", texto: "No hay correos. Lo que le escriban llegará aquí." },
  destacados: { icono: "star-outline", texto: "Destaque con la estrella lo que quiera tener a mano." },
  enviados: { icono: "send-outline", texto: "Aún no ha enviado correos." },
  borradores: { icono: "draft-outline", texto: "Sin borradores. Lo que empiece a escribir se guarda aquí solo." },
  papelera: { icono: "delete-outline", texto: "La papelera está vacía." },
};

const store = useCorreoStore();
const confirm = useConfirm();
const contenedor = ref<HTMLElement | null>(null);
const busqueda = ref(store.filtros.q);
const nombreVista = computed(() => VISTAS.find((v) => v.vista === store.vista)?.nombre ?? "Correo");

let espera: ReturnType<typeof setTimeout> | undefined;
watch(busqueda, (q) => {
  clearTimeout(espera);
  espera = setTimeout(() => store.filtrar({ q }), 300);
});
watch(
  () => store.vista,
  () => contenedor.value?.scrollTo({ top: 0 }),
);
onUnmounted(() => clearTimeout(espera));

/** Un tono estable por dirección para el avatar. */
function tono(direccion: string): number {
  let h = 0;
  for (const c of direccion) h = (h * 31 + c.charCodeAt(0)) | 0;
  return Math.abs(h);
}

async function accion(id: number, a: Accion) {
  try {
    await store.aplicar([id], a);
    if (a === "papelera") notify.info("Enviado a la papelera.", 2500);
  } catch (e) {
    notify.error(e instanceof Error ? e.message : "No se pudo.");
  }
}

function limpiar() {
  busqueda.value = "";
  store.filtrar({ ...SIN_FILTROS });
}

function vaciar() {
  confirm.require({
    header: "Vaciar papelera",
    message: "Los correos de la papelera se eliminan para siempre.",
    acceptLabel: "Vaciar",
    rejectLabel: "Cancelar",
    acceptProps: { severity: "danger" },
    rejectProps: { severity: "secondary", text: true },
    accept: () => void store.vaciarPapelera().catch((e) => notify.error(e instanceof Error ? e.message : "No se pudo vaciar.")),
  });
}

/** Cerca del final carga la página siguiente. */
function alDesplazar() {
  const el = contenedor.value;
  if (el && store.mas && el.scrollTop + el.clientHeight > el.scrollHeight - 200) void store.cargarMas();
}
</script>

<style scoped>
.lista {
  container: lista / inline-size;
  display: flex;
  flex-direction: column;
  min-height: 0;
  min-width: 0;
}
.lista__cabeza {
  flex: none;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  padding: 0.7rem 0.75rem 0.6rem;
  border-bottom: 1px solid var(--p-surface-200);
}
.vistas {
  display: flex;
  gap: 0.25rem;
  margin: 0 -0.75rem;
  padding: 0 0.75rem 0.1rem;
  overflow-x: auto;
  scrollbar-width: none;
}
.vista {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  height: 1.9rem;
  padding: 0 0.7rem;
  border-radius: 999px;
  font-size: 0.8rem;
  color: var(--p-surface-600);
  cursor: pointer;
  &:hover {
    background: var(--p-surface-100);
  }
}
.vista--activa {
  font-weight: 600;
  color: var(--p-primary-800);
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
}
.vista__n {
  font-size: 0.7rem;
  font-weight: 700;
}
.fila-busqueda {
  display: flex;
  gap: 0.5rem;
}
.buscar {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  height: 2.3rem;
  padding: 0 0.6rem 0 0.75rem;
  border-radius: 999px;
  color: var(--p-surface-500);
  background: var(--p-surface-100);
  &:focus-within {
    background: var(--p-content-background);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--p-primary-color) 45%, transparent);
  }
  input {
    flex: 1;
    min-width: 0;
    border: 0;
    outline: 0;
    font-size: 0.875rem;
    color: var(--p-surface-800);
    background: transparent;
  }
  input::-webkit-search-cancel-button {
    display: none;
  }
}
.redactar-mini {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.3rem;
  height: 2.3rem;
  border-radius: 0.8rem;
  color: var(--p-primary-800);
  background: color-mix(in srgb, var(--p-primary-color) 14%, var(--p-content-background));
  cursor: pointer;
  &:hover {
    box-shadow: 0 3px 10px rgb(0 0 0 / 0.12);
  }
}
.filtros {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.filtro {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  height: 1.7rem;
  padding: 0 0.6rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
  font-size: 0.75rem;
  color: var(--p-surface-600);
  cursor: pointer;
  &:hover {
    border-color: var(--p-surface-300);
    color: var(--p-surface-800);
  }
}
.filtro--on {
  border-color: transparent;
  color: var(--p-primary-800);
  background: color-mix(in srgb, var(--p-primary-color) 14%, transparent);
  &:hover {
    border-color: transparent;
    color: var(--p-primary-900);
  }
}
.aviso-papelera {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.45rem 0.65rem;
  border-radius: 0.6rem;
  font-size: 0.75rem;
  color: var(--p-surface-600);
  background: var(--p-surface-100);
}
.enlace {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--p-primary-color);
  cursor: pointer;
  &:hover {
    text-decoration: underline;
  }
}

.lista__cuerpo {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}
.fila {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
  padding: 0.7rem 0.6rem 0.7rem 0.85rem;
  border-bottom: 1px solid var(--p-surface-100);
  cursor: pointer;
  outline: none;
  &:hover {
    background: var(--p-surface-50);
  }
  &:focus-visible {
    box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--p-primary-color) 50%, transparent);
  }
}
.fila--esqueleto {
  cursor: default;
}
.fila--activa,
.fila--activa:hover {
  background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
}
.fila--nueva::before {
  content: "";
  position: absolute;
  top: 50%;
  left: 0.3rem;
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 50%;
  background: var(--p-primary-color);
  transform: translateY(-50%);
}
.fila__texto {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}
.fila__linea {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  min-width: 0;
}
.fila__quien,
.fila__asunto,
.fila__extracto {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fila__quien {
  flex: 1;
  font-size: 0.875rem;
  color: var(--p-surface-700);
}
.fila__quien--borrador {
  color: var(--p-surface-600);
}
.borrador {
  color: var(--c-danger, #dc2626);
  font-weight: 500;
}
.fila__fecha {
  flex: none;
  font-size: 0.75rem;
  color: var(--p-surface-500);
}
.fila__asunto {
  flex: 1;
  font-size: 0.85rem;
  color: var(--p-surface-700);
}
.fila__extracto {
  font-size: 0.8rem;
  color: var(--p-surface-500);
}
.fila--nueva {
  .fila__quien,
  .fila__asunto {
    font-weight: 650;
    color: var(--p-surface-900);
  }
  .fila__fecha {
    font-weight: 600;
    color: var(--p-primary-700);
  }
}
.fila__marcas {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  color: var(--p-surface-500);
}
.externo {
  padding: 0 0.35rem;
  border-radius: 0.3rem;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.1rem;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #9a3412;
  background: #ffedd5;
}
.fila__acciones {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.15rem;
}
/* Las acciones de la fila asoman sobre el extracto al pasar el mouse (no estiran la fila). */
.fila__hover {
  position: absolute;
  right: 0.5rem;
  bottom: 0.4rem;
  display: flex;
  gap: 0.1rem;
  padding-left: 1.5rem;
  background: linear-gradient(to right, transparent, var(--p-surface-50) 1.5rem);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.12s;
}
.fila--activa .fila__hover {
  background: linear-gradient(to right, transparent, color-mix(in srgb, var(--p-primary-color) 8%, var(--p-content-background)) 1.5rem);
}
.fila:hover .fila__hover,
.fila:focus-within .fila__hover {
  opacity: 1;
  pointer-events: auto;
}
@media (hover: none) {
  .fila__hover {
    display: none;
  }
}
.accion {
  display: grid;
  place-items: center;
  width: 1.85rem;
  height: 1.85rem;
  border-radius: 0.5rem;
  color: var(--p-surface-400);
  cursor: pointer;
  &:hover {
    color: var(--p-surface-800);
    background: var(--p-surface-200);
  }
}
.accion--estrella:hover,
.accion--on {
  color: #f59e0b;
}
.vacio {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
  padding: 3rem 1.5rem;
  text-align: center;
  color: var(--p-surface-400);
  p {
    max-width: 18rem;
    margin: 0;
    font-size: 0.85rem;
    line-height: 1.5;
    color: var(--p-surface-500);
  }
}
.mas {
  display: block;
  width: 100%;
  padding: 0.85rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--p-primary-color);
  cursor: pointer;
  &:disabled {
    color: var(--p-surface-400);
    cursor: default;
  }
}
/* Con la columna de carpetas a la izquierda (contenedor ancho), sobran aquí. */
@container correo (min-width: 60rem) {
  .vistas,
  .redactar-mini {
    display: none;
  }
}
</style>
