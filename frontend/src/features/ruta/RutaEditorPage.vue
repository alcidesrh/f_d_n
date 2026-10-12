<!--
  Editor de una ruta (ADR-030): nombre, km, activa y sus paradas en orden.
  Cada parada: lugar, minutos y km desde el origen, si ahí se sube y se baja,
  y minutos por clase de bus cuando difieren. Se reordena arrastrando el
  tirador o con las flechas. Los minutos que retroceden se avisan pero no
  impiden guardar. Al guardar, las salidas futuras sin boletos toman las
  paradas nuevas; las que tienen boletos conservan las suyas.
  `/rutas/nueva` crea; `/rutas/:id` edita. Mapa al lado desde 80rem de ancho.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
      <RouterLink :to="{ name: 'rutas' }" class="no-underline">
        <Button label="Rutas" severity="secondary" text size="small"
          ><template #icon><icon name="arrow-back" class="mr-1" /></template
        ></Button>
      </RouterLink>
      <span v-if="detalle?.codigo" class="text-xs text-muted-color">Código del legado: {{ detalle.codigo }}</span>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-if="!form" height="20rem" />

    <div v-else class="grid gap-4 @5xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <div class="flex flex-col gap-4 min-w-0">
        <section class="panel flex flex-col gap-3" aria-labelledby="ruta-datos">
          <h2 id="ruta-datos" class="m-0 flex items-center gap-2 text-base font-semibold"><icon name="route" />Ruta</h2>
          <div class="grid gap-3 @xl:grid-cols-[minmax(0,1fr)_8rem_auto] @xl:items-end">
            <label class="flex flex-col gap-1">
              <span class="text-xs font-medium text-muted-color">Nombre</span>
              <InputText v-model="form.nombre" maxlength="255" :disabled="!puedeEditar" placeholder="P. ej. Guatemala - Santa Elena por Río Dulce" fluid />
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-xs font-medium text-muted-color">Km totales</span>
              <InputNumber v-model="form.kilometros" :min="0" :disabled="!puedeEditar" :use-grouping="false" fluid />
            </label>
            <label class="flex items-center gap-2 text-sm pb-2">
              <ToggleSwitch v-model="form.activo" :disabled="!puedeEditar" input-id="ruta-activa" />
              Activa
            </label>
          </div>
          <p v-if="detalle?.salidasFuturas" class="m-0 text-xs text-muted-color">
            {{ detalle.salidasFuturas }} salidas programadas próximas.
            <template v-if="detalle.salidasConBoletos">Al guardar, {{ detalle.salidasConBoletos }} con boletos conservan sus paradas (solo cambian sus horas); las demás toman las nuevas.</template>
            <template v-else>Al guardar, toman las paradas nuevas.</template>
          </p>
        </section>

        <section class="panel flex flex-col gap-3" aria-labelledby="ruta-paradas">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="ruta-paradas" class="m-0 flex items-center gap-2 text-base font-semibold">
              <icon name="pin-drop" />Paradas <span class="text-sm font-normal text-muted-color">({{ form.paradas.length }})</span>
            </h2>
            <span class="text-xs text-muted-color">Minutos y km desde el origen</span>
          </div>

          <div ref="listaEl">
            <SortablePanelList :key="modo" v-model="form.paradas" :row-height="alto" :gap="8" :disabled="!puedeEditar">
              <template #row="{ row, index }">
                <div class="parada" :class="[`parada--${modo}`, { 'parada--aviso': avisosForm[index] }]">
                  <button type="button" data-drag-handle class="tirador" :aria-label="`Arrastrar la parada ${index + 1}`" :disabled="!puedeEditar"><icon name="drag-indicator" /></button>
                  <span class="numero tabular-nums" :title="index === 0 ? 'Origen' : index === form.paradas.length - 1 ? 'Destino' : `Parada ${index + 1}`">{{ index + 1 }}</span>
                  <Select v-model="row.enclaveId" :options="opciones?.lugares ?? []" option-label="nombre" option-value="id" filter :filter-fields="['nombre', 'departamento']" :virtual-scroller-options="{ itemSize: 38 }" placeholder="Lugar" :disabled="!puedeEditar" class="lugar" :aria-label="`Lugar de la parada ${index + 1}`" />
                  <InputNumber v-model="row.minutos" :min="0" :use-grouping="false" :disabled="!puedeEditar || index === 0" :placeholder="index === 0 ? 'origen' : 'min'" suffix=" min" class="num num1" :aria-label="`Minutos hasta la parada ${index + 1}`" />
                  <InputNumber v-model="row.km" :min="0" :use-grouping="false" :disabled="!puedeEditar" placeholder="km" suffix=" km" class="num num2" :aria-label="`Km hasta la parada ${index + 1}`" />
                  <div class="flags">
                    <label class="flex items-center gap-1 text-xs" v-tooltip.top="'Aquí suben pasajeros'"><Checkbox v-model="row.permiteSubir" binary :disabled="!puedeEditar || index === form.paradas.length - 1" />Sube</label>
                    <label class="flex items-center gap-1 text-xs" v-tooltip.top="'Aquí bajan pasajeros'"><Checkbox v-model="row.permiteBajar" binary :disabled="!puedeEditar || index === 0" />Baja</label>
                  </div>
                  <div class="acciones">
                    <icon v-if="avisosForm[index]" name="warning-outline" class="text-orange-500" v-tooltip.top="avisosForm[index]" :aria-label="avisosForm[index]" />
                    <Button text rounded size="small" severity="secondary" :disabled="index === 0" v-tooltip.top="'Minutos por clase de bus'" :aria-label="`Minutos por clase de bus de la parada ${index + 1}`" :badge="row.tiempos.length ? String(row.tiempos.length) : undefined" @click="tiemposDe = index">
                      <template #icon><icon name="timer-outline" /></template>
                    </Button>
                    <Button text rounded size="small" severity="secondary" :disabled="!puedeEditar || index === 0" aria-label="Subir la parada" @click="moverParada(index, -1)"
                      ><template #icon><icon name="arrow-upward" /></template
                    ></Button>
                    <Button text rounded size="small" severity="secondary" :disabled="!puedeEditar || index === form.paradas.length - 1" aria-label="Bajar la parada" @click="moverParada(index, 1)"
                      ><template #icon><icon name="arrow-downward" /></template
                    ></Button>
                    <Button text rounded size="small" severity="danger" :disabled="!puedeEditar || form.paradas.length <= 2" aria-label="Quitar la parada" @click="quitar(index)"
                      ><template #icon><icon name="close" /></template
                    ></Button>
                  </div>
                </div>
              </template>
            </SortablePanelList>
          </div>

          <Button v-if="puedeEditar" label="Agregar parada" severity="secondary" outlined size="small" class="self-start" @click="form.paradas.push(nuevaParada())">
            <template #icon><icon name="add" class="mr-1" /></template>
          </Button>
        </section>

        <div v-if="puedeEditar" class="flex flex-col gap-2">
          <Message v-for="e in erroresForm" :key="e" severity="warn" size="small" :closable="false">{{ e }}</Message>
          <div class="flex flex-wrap justify-end gap-2">
            <RouterLink :to="{ name: 'rutas' }" class="no-underline"><Button label="Cancelar" severity="secondary" text /></RouterLink>
            <Button :label="esNueva ? 'Crear ruta' : 'Guardar cambios'" :loading="guardando" :disabled="!!erroresForm.length" @click="guardar">
              <template #icon><icon name="save-outline" class="mr-1" /></template>
            </Button>
          </div>
        </div>
      </div>

      <section class="panel flex flex-col gap-2 min-w-0 @5xl:sticky @5xl:top-4 @5xl:self-start" aria-labelledby="ruta-mapa">
        <h2 id="ruta-mapa" class="m-0 flex items-center gap-2 text-base font-semibold"><icon name="map-outline" />Mapa</h2>
        <MapaRuta :paradas="enMapa" :enclaves="opciones?.lugares ?? []" />
        <p class="m-0 text-xs text-muted-color">Duración hasta el destino: {{ duracion(form.paradas[form.paradas.length - 1]?.minutos ?? null) }}</p>
      </section>
    </div>

    <Dialog :visible="tiemposDe !== null" modal header="Minutos por clase de bus" class="w-[min(28rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && (tiemposDe = null)">
      <div v-if="tiemposDe !== null && form" class="flex flex-col gap-3">
        <p class="m-0 text-sm text-muted-color">{{ lugar(form.paradas[tiemposDe]!.enclaveId) }}: si una clase tarda distinto de {{ form.paradas[tiemposDe]!.minutos ?? "—" }} min desde el origen.</p>
        <div v-for="(t, i) in form.paradas[tiemposDe]!.tiempos" :key="i" class="flex items-center gap-2">
          <Select v-model="t.claseId" :options="opciones?.clases ?? []" option-label="nombre" option-value="id" filter placeholder="Clase" :disabled="!puedeEditar" class="flex-1 min-w-0" />
          <InputNumber v-model="t.minutos" :min="0" :use-grouping="false" suffix=" min" :disabled="!puedeEditar" class="w-28" input-class="w-full" />
          <Button text rounded severity="danger" size="small" :disabled="!puedeEditar" aria-label="Quitar" @click="form.paradas[tiemposDe]!.tiempos.splice(i, 1)"
            ><template #icon><icon name="close" /></template
          ></Button>
        </div>
        <Button v-if="puedeEditar" label="Agregar clase" severity="secondary" outlined size="small" class="self-start" @click="form.paradas[tiemposDe]!.tiempos.push({ claseId: 0, minutos: form.paradas[tiemposDe]!.minutos ?? 0 })">
          <template #icon><icon name="add" class="mr-1" /></template>
        </Button>
      </div>
      <template #footer><Button label="Listo" @click="tiemposDe = null" /></template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { crearRuta, fetchOpcionesRutas, fetchRuta, guardarRuta } from "@/core/ruta/api";
import { avisos, duracion, errores, formDe, formVacio, mover, nuevaParada, payload, type RutaForm } from "@/core/ruta/editor";
import type { OpcionesRutas, RutaDetalle } from "@/core/ruta/types";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import MapaRuta from "./MapaRuta.vue";

/** Cada parada en una, dos o tres líneas según el ancho de la lista (px), y su alto (px). */
type Modo = "ancho" | "medio" | "angosto";
const ALTO: Record<Modo, number> = { ancho: 56, medio: 96, angosto: 136 };
const modoPara = (ancho: number): Modo => (ancho >= 780 ? "ancho" : ancho >= 460 ? "medio" : "angosto");

const route = useRoute();
const router = useRouter();
const esNueva = computed(() => route.params.id === "nueva");
const form = ref<RutaForm | null>(null);
const detalle = shallowRef<RutaDetalle | null>(null);
const opciones = shallowRef<OpcionesRutas | null>(null);
const error = ref("");
const guardando = ref(false);
const tiemposDe = ref<number | null>(null);
const listaEl = ref<HTMLElement | null>(null);
const modo = ref<Modo>("ancho");
let observador: ResizeObserver | null = null;

const puedeEditar = computed(() => !!opciones.value?.puedeEditar);
const alto = computed(() => ALTO[modo.value]);
const erroresForm = computed(() => (form.value ? errores(form.value) : []));
const avisosForm = computed(() => (form.value ? avisos(form.value.paradas) : {}));
const lugares = computed(() => new Map((opciones.value?.lugares ?? []).map((l) => [l.id, l])));
const lugar = (id: number | null) => (id ? (lugares.value.get(id)?.nombre ?? "?") : "Parada sin lugar");
const enMapa = computed(() =>
  (form.value?.paradas ?? [])
    .filter((p) => p.enclaveId)
    .map((p) => {
      const l = lugares.value.get(p.enclaveId!);
      return { nombre: l?.nombre ?? "?", latitud: l?.latitud ?? null, longitud: l?.longitud ?? null };
    }),
);

function moverParada(i: number, delta: number) {
  if (form.value) form.value.paradas = mover(form.value.paradas, i, delta);
}

function quitar(i: number) {
  form.value?.paradas.splice(i, 1);
}

async function cargar() {
  error.value = "";
  try {
    const [o, r] = await Promise.all([fetchOpcionesRutas(), esNueva.value ? Promise.resolve(null) : fetchRuta(Number(route.params.id))]);
    opciones.value = o;
    detalle.value = r;
    form.value = r ? formDe(r) : formVacio();
  } catch (e) {
    error.value = e instanceof HttpError && e.status === 404 ? "La ruta no existe." : e instanceof Error ? e.message : String(e);
  }
}

async function guardar() {
  if (!form.value) return;
  guardando.value = true;
  try {
    const r = esNueva.value ? await crearRuta(payload(form.value)) : await guardarRuta(Number(route.params.id), payload(form.value));
    detalle.value = r;
    form.value = formDe(r);
    notify.success(esNueva.value ? "Ruta creada" : r.salidasFuturas ? `Ruta guardada: ${r.salidasFuturas} salidas próximas con el horario nuevo` : "Ruta guardada");
    if (esNueva.value) await router.replace({ name: "ruta", params: { id: r.id } });
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  } finally {
    guardando.value = false;
  }
}

watch(() => route.params.id, cargar, { immediate: true });

watch(listaEl, (el) => {
  observador?.disconnect();
  if (!el) return;
  observador = new ResizeObserver(([e]) => (modo.value = modoPara(e?.contentRect.width ?? 0)));
  observador.observe(el);
});

onBeforeUnmount(() => observador?.disconnect());
</script>

<style scoped>
.parada {
  display: grid;
  grid-template-columns: auto 2rem minmax(10rem, 1fr) 7rem 7rem auto auto;
  align-items: center;
  gap: 0.5rem;
  height: 100%;
  padding: 0 0.5rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius);
  /*background: var(--p-content-background);*/

  background-color: var(--p-surface-100);
}
.parada--medio,
.parada--angosto {
  align-content: center;
  row-gap: 0.25rem;
  padding: 0.25rem 0.5rem;
}
.parada--medio {
  grid-template-columns: auto 2rem minmax(0, 1fr) minmax(0, 1fr) auto;
  grid-template-areas:
    "tirador numero lugar lugar acciones"
    "tirador numero num1 num2 flags";
}
.parada--angosto {
  grid-template-columns: auto 1.5rem minmax(0, 1fr) minmax(0, 1fr);
  grid-template-areas:
    "tirador numero lugar lugar"
    "tirador numero num1 num2"
    "flags flags acciones acciones";
}
.parada--aviso {
  border-color: var(--p-orange-400);
}
.tirador {
  cursor: grab;
  display: flex;
  border: 0;
  background: none;
  color: var(--p-text-muted-color);
  padding: 0;
}
.numero {
  text-align: center;
  font-weight: 600;
  color: var(--p-text-muted-color);
}
.num :deep(input) {
  width: 100%;
}
.flags {
  display: flex;
  gap: 0.75rem;
}
.acciones {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}
.lugar,
.num {
  width: 100%;
  min-width: 0;
}
:is(.parada--medio, .parada--angosto) .tirador {
  grid-area: tirador;
}
:is(.parada--medio, .parada--angosto) .numero {
  grid-area: numero;
}
:is(.parada--medio, .parada--angosto) .lugar {
  grid-area: lugar;
}
:is(.parada--medio, .parada--angosto) .num1 {
  grid-area: num1;
}
:is(.parada--medio, .parada--angosto) .num2 {
  grid-area: num2;
}
:is(.parada--medio, .parada--angosto) .flags {
  grid-area: flags;
}
:is(.parada--medio, .parada--angosto) .acciones {
  grid-area: acciones;
}
</style>
