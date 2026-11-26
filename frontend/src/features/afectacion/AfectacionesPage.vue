<!--
  Afectaciones de la logística (ADR-070), ruta `/afectaciones`: lo que se
  rompió, se agotó o falla en una estación, un bus o la empresa.

  - Cada uno ve las que reportó, las que atiende (la capacidad `target` del
    tipo, donde cae) y, con `afectacion.supervisar`, todas las de su alcance.
  - «Reportar» abre el formulario con los tipos que puede reportar (`owner`).
  - «Corregir» y «Resolver», en las que atiende.

  `?vista=` elige la pestaña y `?id=` muestra esa afectación arriba (el
  enlace de los avisos del chat). Se refresca sola cada minuto, al ritmo del
  centinela.
-->
<template>
  <div class="@container mx-auto flex w-full max-w-4xl flex-col gap-4">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div class="flex min-w-0 flex-col gap-1">
        <h1 class="m-0 flex items-center gap-2 text-xl font-semibold"><icon name="report-outline" />Afectaciones</h1>
        <p class="m-0 text-sm text-muted-color">Lo que se rompe, se agota o falla en una estación, un bus o la empresa. Se avisa a quien la atiende; si no se resuelve a tiempo, se insiste y se escala hasta que se resuelve.</p>
      </div>
      <div class="flex items-center gap-2">
        <RouterLink v-if="pagina?.puede.tipos" :to="{ name: 'entity-list', params: { entity: 'tipo-afectacion' } }" class="text-sm">Tipos</RouterLink>
        <Button label="Reportar" @click="reportando = true">
          <template #icon><icon name="add" class="mr-1" /></template>
        </Button>
      </div>
    </header>

    <div class="flex flex-wrap items-center justify-between gap-2">
      <SelectButton v-model="vista" :options="VISTAS" option-label="etiqueta" option-value="valor" :allow-empty="false" aria-label="Vista" />
      <Button severity="secondary" text size="small" :loading="cargando" aria-label="Actualizar" @click="cargar(pagina?.pagina ?? 1)">
        <template #icon><icon name="refresh" /></template>
      </Button>
    </div>

    <AfectacionTarjeta v-if="destacada" :a="destacada" :ahora="ahora" destacada @editar="(a) => abrir(a, 'editar')" @resolver="(a) => abrir(a, 'resolver')" />

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-else-if="!pagina" height="10rem" />
    <p v-else-if="!items.length" class="m-0 py-10 text-center text-sm text-muted-color">{{ VACIO[vista] }}</p>
    <div v-else class="flex flex-col gap-3" :class="{ 'opacity-60': cargando }">
      <AfectacionTarjeta v-for="a in items" :key="a.id" :a="a" :ahora="ahora" @editar="(x) => abrir(x, 'editar')" @resolver="(x) => abrir(x, 'resolver')" />
    </div>
    <Paginator v-if="pagina && pagina.total > pagina.porPagina" :rows="pagina.porPagina" :total-records="pagina.total" :first="(pagina.pagina - 1) * pagina.porPagina" @page="(e) => cargar(e.page + 1)" />

    <ReportarDialog :visible="reportando" @cerrar="reportando = false" @reportada="alReportar" />
    <AtenderDialog :a="atendiendo" :como="como" @cerrar="atendiendo = null" @atendida="alAtender" />
  </div>
</template>

<script setup lang="ts">
import { fetchAfectacion, fetchAfectaciones } from "@/core/afectacion/api";
import type { Afectacion, PaginaAfectaciones, VistaAfectaciones } from "@/core/afectacion/tipos";
import { notify } from "@/core/notify";
import AfectacionTarjeta from "./AfectacionTarjeta.vue";
import AtenderDialog from "./AtenderDialog.vue";
import ReportarDialog from "./ReportarDialog.vue";

const VISTAS: Array<{ valor: VistaAfectaciones; etiqueta: string }> = [
  { valor: "abiertas", etiqueta: "Abiertas" },
  { valor: "resueltas", etiqueta: "Resueltas" },
  { valor: "todas", etiqueta: "Todas" },
];
const VACIO: Record<VistaAfectaciones, string> = {
  abiertas: "No hay afectaciones abiertas que le toquen.",
  resueltas: "Todavía no hay afectaciones resueltas que le toquen.",
  todas: "No hay afectaciones que le toquen.",
};
const REFRESCO_MS = 60_000;

const route = useRoute();
const router = useRouter();

const vista = ref<VistaAfectaciones>(VISTAS.some((v) => v.valor === route.query.vista) ? (route.query.vista as VistaAfectaciones) : "abiertas");
const pagina = shallowRef<PaginaAfectaciones | null>(null);
const destacada = shallowRef<Afectacion | null>(null);
const items = computed(() => (pagina.value?.filas ?? []).filter((a) => a.id !== destacada.value?.id));
const cargando = ref(false);
const error = ref("");
const ahora = ref(new Date());
const reportando = ref(false);
const atendiendo = shallowRef<Afectacion | null>(null);
const como = ref<"editar" | "resolver">("resolver");

let pedido = 0;
async function cargar(n = 1, silencioso = false) {
  const mio = ++pedido;
  cargando.value = true;
  if (!silencioso) error.value = "";
  try {
    const p = await fetchAfectaciones(vista.value, null, n, silencioso);
    if (mio === pedido) {
      pagina.value = p;
      error.value = "";
    }
  } catch (e) {
    if (mio === pedido && !silencioso) error.value = e instanceof Error ? e.message : String(e);
  } finally {
    if (mio === pedido) cargando.value = false;
    ahora.value = new Date();
  }
}

async function cargarDestacada() {
  const id = Number(route.query.id);
  if (!id) return void (destacada.value = null);
  try {
    destacada.value = await fetchAfectacion(id);
  } catch (e) {
    destacada.value = null;
    notify.error(e instanceof Error ? e.message : String(e));
  }
}

function abrir(a: Afectacion, c: "editar" | "resolver") {
  como.value = c;
  atendiendo.value = a;
}

function alReportar(a: Afectacion) {
  reportando.value = false;
  void router.replace({ query: { ...route.query, id: String(a.id) } });
  void cargar(1, true);
}

function alAtender(a: Afectacion) {
  atendiendo.value = null;
  if (destacada.value?.id === a.id) destacada.value = a;
  void cargar(pagina.value?.pagina ?? 1, true);
}

watch(vista, () => {
  void router.replace({ query: { ...route.query, vista: vista.value } });
  pagina.value = null;
  void cargar();
});
watch(() => route.query.id, cargarDestacada);

let reloj: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
  void cargar();
  void cargarDestacada();
  reloj = setInterval(() => {
    if (document.visibilityState === "visible") void cargar(pagina.value?.pagina ?? 1, true);
  }, REFRESCO_MS);
});
onBeforeUnmount(() => clearInterval(reloj));
</script>
