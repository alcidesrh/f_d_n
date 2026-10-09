<!--
  Selector de registros para adjuntar al mensaje, sin buscar ids: a la
  izquierda los recursos que el usuario puede compartir (los decide el
  backend, `GET /api/chat/recursos`); al centro, el listado genérico de ese
  recurso (filtros, orden, páginas) en modo selección; a la derecha, lo
  elegido agrupado por recurso, como un carrito. Se puede saltar de página
  y de recurso sin perder nada. Aceptar devuelve la selección al redactor.
  Ocupa la pantalla; debajo de 60rem los tres paneles van en pestañas.
-->
<template>
  <Dialog :visible="visible" modal header="Adjuntar registros" class="selector-registros" :pt="{ content: { class: 'selector-registros__contenido' } }" @update:visible="emit('update:visible', $event)" @show="preparar">
    <div class="sel">
      <div class="sel__pestanas" role="tablist">
        <button v-for="p in PESTANAS" :key="p.id" type="button" role="tab" class="sel__pestana" :aria-selected="pestana === p.id" @click="pestana = p.id">
          {{ p.nombre }}<span v-if="p.id === 'carrito' && elegidos.length" class="sel__cuenta">{{ elegidos.length }}</span>
        </button>
      </div>

      <div class="sel__rejilla" :data-pestana="pestana">
        <nav class="sel__recursos" aria-label="Recursos">
          <label class="buscar">
            <icon name="search" size="1rem" color="text-current" />
            <input v-model="busqueda" type="search" placeholder="Buscar recurso" aria-label="Buscar recurso" />
          </label>
          <p v-if="cargando" class="sel__nota">Cargando…</p>
          <template v-for="g in grupos" v-else :key="g.nombre">
            <p v-if="g.recursos.length" class="sel__seccion">{{ g.nombre }}</p>
            <button v-for="r in g.recursos" :key="r.tipo" type="button" class="recurso" :aria-current="r.tipo === recurso" @click="elegirRecurso(r.tipo)">
              <icon :name="tipoTarjeta(r.tipo).icono" size="1.15rem" color="text-current" />
              <span class="recurso__nombre">{{ tipoTarjeta(r.tipo).nombre }}</span>
              <span v-if="cuantos(r.tipo)" class="sel__cuenta">{{ cuantos(r.tipo) }}</span>
            </button>
          </template>
          <p v-if="!cargando && !grupos.some((g) => g.recursos.length)" class="sel__nota">Sin resultados.</p>
        </nav>

        <section class="sel__lista">
          <template v-if="recurso && listado">
            <header class="sel__lista-cabeza">
              <icon :name="tipoTarjeta(recurso).icono" color="text-current" />
              <span class="font-semibold">{{ tipoTarjeta(recurso).nombre }}</span>
              <span class="text-xs text-muted-color">Marque los que quiera; puede cambiar de página, filtrar o pasar a otro recurso sin perderlos.</span>
            </header>
            <div class="sel__listado">
              <component :is="listado" :key="recurso" :entity="recurso" :seleccion="filasDe(recurso)" @update:seleccion="(filas: unknown[]) => alSeleccionar(recurso!, filas)" />
            </div>
          </template>
          <div v-else class="sel__vacio">
            <icon name="dataset-linked" size="2.5rem" color="text-current" />
            <p>Elija un recurso para ver su listado.</p>
          </div>
        </section>

        <aside class="sel__carrito" aria-label="Selección">
          <header class="sel__carrito-cabeza">
            <span class="font-semibold">Selección</span>
            <span class="text-xs" :class="elegidos.length >= MAXIMO ? 'text-red-500' : 'text-muted-color'">{{ elegidos.length }}/{{ MAXIMO }}</span>
            <button v-if="elegidos.length" type="button" class="sel__limpiar" @click="elegidos = []">Quitar todo</button>
          </header>
          <div v-if="!elegidos.length" class="sel__vacio sel__vacio--chico">
            <icon name="shopping-cart" size="2rem" color="text-current" />
            <p>Lo que marque aparece aquí, agrupado por recurso.</p>
          </div>
          <div v-for="g in carrito" :key="g.tipo" class="grupo">
            <button type="button" class="grupo__cabeza" @click="elegirRecurso(g.tipo)">
              <icon :name="tipoTarjeta(g.tipo).icono" size="1rem" color="text-current" />
              <span class="flex-1 text-left">{{ tipoTarjeta(g.tipo).nombre }}</span>
              <span class="sel__cuenta">{{ g.items.length }}</span>
            </button>
            <TransitionGroup tag="ul" name="item" class="grupo__items">
              <li v-for="e in g.items" :key="e.id" class="item">
                <span class="min-w-0 flex-1">
                  <span class="item__etiqueta">{{ e.etiqueta }}</span>
                  <span class="item__id">#{{ e.id }}</span>
                </span>
                <button type="button" class="item__quitar tap-target" :aria-label="`Quitar ${e.etiqueta}`" @click="quitar(e)"><icon name="close" size=".9rem" color="text-current" /></button>
              </li>
            </TransitionGroup>
          </div>
        </aside>
      </div>
    </div>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
      <Button :label="elegidos.length ? `Adjuntar ${elegidos.length}` : 'Adjuntar'" :disabled="!cambio" @click="aceptar">
        <template #icon><icon name="check" size="1rem" color="text-current" class="mr-1.5" /></template>
      </Button>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { useChatStore } from "@/core/chat/store";
import { coincide } from "@/core/chat/modelo";
import type { Recurso, Registro } from "@/core/chat/types";
import { useSchemaStore } from "@/core/entities/schema";
import { notify } from "@/core/notify";
import { LISTADO_DE_REGISTROS, etiquetaDeFila } from "@/shared/chat/integracion";
import { HABITUALES, tipoTarjeta } from "./tarjetas/catalogo";

const props = defineProps<{ visible: boolean; inicial: Registro[] }>();
const emit = defineEmits<{ "update:visible": [boolean]; aceptar: [registros: Registro[]] }>();

/** Lo que el backend admite por mensaje (`Tarjetas::MAXIMO`). */
const MAXIMO = 20;
const PESTANAS = [
  { id: "recursos", nombre: "Recursos" },
  { id: "lista", nombre: "Listado" },
  { id: "carrito", nombre: "Selección" },
] as const;

/** Lo elegido; `fila` (la del listado) solo si se marcó aquí. */
interface Elegido extends Registro {
  fila?: unknown;
}

const chat = useChatStore();
const schema = useSchemaStore();
const listado = inject(LISTADO_DE_REGISTROS, null);

const recursos = ref<Recurso[]>([]);
const cargando = ref(false);
const busqueda = ref("");
const recurso = ref<string | null>(null);
const elegidos = ref<Elegido[]>([]);
const pestana = ref<(typeof PESTANAS)[number]["id"]>("recursos");

const clave = (r: Registro) => `${r.tipo}:${r.id}`;
const cambio = computed(() => elegidos.value.map(clave).join() !== props.inicial.map(clave).join());

/** Solo los que tienen listado (colección en GraphQL). */
const grupos = computed(() => {
  const visibles = recursos.value.filter((r) => schema.find(r.tipo)?.queryCollection && (!busqueda.value || coincide(`${tipoTarjeta(r.tipo).nombre} ${r.tipo}`, busqueda.value)));
  const orden = (t: string) => HABITUALES.indexOf(t);
  const nombre = (r: Recurso) => tipoTarjeta(r.tipo).nombre;
  return [
    { nombre: "Habituales", recursos: visibles.filter((r) => orden(r.tipo) >= 0).sort((a, b) => orden(a.tipo) - orden(b.tipo)) },
    { nombre: "Todos", recursos: visibles.filter((r) => orden(r.tipo) < 0).sort((a, b) => nombre(a).localeCompare(nombre(b))) },
  ];
});

/** Agrupado por recurso, en el orden en que se eligió cada uno. */
const carrito = computed(() => {
  const grupos = new Map<string, Elegido[]>();
  for (const e of elegidos.value) grupos.set(e.tipo, [...(grupos.get(e.tipo) ?? []), e]);
  return [...grupos].map(([tipo, items]) => ({ tipo, items }));
});

const cuantos = (tipo: string) => elegidos.value.filter((e) => e.tipo === tipo).length;
/** Para el listado: la fila marcada aquí, o `{ id }` (lo que ya venía del redactor). */
const filasDe = (tipo: string) => elegidos.value.filter((e) => e.tipo === tipo).map((e) => e.fila ?? { id: e.id });

const idDe = (fila: unknown) => {
  const v = (fila as { id?: unknown }).id;
  const n = typeof v === "number" ? v : Number(String(v ?? "").match(/(\d+)$/)?.[1]);
  return Number.isInteger(n) && n > 0 ? n : null;
};

/** El listado devuelve todas las filas marcadas de ese recurso. */
function alSeleccionar(tipo: string, filas: unknown[]) {
  const previos = new Map(elegidos.value.filter((e) => e.tipo === tipo).map((e) => [e.id, e]));
  const otros = elegidos.value.filter((e) => e.tipo !== tipo);
  const nuevos: Elegido[] = [];
  for (const fila of filas) {
    const id = idDe(fila);
    if (id === null) continue;
    const previo = previos.get(id);
    if (!previo && otros.length + nuevos.length >= MAXIMO) {
      notify.warning(`Se pueden adjuntar hasta ${MAXIMO} registros por mensaje.`);
      break;
    }
    const soloId = Object.keys(fila as object).length <= 1;
    const etiqueta = soloId && previo ? previo.etiqueta : etiquetaDeFila(fila as Record<string, unknown>, id, tipoTarjeta(tipo).nombre);
    nuevos.push({ tipo, id, etiqueta, fila });
  }
  // Los demás recursos quedan donde estaban; este, en su lugar (o al final si es nuevo).
  const posicion = elegidos.value.findIndex((e) => e.tipo === tipo);
  elegidos.value = posicion < 0 ? [...otros, ...nuevos] : [...otros.slice(0, posicion), ...nuevos, ...otros.slice(posicion)];
}

function quitar(e: Elegido) {
  elegidos.value = elegidos.value.filter((x) => clave(x) !== clave(e));
}

function elegirRecurso(tipo: string) {
  recurso.value = tipo;
  pestana.value = "lista";
}

async function preparar() {
  elegidos.value = props.inicial.map((r) => ({ ...r }));
  busqueda.value = "";
  pestana.value = recurso.value ? "lista" : "recursos";
  if (recursos.value.length) return;
  cargando.value = true;
  try {
    recursos.value = await chat.cargarRecursos();
    recurso.value ??= props.inicial[0]?.tipo ?? grupos.value[0]?.recursos[0]?.tipo ?? null;
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  } finally {
    cargando.value = false;
  }
}

function aceptar() {
  emit(
    "aceptar",
    elegidos.value.map(({ tipo, id, etiqueta }) => ({ tipo, id, etiqueta })),
  );
  emit("update:visible", false);
}
</script>

<style>
/* El Dialog va a `body`: estilos sin scope, por clase propia. A pantalla completa. */
.p-dialog.selector-registros {
  width: 100vw;
  height: 100dvh;
  max-width: 100vw;
  max-height: 100dvh;
  margin: 0;
  border-radius: 0;
}
.selector-registros__contenido {
  flex: 1;
  min-height: 0;
  padding-bottom: 0 !important;
}
</style>

<style scoped>
.sel {
  container: selector / inline-size;
  display: flex;
  flex-direction: column;
  height: 100%;
  gap: 0.75rem;
}
.sel__pestanas {
  display: flex;
  gap: 0.25rem;
  padding: 0.25rem;
  border-radius: 0.75rem;
  background: var(--p-surface-100);
}
.sel__pestana {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  min-height: 2.25rem;
  border-radius: 0.55rem;
  font-size: 0.85rem;
  color: var(--p-surface-600);
  cursor: pointer;
  &[aria-selected="true"] {
    color: var(--p-surface-900);
    background: var(--p-content-background);
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
  }
}
.sel__rejilla {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
}
.sel__recursos,
.sel__lista,
.sel__carrito {
  display: none;
  min-height: 0;
  overflow-y: auto;
}
.sel__rejilla[data-pestana="recursos"] .sel__recursos,
.sel__rejilla[data-pestana="carrito"] .sel__carrito {
  display: block;
}
.sel__rejilla[data-pestana="lista"] .sel__lista {
  display: flex;
}
@container selector (min-width: 60rem) {
  .sel__pestanas {
    display: none;
  }
  .sel__rejilla {
    grid-template-columns: 13rem minmax(0, 1fr) 16rem;
    gap: 1rem;
  }
  .sel__rejilla .sel__recursos,
  .sel__rejilla .sel__carrito {
    display: block;
  }
  .sel__rejilla .sel__lista {
    display: flex;
  }
}

/* Recursos */
.buscar {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  height: 2.35rem;
  margin-bottom: 0.25rem;
  padding: 0 0.7rem;
  border-radius: 0.7rem;
  color: var(--p-surface-500);
  background: var(--p-surface-100);
  input {
    flex: 1;
    min-width: 0;
    border: 0;
    outline: 0;
    font-size: 0.85rem;
    color: var(--p-surface-800);
    background: transparent;
  }
}
.sel__seccion {
  margin: 0.75rem 0.5rem 0.25rem;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--p-surface-400);
}
.sel__nota {
  padding: 1rem 0.5rem;
  font-size: 0.85rem;
  color: var(--p-surface-500);
}
.recurso {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  min-height: 2.35rem;
  padding: 0 0.6rem;
  border-radius: 0.6rem;
  font-size: 0.875rem;
  text-align: left;
  color: var(--p-surface-700);
  cursor: pointer;
  &:hover {
    background: var(--p-surface-100);
  }
  &[aria-current="true"] {
    color: var(--p-primary-color);
    background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
    font-weight: 500;
  }
}
.recurso__nombre {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sel__cuenta {
  min-width: 1.25rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  line-height: 1.25rem;
  text-align: center;
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
}

/* Listado */
.sel__lista {
  flex-direction: column;
  gap: 0.5rem;
  overflow: hidden;
}
.sel__lista-cabeza {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.5rem;
  color: var(--p-surface-700);
}
.sel__listado {
  flex: 1;
  min-height: 0;
  overflow: auto;
}
.sel__vacio {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  height: 100%;
  padding: 2rem 1rem;
  text-align: center;
  font-size: 0.875rem;
  color: var(--p-surface-400);
  p {
    margin: 0;
  }
}
.sel__vacio--chico {
  height: auto;
}

/* Carrito */
.sel__carrito {
  padding: 0.5rem;
  border-radius: 0.9rem;
  background: var(--p-surface-50);
}
.sel__carrito-cabeza {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.35rem 0.5rem;
  color: var(--p-surface-800);
}
.sel__limpiar {
  margin-left: auto;
  font-size: 0.75rem;
  color: var(--p-surface-500);
  cursor: pointer;
  &:hover {
    color: var(--c-danger);
  }
}
.grupo + .grupo {
  margin-top: 0.5rem;
}
.grupo__cabeza {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  width: 100%;
  padding: 0.35rem;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--p-surface-700);
  cursor: pointer;
  &:hover {
    background: var(--p-surface-100);
  }
  background-color: red;
  display: none;
}
.grupo__items {
  margin: 0;
  padding: 0;
  list-style: none;
}
.item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0.2rem 0 0 0.35rem;
  padding: 0.3rem 0.25rem 0.3rem 0.65rem;
  border-radius: 0.55rem;
  background: var(--p-content-background);
  box-shadow: 0 0 0 1px var(--p-surface-200);
}
.item__etiqueta {
  display: block;
  overflow: hidden;
  font-size: 0.82rem;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--p-surface-800);
}
.item__id {
  font-size: 0.7rem;
  color: var(--p-surface-400);
}
.item__quitar {
  display: grid;
  place-items: center;
  min-width: 1.6rem;
  min-height: 1.6rem;
  border-radius: 50%;
  color: var(--p-surface-500);
  &:hover {
    color: var(--c-danger);
    background: var(--p-surface-100);
  }
}
.item-enter-active,
.item-leave-active {
  transition: all 0.2s var(--ease);
}
.item-enter-from,
.item-leave-to {
  opacity: 0;
  transform: translateX(0.75rem);
}
</style>
