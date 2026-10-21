<!--
  Administración (`/administracion`): el "Administrar" del legado (cajas con
  "Agregar nuevo" y "Listar"), en un muro de cajas que ocupa el
  ancho disponible. Las cajas y sus enlaces viven en la base
  (`/api/administracion`): cada usuario ve las que le tocan por roles y
  usuarios. Con `config.editar`, "Personalizar" crea, edita, oculta, ordena
  (arrastrando), pasa de caja y elimina cajas y enlaces, y decide quién ve
  cada cosa; todo se guarda al momento. Reglas puras en `core/administracion/`.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
      <IconField v-if="!store.configurando" class="min-w-0 flex-1 @xl:max-w-sm">
        <InputIcon><icon name="search" /></InputIcon>
        <InputText ref="buscador" v-model="busqueda" placeholder="Buscar (Bus, tarifas, nacionalidades…)" aria-label="Buscar en la administración" fluid @keydown.enter="abrirPrimera" @keydown.esc="busqueda = ''" />
      </IconField>
      <span v-else class="min-w-0 flex-1 text-sm text-muted-color">
        Arrastrá <icon name="drag-indicator" sm class="align-text-bottom" /> para ordenar; <icon name="visibility-outline" sm class="align-text-bottom" /> muestra u oculta, <icon name="edit-outline" sm class="align-text-bottom" /> edita y <icon name="more-vert" sm class="align-text-bottom" /> pasa a otra caja o elimina. Se guarda al momento.
      </span>
      <span v-if="!store.configurando" class="hidden text-sm text-muted-color @lg:inline">{{ resumen }}</span>
      <div class="ml-auto flex items-center gap-2">
        <template v-if="store.configurando">
          <Button label="Nueva caja" severity="secondary" outlined size="small" @click="abrirSeccion(null)">
            <template #icon><icon name="add-box-outline" /></template>
          </Button>
          <Button label="Listo" size="small" @click="store.configurar(false)">
            <template #icon><icon name="check" class="text-inherit" /></template>
          </Button>
        </template>
        <Button v-else-if="store.puedeConfigurar" label="Personalizar" severity="secondary" outlined size="small" @click="personalizar">
          <template #icon><icon name="tune" /></template>
        </Button>
      </div>
    </div>

    <Message v-if="store.error" severity="error" :closable="false">{{ store.error }}</Message>
    <div v-if="store.cargando && !store.secciones.length" class="grid gap-4 @2xl:grid-cols-3">
      <Skeleton v-for="i in 3" :key="i" height="12rem" />
    </div>

    <div ref="muro" class="muro" :style="{ gridTemplateColumns: `repeat(${columnas.length}, minmax(0, 1fr))` }">
      <div v-for="(columna, c) in columnas" :key="c" class="flex min-w-0 flex-col gap-4">
        <section v-for="s in columna" :key="s.id" class="caja" :class="{ 'caja--apagada': store.configurando && !s.activa }" :aria-labelledby="`caja-${s.id}`">
          <header class="caja-cabecera">
            <span class="caja-icono"><icon :name="s.icono" sm color="text-inherit" /></span>
            <h2 :id="`caja-${s.id}`" class="min-w-0 flex-1 truncate">{{ s.titulo }}</h2>
            <template v-if="store.configurando">
              <icon v-if="restringido(s.acceso)" name="lock-outline" sm class="caja-marca" v-tooltip.top="quienVe(s.acceso)" />
              <Button text rounded size="small" severity="secondary" class="tap-target" :aria-label="s.activa ? `Ocultar la caja ${s.titulo}` : `Mostrar la caja ${s.titulo}`" v-tooltip.top="s.activa ? 'Ocultar caja' : 'Mostrar caja'" @click="alternarSeccion(s)">
                <template #icon><icon :name="s.activa ? 'visibility-outline' : 'visibility-off-outline'" sm /></template>
              </Button>
              <Button text rounded size="small" severity="secondary" class="tap-target" :aria-label="`Editar la caja ${s.titulo}`" v-tooltip.top="'Editar caja'" @click="abrirSeccion(s)">
                <template #icon><icon name="edit-outline" sm /></template>
              </Button>
              <Button text rounded size="small" severity="secondary" class="tap-target" :aria-label="`Más acciones de la caja ${s.titulo}`" @click="menuSeccion($event, s)">
                <template #icon><icon name="more-vert" sm /></template>
              </Button>
            </template>
            <span v-else class="caja-cuenta">{{ s.enlaces.length }}</span>
          </header>

          <template v-if="store.configurando">
            <SortablePanelList v-if="s.enlaces.length" :model-value="filas(s)" :row-height="FILA" :gap="4" class="mx-2 mt-2" @update:model-value="(f) => store.ordenar(ordenarEnlaces(store.secciones, s.id, f.map((x) => Number(x.key))))">
              <template #row="{ row }">
                <div v-if="enlacePorId(row.key)" class="fila" :class="{ 'fila--oculta': !enlacePorId(row.key)!.activo }">
                  <span data-drag-handle class="tirador" aria-hidden="true"><icon name="drag-indicator" sm /></span>
                  <icon :name="enlacePorId(row.key)!.icono" sm class="fila-icono" />
                  <span class="min-w-0 flex-1 truncate" v-tooltip.top="describirDestino(enlacePorId(row.key)!.abrir)">{{ enlacePorId(row.key)!.etiqueta }}</span>
                  <icon v-if="!ubicacion(enlacePorId(row.key)!.abrir, existeRuta)" name="link-off" sm class="text-red-600" v-tooltip.top="'Su destino ya no existe'" />
                  <icon v-if="restringido(enlacePorId(row.key)!.acceso)" name="lock-outline" sm class="text-muted-color" v-tooltip.top="quienVe(enlacePorId(row.key)!.acceso)" />
                  <Button text rounded size="small" severity="secondary" class="tap-target" :aria-label="enlacePorId(row.key)!.activo ? `Ocultar ${enlacePorId(row.key)!.etiqueta}` : `Mostrar ${enlacePorId(row.key)!.etiqueta}`" @click="alternarEnlace(enlacePorId(row.key)!)">
                    <template #icon><icon :name="enlacePorId(row.key)!.activo ? 'visibility-outline' : 'visibility-off-outline'" sm /></template>
                  </Button>
                  <Button text rounded size="small" severity="secondary" class="tap-target" :aria-label="`Editar ${enlacePorId(row.key)!.etiqueta}`" @click="abrirEnlace(s, enlacePorId(row.key)!)">
                    <template #icon><icon name="edit-outline" sm /></template>
                  </Button>
                  <Button text rounded size="small" severity="secondary" class="tap-target" :aria-label="`Más acciones de ${enlacePorId(row.key)!.etiqueta}`" @click="menuEnlace($event, s, enlacePorId(row.key)!)">
                    <template #icon><icon name="more-vert" sm /></template>
                  </Button>
                </div>
              </template>
            </SortablePanelList>
            <button type="button" class="caja-agregar" @click="abrirEnlace(s, null)"><icon name="add" sm color="text-inherit" /> Agregar enlace</button>
          </template>

          <ul v-else class="enlaces">
            <li v-for="e in s.enlaces" :key="e.id" class="enlace">
              <component :is="vinculo(e.abrir).is" v-bind="vinculo(e.abrir).attrs" class="enlace-abrir" :class="{ 'enlace-abrir--roto': !vinculo(e.abrir).ok }">
                <icon :name="e.icono" sm class="enlace-icono" />
                <span class="min-w-0 flex-1 truncate">{{ e.etiqueta }}</span>
                <icon :name="vinculo(e.abrir).externo ? 'open-in-new' : 'chevron-right'" sm class="enlace-flecha" />
              </component>
              <component :is="vinculo(e.agregar).is" v-if="e.agregar && vinculo(e.agregar).ok" v-bind="vinculo(e.agregar).attrs" class="enlace-agregar tap-target" :aria-label="`Agregar en ${e.etiqueta}`" v-tooltip.left="'Agregar nuevo'">
                <icon name="add" sm color="text-inherit" />
              </component>
            </li>
          </ul>
        </section>
      </div>
    </div>

    <div v-if="!store.cargando && !secciones.length && !store.error" class="flex flex-col items-center gap-2 p-6 text-center text-muted-color">
      <icon :name="busqueda ? 'search-off' : 'dashboard-customize-outline'" size="2rem" />
      <span>{{ busqueda ? `Nada coincide con «${busqueda}».` : store.configurando ? "No hay cajas todavía." : "No tenés cajas de administración a la vista." }}</span>
      <Button v-if="store.configurando" label="Nueva caja" size="small" text @click="abrirSeccion(null)" />
    </div>

    <Menu ref="menu" :model="opcionesMenu" popup>
      <template #item="{ item, props: p }">
        <a v-bind="p.action" class="flex items-center gap-2.5" :class="{ 'opacity-60': item.disabled }">
          <icon :name="String(item.icono)" size="1.15rem" :class="item.peligro ? 'text-red-600' : ''" />
          <span class="flex-1 text-sm" :class="item.peligro ? 'text-red-600' : ''">{{ item.label }}</span>
        </a>
      </template>
    </Menu>

    <SeccionDialog v-model:visible="dialogoSeccion" :inicial="seccionEditada" :guardando="guardando" @guardar="guardarSeccion" />
    <EnlaceDialog v-model:visible="dialogoEnlace" :inicial="enlaceEditado" :caja="cajaDelEnlace?.titulo ?? ''" :guardando="guardando" @guardar="guardarEnlace" />
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from "primevue/useconfirm";
import { RouterLink } from "vue-router";
import type { Acceso, Destino, Enlace, EnlacePayload, Seccion, SeccionPayload } from "@/core/administracion/api";
import { describirDestino, enlacePayload, moverEnlace, moverSeccion, ordenarEnlaces, repartirEnColumnas, restringido, seccionesVisibles, seccionPayload, ubicacion } from "@/core/administracion/disposicion";
import SortablePanelList from "@/shared/ui/SortablePanelList.vue";
import EnlaceDialog from "./EnlaceDialog.vue";
import SeccionDialog from "./SeccionDialog.vue";
import { useAdministracionStore } from "./store";

defineOptions({ name: "AdministracionPage" });

/** Alto de una fila al configurar (px, incluye el `gap`). */
const FILA = 44;
/** Ancho mínimo de una columna del muro (px) y separación entre columnas. */
const ANCHO_COLUMNA = 280;
const SEPARACION = 16;
const MAX_COLUMNAS = 5;

const store = useAdministracionStore();
const router = useRouter();
const confirm = useConfirm();

const busqueda = ref("");
onMounted(() => void store.cargar());

/** Configurando, todas tal cual; si no, las visibles que coinciden con la búsqueda. */
const secciones = computed<Seccion[]>(() => (store.configurando ? store.secciones : seccionesVisibles(store.secciones, busqueda.value)));

const resumen = computed(() => {
  const n = secciones.value.reduce((t, s) => t + s.enlaces.length, 0);
  return `${n} ${n === 1 ? "enlace" : "enlaces"} en ${secciones.value.length} ${secciones.value.length === 1 ? "caja" : "cajas"}`;
});

function personalizar() {
  busqueda.value = "";
  void store.configurar(true);
}

const quienVe = (a: Acceso | undefined) => (a ? ["Solo:", ...a.roles.map((r) => r.label), ...a.usuarios.map((u) => u.label)].join(" ") : "");

// ── Enlaces: destinos ─────────────────────────────────────────────────────────

const existeRuta = (nombre: string) => router.hasRoute(nombre);

/** Elemento y atributos para un destino: RouterLink, enlace externo o texto inerte si ya no existe. */
function vinculo(d: Destino | null) {
  const u = ubicacion(d, existeRuta);
  if (!u) return { is: "span", attrs: { "aria-disabled": "true", title: "Su destino ya no existe" }, ok: false, externo: false };
  if (u.tipo === "externa") return { is: "a", attrs: { href: u.href, target: "_blank", rel: "noopener noreferrer" }, ok: true, externo: true };
  return { is: RouterLink, attrs: { to: u.to }, ok: true, externo: false };
}

/** Enter en el buscador abre la primera coincidencia. */
function abrirPrimera() {
  const primera = secciones.value[0]?.enlaces[0];
  const u = primera && busqueda.value.trim() ? ubicacion(primera.abrir, existeRuta) : null;
  if (u?.tipo === "externa") window.open(u.href, "_blank", "noopener");
  else if (u) void router.push(u.to);
}

// ── Configurar: filas, visibilidad, orden ────────────────────────────────────

/**
 * Filas de `SortablePanelList`: solo la clave. La lista conserva sus filas
 * mientras no cambie el orden, así que el contenido se lee siempre del
 * estado actual por id (si no, el ojo no cambiaba hasta salir y volver).
 */
const filas = (s: Seccion) => s.enlaces.map((e) => ({ key: String(e.id) }));
const enlaces = computed(() => new Map(store.secciones.flatMap((s) => s.enlaces.map((e) => [String(e.id), e] as const))));
const enlacePorId = (key: string) => enlaces.value.get(key);

/** Cambia el enlace en pantalla al instante y lo guarda (el servidor responde la configuración). */
async function alternarEnlace(e: Enlace) {
  const activo = !e.activo;
  store.secciones = store.secciones.map((s) => ({ ...s, enlaces: s.enlaces.map((x) => (x.id === e.id ? { ...x, activo } : x)) }));
  if (!(await store.guardarEnlace(e.id, enlacePayload(e, { activo })))) await store.cargar();
}

async function alternarSeccion(s: Seccion) {
  const activa = !s.activa;
  store.secciones = store.secciones.map((x) => (x.id === s.id ? { ...x, activa } : x));
  if (!(await store.guardarSeccion(s.id, seccionPayload(s, { activa })))) await store.cargar();
}

// ── Menús de acciones ────────────────────────────────────────────────────────

const menu = ref<{ toggle: (e: Event) => void } | null>(null);
const opcionesMenu = ref<Record<string, unknown>[]>([]);

function menuSeccion(evento: Event, s: Seccion) {
  const i = store.secciones.findIndex((x) => x.id === s.id);
  opcionesMenu.value = [
    { label: "Mover antes", icono: "arrow-upward", disabled: i === 0, command: () => store.ordenar(moverSeccion(store.secciones, s.id, -1)) },
    { label: "Mover después", icono: "arrow-downward", disabled: i === store.secciones.length - 1, command: () => store.ordenar(moverSeccion(store.secciones, s.id, 1)) },
    { separator: true },
    { label: "Eliminar caja", icono: "delete-outline", peligro: true, command: () => eliminarSeccion(s) },
  ];
  menu.value?.toggle(evento);
}

function menuEnlace(evento: Event, s: Seccion, e: Enlace) {
  const otras = store.secciones.filter((x) => x.id !== s.id);
  opcionesMenu.value = [
    ...(otras.length ? [{ label: "Pasar a la caja", items: otras.map((x) => ({ label: x.titulo, icono: "drive-file-move-outline", command: () => store.ordenar(moverEnlace(store.secciones, e.id, x.id)) })) }] : []),
    { separator: true },
    { label: "Eliminar enlace", icono: "delete-outline", peligro: true, command: () => eliminarEnlace(e) },
  ];
  menu.value?.toggle(evento);
}

function eliminarSeccion(s: Seccion) {
  confirm.require({
    header: "Eliminar caja",
    message: s.enlaces.length ? `¿Eliminar «${s.titulo}» y sus ${s.enlaces.length} enlaces? No se puede deshacer.` : `¿Eliminar «${s.titulo}»?`,
    acceptLabel: "Eliminar",
    rejectLabel: "Cancelar",
    acceptProps: { severity: "danger" },
    accept: () => void store.eliminarSeccion(s.id),
  });
}

function eliminarEnlace(e: Enlace) {
  confirm.require({
    header: "Eliminar enlace",
    message: `¿Eliminar «${e.etiqueta}»?`,
    acceptLabel: "Eliminar",
    rejectLabel: "Cancelar",
    acceptProps: { severity: "danger" },
    accept: () => void store.eliminarEnlace(e.id),
  });
}

// ── Diálogos ─────────────────────────────────────────────────────────────────

const guardando = ref(false);
const dialogoSeccion = ref(false);
const seccionEditada = ref<Seccion | null>(null);
const dialogoEnlace = ref(false);
const enlaceEditado = ref<Enlace | null>(null);
const cajaDelEnlace = ref<Seccion | null>(null);

function abrirSeccion(s: Seccion | null) {
  seccionEditada.value = s;
  dialogoSeccion.value = true;
}

function abrirEnlace(s: Seccion, e: Enlace | null) {
  cajaDelEnlace.value = s;
  enlaceEditado.value = e;
  dialogoEnlace.value = true;
}

async function conGuardando(operacion: () => Promise<boolean>, cerrar: () => void) {
  guardando.value = true;
  try {
    if (await operacion()) cerrar();
  } finally {
    guardando.value = false;
  }
}

function guardarSeccion(p: SeccionPayload) {
  const s = seccionEditada.value;
  void conGuardando(() => (s ? store.guardarSeccion(s.id, p) : store.crearSeccion(p)), () => (dialogoSeccion.value = false));
}

function guardarEnlace(p: EnlacePayload) {
  const e = enlaceEditado.value;
  const caja = cajaDelEnlace.value;
  if (!caja) return;
  void conGuardando(() => (e ? store.guardarEnlace(e.id, p) : store.crearEnlace(caja.id, p)), () => (dialogoEnlace.value = false));
}

// ── Muro: columnas según el ancho del contenedor ──────────────────────────────

const muro = ref<HTMLElement | null>(null);
const ancho = ref(0);
let observador: ResizeObserver | null = null;
onMounted(() => {
  if (!muro.value) return;
  observador = new ResizeObserver(([e]) => (ancho.value = e?.contentRect.width ?? 0));
  observador.observe(muro.value);
});
onBeforeUnmount(() => observador?.disconnect());

const cantidadColumnas = computed(() => Math.min(MAX_COLUMNAS, Math.max(1, Math.floor((ancho.value + SEPARACION) / (ANCHO_COLUMNA + SEPARACION)))));
/** Cabecera ≈ 1.5 filas (+1 al configurar, el "Agregar enlace"); cada caja a la columna más baja. */
const columnas = computed(() => repartirEnColumnas(secciones.value, (s) => (store.configurando ? 3 : 1.5) + s.enlaces.length, cantidadColumnas.value));

// "/" lleva al buscador desde cualquier parte de la página.
const buscador = ref<{ $el: HTMLElement } | null>(null);
function atajo(e: KeyboardEvent) {
  const destino = e.target as HTMLElement | null;
  if (e.key !== "/" || store.configurando || destino?.closest("input, textarea, [contenteditable]")) return;
  e.preventDefault();
  buscador.value?.$el.focus();
}
onMounted(() => window.addEventListener("keydown", atajo));
onBeforeUnmount(() => window.removeEventListener("keydown", atajo));
</script>

<style scoped>
.muro {
  display: grid;
  gap: 1rem;
  align-items: start;
}

/* Cajas en la rampa de superficie (`--p-surface-*`): neutras, en ambos modos. */
.caja {
  background: var(--p-content-background);
  border: 1px solid var(--p-surface-200);
  border-top: 4px solid var(--p-surface-500);
  border-radius: var(--p-content-border-radius, 0.75rem);
  box-shadow: 0 1px 2px color-mix(in srgb, var(--p-surface-900) 8%, transparent), 0 4px 12px -6px color-mix(in srgb, var(--p-surface-900) 18%, transparent);
  overflow: hidden;
  padding-bottom: 0.25rem;
  transition: opacity 0.15s;
}
.caja--apagada {
  opacity: 0.55;
  border-style: dashed;
  border-top-style: solid;
}
.caja-cabecera {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-height: 3rem;
  padding: 0.5rem 0.5rem 0.5rem 0.75rem;
  background: var(--p-surface-50);
  border-bottom: 1px solid var(--p-surface-200);
  color: var(--p-surface-900);
}
.caja-cabecera h2 {
  font-size: 0.9375rem;
  font-weight: 600;
}
.caja-icono {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  flex: none;
  border-radius: 0.5rem;
  background: var(--p-surface-500);
  color: var(--p-surface-50);
}
.caja-cuenta {
  min-width: 1.5rem;
  padding: 0.0625rem 0.5rem;
  border-radius: 999px;
  background: var(--p-surface-100);
  color: var(--p-surface-800);
  font-size: 0.75rem;
  font-weight: 600;
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.caja-marca {
  color: var(--p-surface-700);
}
.caja-agregar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  width: calc(100% - 1rem);
  margin: 0.5rem 0.5rem 0.25rem;
  min-height: 2.25rem;
  border: 1px dashed var(--p-surface-300);
  border-radius: 0.5rem;
  color: var(--p-surface-700);
  font-size: 0.875rem;
}
.caja-agregar:hover,
.caja-agregar:focus-visible {
  background: var(--p-surface-50);
  border-style: solid;
}

.enlaces {
  padding: 0.25rem 0.25rem 0;
}
.enlace {
  display: flex;
  align-items: center;
  gap: 0.125rem;
  border-radius: 0.5rem;
}
.enlace + .enlace {
  border-top: 1px solid color-mix(in srgb, var(--p-surface-200) 60%, transparent);
}
.enlace:hover,
.enlace:focus-within {
  background: var(--p-surface-50);
}
.enlace-abrir {
  display: flex;
  flex: 1;
  min-width: 0;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  color: inherit;
  text-decoration: none;
}
.enlace-abrir--roto {
  color: var(--p-text-muted-color);
  text-decoration: line-through;
  cursor: not-allowed;
}
.enlace-icono {
  color: var(--p-surface-600);
}
.enlace-abrir:focus-visible,
.enlace-agregar:focus-visible {
  outline: 2px solid var(--p-surface-500);
  outline-offset: -2px;
}
.enlace-flecha {
  opacity: 0;
  color: var(--p-surface-600);
  transition: opacity 0.15s;
}
.enlace:hover .enlace-flecha,
.enlace-abrir:focus-visible .enlace-flecha {
  opacity: 1;
}
.enlace-agregar {
  display: grid;
  place-items: center;
  flex: none;
  width: 2rem;
  height: 2rem;
  margin-inline-end: 0.125rem;
  border-radius: 0.5rem;
  color: var(--p-surface-700);
}
.enlace-agregar:hover {
  background: var(--p-surface-100);
}

.fila {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  height: 100%;
  padding-inline: 0.25rem 0.125rem;
  border-radius: 0.5rem;
  border: 1px solid var(--p-surface-200);
  background: var(--p-content-background);
  font-size: 0.875rem;
}
.fila-icono {
  color: var(--p-surface-600);
}
.fila--oculta {
  color: var(--p-text-muted-color);
  background: var(--p-surface-50);
  border-style: dashed;
}
.fila--oculta .fila-icono {
  color: inherit;
}
.tirador {
  display: grid;
  place-items: center;
  cursor: grab;
  touch-action: none;
  padding: 0.25rem 0;
  color: var(--p-text-muted-color);
}
</style>
