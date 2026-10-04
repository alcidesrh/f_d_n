<!--
  Programador de salidas (ADR-024): trayecto, horas del día con su bus y
  días (uno solo, N días o hasta una fecha, cada X días). La vista previa
  se pide sola al backend y marca las que ya existen, chocan con otro viaje
  del bus o ya pasaron; solo se crean las nuevas.
  Esquemas: la configuración se puede guardar con un nombre al crear, y un
  esquema guardado se carga, cambia (buses, horas, quitar horas) o borra aquí mismo.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <!-- <Toolbar> -->
    <!-- <template #start><PageHead /></template> -->
    <!-- <template #end> -->
    <RouterLink :to="{ name: 'salidas' }" class="no-underline ml-auto">
      <Button label="Ver salidas" severity="secondary" text size="small"
        ><template #icon><icon name="list" class="mr-1" /></template
      ></Button>
    </RouterLink>
    <!-- </template> -->
    <!-- </Toolbar> -->

    <Message v-if="sinPermiso" severity="warn" :closable="false">No tiene permiso para programar salidas (salida.crear).</Message>

    <div v-else class="grid gap-4 @5xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] @5xl:items-start">
      <div class="flex flex-col gap-4">
        <!-- Esquema guardado -->
        <section class="panel flex flex-col gap-3 mb-4" aria-labelledby="pg-esquema">
          <h2 id="pg-esquema" class="m-0 flex items-center gap-2 text-base font-semibold"><icon name="template" />Esquema guardado</h2>
          <div class="flex flex-col gap-2 @xl:flex-row @xl:items-end">
            <label class="flex flex-1 flex-col gap-1">
              <span class="text-xs font-medium text-muted-color">Cargar un esquema</span>
              <Select v-model="esquemaId" :options="esquemas" option-label="nombre" option-value="id" show-clear filter fluid :placeholder="esquemas.length ? 'Elegir…' : 'No hay esquemas guardados'" :disabled="!esquemas.length">
                <template #option="{ option }">
                  <div class="flex flex-col">
                    <span>{{ option.nombre }}</span>
                    <span class="text-xs text-muted-color">{{ option.trayecto.ruta }} · {{ option.momentos.length }} hora(s){{ option.intervaloDias > 1 ? ` · cada ${option.intervaloDias} días` : "" }}</span>
                  </div>
                </template>
              </Select>
            </label>
            <div v-if="esquema" class="flex flex-wrap gap-2">
              <Button label="Guardar cambios" size="small" :disabled="!cambiosEsquema || !!errores.length" :loading="guardandoEsquema" @click="actualizarEsquema">
                <template #icon><icon name="device-floppy" class="mr-1" /></template>
              </Button>
              <Button label="Borrar" size="small" severity="danger" outlined @click="borrarEsquema = true"
                ><template #icon><icon name="trash" class="mr-1" /></template
              ></Button>
            </div>
          </div>
          <p v-if="esquema" class="m-0 text-xs text-muted-color">
            <template v-if="cambiosEsquema">Tiene cambios sin guardar en «{{ esquema.nombre }}».</template>
            <template v-else
              >Sin cambios. Último cambio: {{ fechaHora(esquema.actualizadoEn) }}<template v-if="esquema.actualizadoPor"> por {{ esquema.actualizadoPor }}</template
              >.</template
            >
            Cambiar el esquema no toca las salidas ya creadas.
          </p>
        </section>

        <Divider align="center" class="">
          <span class="text-surface-500 font-semibold">Programar salidas</span>
        </Divider>

        <!-- Trayecto -->
        <section class="panel flex flex-col gap-3 mb-5" aria-labelledby="pg-trayecto">
          <h2 id="pg-trayecto" class="m-0 flex items-center gap-2 text-base font-semibold"><icon name="route" />Trayecto</h2>
          <Select v-model="form.trayectoId" :options="opciones?.trayectos ?? []" option-label="ruta" option-value="id" filter fluid :virtual-scroller-options="{ itemSize: 38 }" placeholder="Origen → destino" :loading="!opciones" aria-label="Trayecto" />
          <p v-if="trayecto?.duracionMinutos" class="m-0 text-xs text-muted-color">Duración estimada: {{ duracion(trayecto.duracionMinutos) }}. Un bus no se programa mientras está en otro viaje.</p>
          <p v-else-if="trayecto" class="m-0 text-xs text-muted-color">El trayecto no tiene duración estimada: solo se evita el mismo bus a la misma hora.</p>
        </section>
        <!-- <divider /> -->

        <!-- Horas y buses -->
        <section class="panel flex flex-col gap-3 mb-5" aria-labelledby="pg-horas">
          <h2 id="pg-horas" class="m-0 flex items-center gap-2 text-base font-semibold"><icon name="clock" />Horas de salida y bus</h2>
          <ul class="m-0 flex list-none flex-col gap-2 p-0">
            <li v-for="(m, i) in form.momentos" :key="i" class="grid grid-cols-[7rem_minmax(0,1fr)_auto] items-center gap-2">
              <InputText v-model="m.hora" type="time" :aria-label="`Hora n.º ${i + 1}`" class="tabular-nums" />
              <Select v-model="m.busId" :options="opciones?.buses ?? []" :option-label="etiquetaBus" option-value="id" filter :filter-fields="['codigo', 'matricula']" fluid placeholder="Bus" :aria-label="`Bus de la hora n.º ${i + 1}`" />
              <Button severity="secondary" text rounded class="tap-target" :aria-label="`Quitar la hora n.º ${i + 1}`" :disabled="form.momentos.length === 1" @click="form.momentos.splice(i, 1)">
                <template #icon><icon name="x" /></template>
              </Button>
            </li>
          </ul>
          <div class="flex flex-wrap gap-2">
            <Button label="Agregar hora" size="small" severity="secondary" outlined :disabled="form.momentos.length >= MAX_MOMENTOS" @click="agregarHora"
              ><template #icon><icon name="plus" class="mr-1" /></template
            ></Button>
            <Button v-if="form.momentos.length > 1" label="Ordenar por hora" size="small" text @click="ordenar">
              <template #icon><icon name="sort-ascending" class="mr-1" /></template>
            </Button>
          </div>
        </section>

        <!-- Días -->
        <section class="panel flex flex-col gap-3" aria-labelledby="pg-dias">
          <h2 id="pg-dias" class="m-0 flex items-center gap-2 text-base font-semibold"><icon name="calendar" />Días</h2>
          <label class="flex flex-col gap-1">
            <span class="text-xs font-medium text-muted-color">Día (primera salida)</span>
            <DatePicker v-model="desde" date-format="dd/mm/yy" show-icon fluid :min-date="hoy" />
          </label>
          <div class="flex flex-col gap-1 mt-5">
            <span class="text-xs font-medium text-muted-color">Repetir</span>
            <SelectButton
              v-model="form.repeticion"
              :options="[
                { v: 'no', l: 'Solo ese día' },
                { v: 'veces', l: 'N días' },
                { v: 'hasta', l: 'Hasta una fecha' },
              ]"
              option-label="l"
              option-value="v"
              :allow-empty="false"
              class="flex-wrap"
            />
          </div>
          <div v-if="form.repeticion !== 'no'" class="grid grid-cols-2 gap-3">
            <label class="flex flex-col gap-1">
              <span class="text-xs font-medium text-muted-color">Cada</span>
              <InputNumber v-model="intervalo" :min="1" :max="MAX_INTERVALO" show-buttons suffix=" día(s)" fluid />
            </label>
            <label v-if="form.repeticion === 'veces'" class="flex flex-col gap-1">
              <span class="text-xs font-medium text-muted-color">Cuántos días</span>
              <InputNumber v-model="veces" :min="1" :max="MAX_DIAS" show-buttons fluid />
            </label>
            <label v-else class="flex flex-col gap-1">
              <span class="text-xs font-medium text-muted-color">Hasta (incluido)</span>
              <DatePicker v-model="hasta" date-format="dd/mm/yy" show-icon fluid :min-date="desde ?? hoy" />
            </label>
          </div>
          <p v-if="dias.length" class="m-0 text-sm">
            <b>{{ dias.length }}</b> día(s){{ dias.length > 1 ? `, del ${diaCorto(dias[0]!)} al ${diaCorto(dias[dias.length - 1]!)}` : `: ${diaLargo(dias[0]!)}` }} · <b>{{ totalSalidas(form) }}</b> salida(s) en total
          </p>
        </section>
        <divider />

        <!-- Guardar esquema -->
        <section class="panel flex flex-col gap-3">
          <div class="flex items-center gap-2">
            <Checkbox v-model="guardar" binary input-id="pg-guardar" />
            <label for="pg-guardar" class="font-medium">Guardar esta configuración como esquema</label>
          </div>
          <InputText v-if="guardar" v-model="nombreEsquema" placeholder="Nombre del esquema, p. ej. «Xela–Guate diario»" maxlength="100" fluid aria-label="Nombre del esquema" />
          <p class="m-0 text-xs text-muted-color">Guarda el trayecto, las horas con su bus y el intervalo; los días se eligen cada vez que se usa.</p>
        </section>
      </div>

      <!-- Vista previa -->
      <div class="flex flex-wsrap w-full gap-4">
        <divider layout="vertical" />

        <section class="panel w-full flex flex-col gap-3 @5xl:sticky @5xl:top-4" aria-labelledby="pg-previa">
          <h2 id="pg-previa" class="m-0 flex items-center gap-2 text-base font-semibold"><icon name="eye" />Vista previa</h2>
          <ul v-if="errores.length" class="m-0 flex flex-col gap-1 pl-4 text-sm text-muted-color">
            <li v-for="e in errores" :key="e">{{ e }}</li>
          </ul>
          <template v-else>
            <Message v-if="errorPrevia" severity="error" :closable="false">{{ errorPrevia }}</Message>
            <Skeleton v-else-if="!previa" height="6rem" />
            <template v-else>
              <div class="flex flex-wrap gap-2" :class="{ 'opacity-60': cargandoPrevia }">
                <Tag v-for="r in resumenPrevia" :key="r.estado" :severity="r.severidad" :value="`${r.n} ${r.etiqueta}`" />
              </div>
              <div class="flex max-h-[28rem] flex-col gap-2 overflow-auto">
                <details v-for="g in porDia" :key="g.dia" class="rounded-border border border-surface-200 px-3 py-2" :open="porDia.length <= 3 || g.problemas > 0">
                  <summary class="cursor-pointer text-sm font-medium">
                    {{ diaLargo(g.dia) }}
                    <span class="text-muted-color">· {{ g.items.length }}</span>
                    <Tag v-if="g.problemas" severity="warn" :value="`${g.problemas} sin crear`" class="ml-1 !text-xs" />
                  </summary>
                  <ul class="m-0 mt-2 flex list-none flex-col gap-1 p-0 text-sm">
                    <li v-for="it in g.items" :key="it.fecha + it.busId" class="flex flex-wrap items-baseline gap-x-2">
                      <span class="w-12 tabular-nums">{{ horaDe(it.fecha) }}</span>
                      <span class="font-medium">Bus {{ it.bus }}</span>
                      <Tag :severity="ESTADO_PLAN[it.estado].severidad" :value="ESTADO_PLAN[it.estado].etiqueta" class="!text-xs" />
                      <span v-if="it.choque" class="basis-full pl-14 text-xs text-muted-color">
                        {{ it.estado === "existe" ? "Ya existe" : "Choca con" }}
                        <template v-if="it.choque.id">la salida #{{ it.choque.id }}</template
                        ><template v-else>otra hora de esta programación</template>: {{ it.choque.ruta }}, {{ fechaHora(it.choque.fecha) }}
                      </span>
                    </li>
                  </ul>
                </details>
              </div>
            </template>
          </template>
          <Button :label="previa && !errores.length ? `Crear ${previa.resumen.nueva} salida(s)` : 'Crear salidas'" :disabled="!!errores.length || !previa || cargandoPrevia || (previa.resumen.nueva === 0 && !form.guardarComo)" :loading="creando" @click="crear">
            <template #icon><icon name="calendar-plus" class="mr-1" /></template>
          </Button>
          <Message v-if="resultado" :severity="resultado.creadas ? 'success' : 'warn'" :closable="false">
            <div class="flex flex-col gap-1">
              <span
                >Se crearon <b>{{ resultado.creadas }}</b> salida(s){{ resultado.omitidas.length ? `; ${resultado.omitidas.length} no se crearon (ya existían, chocaban o ya pasaron)` : "" }}.</span
              >
              <span v-if="resultado.esquema">Esquema «{{ resultado.esquema.nombre }}» guardado.</span>
              <RouterLink :to="{ name: 'salidas' }">Ver el listado de salidas</RouterLink>
            </div>
          </Message>
        </section>
      </div>
    </div>

    <Dialog v-model:visible="borrarEsquema" modal header="Borrar esquema" class="w-[min(26rem,calc(100vw-1rem))]">
      <p class="m-0">¿Borrar el esquema «{{ esquema?.nombre }}»? Las salidas ya creadas con él no cambian.</p>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text @click="borrarEsquema = false" />
        <Button label="Borrar" severity="danger" :loading="guardandoEsquema" @click="confirmarBorrarEsquema" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { eliminarEsquema, fetchEsquemas, fetchOpciones, guardarEsquema, programar, vistaPrevia } from "@/core/salida/api";
import { aDia, deDia, horaDe } from "@/core/salida/filtro";
import { aEsquema, aPayload, conEsquema, diasProgramados, difiereDeEsquema, errores as erroresDe, formInicial, horaValida, MAX_DIAS, MAX_INTERVALO, MAX_MOMENTOS, normalizarHora, totalSalidas, type ProgramadorForm } from "@/core/salida/programacion";
import type { BusOpcion, EstadoPlan, Esquema, ItemPlan, OpcionesSalidas, ResultadoProgramacion, VistaPrevia } from "@/core/salida/types";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";

const ESTADO_PLAN: Record<EstadoPlan, { etiqueta: string; severidad: "success" | "secondary" | "warn" | "danger" }> = {
  nueva: { etiqueta: "nueva", severidad: "success" },
  existe: { etiqueta: "ya existe", severidad: "secondary" },
  conflicto: { etiqueta: "bus ocupado", severidad: "danger" },
  pasada: { etiqueta: "hora pasada", severidad: "warn" },
};

const route = useRoute();
const hoy = new Date();
hoy.setHours(0, 0, 0, 0);

const form = reactive<ProgramadorForm>(formInicial());
const opciones = shallowRef<OpcionesSalidas | null>(null);
const sinPermiso = ref(false);
const esquemas = ref<Esquema[]>([]);
const esquemaId = ref<number | null>(null);
const guardandoEsquema = ref(false);
const borrarEsquema = ref(false);
const guardar = ref(false);
const nombreEsquema = ref("");
const previa = shallowRef<VistaPrevia | null>(null);
const cargandoPrevia = ref(false);
const errorPrevia = ref("");
const creando = ref(false);
const resultado = shallowRef<ResultadoProgramacion | null>(null);

const esquema = computed(() => esquemas.value.find((e) => e.id === esquemaId.value) ?? null);
const cambiosEsquema = computed(() => !!esquema.value && difiereDeEsquema(form, esquema.value));
const trayecto = computed(() => opciones.value?.trayectos.find((t) => t.id === form.trayectoId) ?? null);
const dias = computed(() => diasProgramados(form));
const errores = computed(() => erroresDe(form));

const empresas = computed(() => new Map((opciones.value?.empresas ?? []).map((e) => [e.id, e.nombre])));
const etiquetaBus = (b: BusOpcion) => [b.codigo, b.matricula, b.empresaId ? empresas.value.get(b.empresaId) : null].filter(Boolean).join(" · ");

watch(guardar, (g) => (form.guardarComo = g ? nombreEsquema.value : null));
watch(nombreEsquema, (n) => guardar.value && (form.guardarComo = n));

/** Campos numéricos/fecha de PrimeVue ↔ el formulario. */
const intervalo = computed<number | null>({ get: () => form.intervaloDias, set: (v) => (form.intervaloDias = v ?? 1) });
const veces = computed<number | null>({ get: () => form.veces, set: (v) => (form.veces = v ?? 1) });
const desde = computed<Date | null>({ get: () => (form.desde ? deDia(form.desde) : null), set: (d) => (form.desde = d ? aDia(d) : null) });
const hasta = computed<Date | null>({ get: () => (form.hasta ? deDia(form.hasta) : null), set: (d) => (form.hasta = d ? aDia(d) : null) });

watch(esquemaId, () => {
  if (esquema.value) Object.assign(form, conEsquema(form, esquema.value));
  guardar.value = false;
});

function agregarHora() {
  const ultima = form.momentos[form.momentos.length - 1];
  form.momentos.push({ hora: ultima?.hora ?? "06:00", busId: null });
}

function ordenar() {
  form.momentos.sort((a, b) => (horaValida(a.hora) ? normalizarHora(a.hora) : a.hora).localeCompare(horaValida(b.hora) ? normalizarHora(b.hora) : b.hora));
}

// Vista previa automática (con espera) cada vez que el formulario es válido y cambia.
let espera: ReturnType<typeof setTimeout> | null = null;
let pedido = 0;
watch(
  () => (errores.value.length ? null : JSON.stringify({ ...aPayload(form), guardarComo: undefined })),
  (clave) => {
    if (espera) clearTimeout(espera);
    resultado.value = null;
    if (!clave) return;
    espera = setTimeout(cargarPrevia, 400);
  },
);

async function cargarPrevia() {
  const n = ++pedido;
  cargandoPrevia.value = true;
  errorPrevia.value = "";
  try {
    const r = await vistaPrevia(aPayload(form));
    if (n === pedido) previa.value = r;
  } catch (e) {
    if (n === pedido) {
      previa.value = null;
      errorPrevia.value = mensaje(e);
    }
  } finally {
    if (n === pedido) cargandoPrevia.value = false;
  }
}

const resumenPrevia = computed(() => {
  const r = previa.value?.resumen;
  if (!r) return [];
  return (Object.keys(ESTADO_PLAN) as EstadoPlan[]).filter((e) => r[e] > 0).map((e) => ({ estado: e, n: r[e], ...ESTADO_PLAN[e] }));
});

const porDia = computed(() => {
  const grupos = new Map<string, ItemPlan[]>();
  for (const it of previa.value?.items ?? []) {
    const dia = aDia(new Date(it.fecha));
    grupos.set(dia, [...(grupos.get(dia) ?? []), it]);
  }
  return [...grupos].map(([dia, items]) => ({ dia, items, problemas: items.filter((i) => i.estado !== "nueva").length }));
});

async function crear() {
  creando.value = true;
  try {
    const r = await programar(aPayload(form));
    resultado.value = r;
    notify[r.creadas ? "success" : "warning"](`Se crearon ${r.creadas} salida(s).`);
    if (r.esquema) {
      esquemas.value = [...esquemas.value, r.esquema].sort((a, b) => a.nombre.localeCompare(b.nombre));
      esquemaId.value = r.esquema.id;
    }
    void cargarPrevia().then(() => (resultado.value = r));
  } catch (e) {
    notify.error(mensaje(e));
  } finally {
    creando.value = false;
  }
}

async function actualizarEsquema() {
  if (!esquema.value) return;
  guardandoEsquema.value = true;
  try {
    const e = await guardarEsquema(esquema.value.id, aEsquema(form, esquema.value.nombre));
    esquemas.value = esquemas.value.map((x) => (x.id === e.id ? e : x));
    notify.success(`Esquema «${e.nombre}» actualizado.`);
  } catch (e) {
    notify.error(mensaje(e));
  } finally {
    guardandoEsquema.value = false;
  }
}

async function confirmarBorrarEsquema() {
  if (!esquema.value) return;
  guardandoEsquema.value = true;
  try {
    const { id } = await eliminarEsquema(esquema.value.id);
    const nombre = esquema.value.nombre;
    esquemaId.value = null;
    esquemas.value = esquemas.value.filter((x) => x.id !== id);
    borrarEsquema.value = false;
    notify.success(`Esquema «${nombre}» borrado.`);
  } catch (e) {
    notify.error(mensaje(e));
  } finally {
    guardandoEsquema.value = false;
  }
}

const mensaje = (e: unknown) => (e instanceof Error ? e.message : String(e));

const fechaHora = (iso: string) => new Intl.DateTimeFormat("es-GT", { day: "2-digit", month: "short", hour: "numeric", minute: "2-digit" }).format(new Date(iso));
const diaCorto = (d: string) => new Intl.DateTimeFormat("es-GT", { day: "numeric", month: "short" }).format(deDia(d));
const diaLargo = (d: string) => new Intl.DateTimeFormat("es-GT", { weekday: "short", day: "numeric", month: "short", year: "numeric" }).format(deDia(d));
const duracion = (min: number) => (min >= 60 ? `${Math.floor(min / 60)} h${min % 60 ? ` ${min % 60} min` : ""}` : `${min} min`);

onMounted(async () => {
  try {
    const [o, e] = await Promise.all([fetchOpciones(), fetchEsquemas()]);
    opciones.value = o;
    esquemas.value = e.items;
    if (!o.puede.crear) sinPermiso.value = true;
    const pedido = Number(route.query.esquema);
    if (pedido && e.items.some((x) => x.id === pedido)) esquemaId.value = pedido;
  } catch (e) {
    if (e instanceof HttpError && e.status === 403) sinPermiso.value = true;
    else notify.error(mensaje(e));
  }
});
</script>
