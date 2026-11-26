<!--
  El libro de un almacén (ADR-071): cada entrada, salida, envío, recepción y
  conteo, lo más nuevo primero, con el saldo que dejó, quién y por qué. No
  se edita: un error se corrige con otro movimiento.
-->
<template>
  <div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <Select v-model="articulo" :options="articulos" option-label="nombre" option-value="id" show-clear filter placeholder="Todos los artículos" class="w-full sm:w-72" />
      <Button severity="secondary" text size="small" :loading="cargando" aria-label="Actualizar" @click="cargar(pagina?.pagina ?? 1)">
        <template #icon><icon name="refresh" /></template>
      </Button>
    </div>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <DataTable v-else :value="pagina?.filas ?? []" :loading="cargando && !pagina" data-key="id" size="small" striped-rows>
      <template #empty><p class="m-0 py-6 text-center text-sm text-muted-color">Todavía no hay movimientos.</p></template>
      <Column header="Fecha">
        <template #body="{ data }"><span class="whitespace-nowrap text-sm">{{ fecha(data.creadoEn) }}</span></template>
      </Column>
      <Column header="Qué">
        <template #body="{ data }"><Tag :value="data.etiqueta" :severity="SEVERIDAD[data.tipo as TipoMovimiento]" /></template>
      </Column>
      <Column header="Artículo">
        <template #body="{ data }">{{ data.articulo.nombre }}</template>
      </Column>
      <Column header="Cantidad" class="text-right tabular-nums">
        <template #body="{ data }"><span class="whitespace-nowrap">{{ conSigno(data.cantidad) }} {{ data.articulo.unidad }}</span></template>
      </Column>
      <Column header="Saldo" class="text-right tabular-nums">
        <template #body="{ data }">{{ cantidad(data.saldo) }}</template>
      </Column>
      <Column header="Costo c/u" class="text-right tabular-nums">
        <template #body="{ data }">{{ quetzales(data.costoUnitario, true) }}</template>
      </Column>
      <Column header="Detalle">
        <template #body="{ data }">
          <div class="flex min-w-48 flex-col text-xs">
            <span v-if="data.destino">Hacia {{ data.destino.nombre }} · {{ data.recibidoEn ? `recibido ${fecha(data.recibidoEn)}` : "en camino" }}</span>
            <span v-if="data.origen">Desde {{ data.origen.nombre }}</span>
            <span v-if="data.documento">Doc. {{ data.documento }}</span>
            <span v-if="data.motivo" class="text-muted-color">{{ data.motivo }}</span>
            <RouterLink v-if="data.afectacion" :to="{ name: 'afectaciones', query: { id: data.afectacion.id } }">Afectación #{{ data.afectacion.id }}</RouterLink>
          </div>
        </template>
      </Column>
      <Column header="Quién">
        <template #body="{ data }"><span class="text-sm">{{ data.usuario?.nombre ?? "Sistema" }}</span></template>
      </Column>
    </DataTable>
    <Paginator v-if="pagina && pagina.total > pagina.porPagina" :rows="pagina.porPagina" :total-records="pagina.total" :first="(pagina.pagina - 1) * pagina.porPagina" @page="(e) => cargar(e.page + 1)" />
  </div>
</template>

<script setup lang="ts">
import { fetchMovimientos } from "@/core/inventario/api";
import { cantidad, conSigno, fecha, quetzales } from "@/core/inventario/reglas";
import type { ArticuloBreve, PaginaMovimientos, TipoMovimiento } from "@/core/inventario/tipos";

const props = defineProps<{ almacen: number; articulos: ArticuloBreve[]; version: number }>();

const SEVERIDAD: Record<TipoMovimiento, "success" | "info" | "warn" | "secondary" | "contrast"> = {
  entrada: "success",
  recepcion: "success",
  salida: "secondary",
  envio: "info",
  ajuste: "warn",
};

const articulo = ref<number | null>(null);
const pagina = shallowRef<PaginaMovimientos | null>(null);
const cargando = ref(false);
const error = ref("");

let pedido = 0;
async function cargar(n = 1) {
  const mio = ++pedido;
  cargando.value = true;
  error.value = "";
  try {
    const p = await fetchMovimientos(props.almacen, articulo.value, n);
    if (mio === pedido) pagina.value = p;
  } catch (e) {
    if (mio === pedido) error.value = e instanceof Error ? e.message : String(e);
  } finally {
    if (mio === pedido) cargando.value = false;
  }
}

watch(
  () => [props.almacen, articulo.value] as const,
  () => {
    pagina.value = null;
    void cargar();
  },
);
watch(
  () => props.version,
  () => void cargar(pagina.value?.pagina ?? 1),
);
onMounted(() => void cargar());
</script>
