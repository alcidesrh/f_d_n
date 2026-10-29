<!--
  Artículos de una encomienda (ADR-035): cada uno es un paquete (por peso,
  medidas opcionales) o un especial del catálogo (precio fijo), con cantidad
  de bultos iguales y una descripción. Muestra el importe de cada línea que
  ya cotizó el padre. Lo usan el registro de encomiendas y la venta con boleto.

  Angosto: cada artículo en una tarjeta de dos filas; ancho (container
  query), una sola fila.
-->
<template>
  <div class="@container flex flex-col gap-2">
    <TransitionGroup tag="ol" name="articulo" class="m-0 flex list-none flex-col gap-2 p-0">
      <li v-for="(a, i) in modelValue" :key="claves[i]" class="articulo">
        <div class="fila">
          <SelectButton :model-value="a.tipo" :options="TIPOS" option-label="label" option-value="value" :allow-empty="false" size="small" :aria-label="`Tipo del artículo ${i + 1}`" @update:model-value="cambiar(i, { tipo: $event, especial: null, peso: null })" />

          <Select
            v-if="a.tipo === 'especial'"
            :model-value="a.especial ?? null"
            :options="especialesVisibles"
            option-label="nombre"
            option-value="id"
            filter
            :filter-fields="['nombre', 'descripcion']"
            placeholder="Tipo de especial"
            size="small"
            class="especial"
            :invalid="intentado && !a.especial"
            :aria-label="`Especial del artículo ${i + 1}`"
            @update:model-value="cambiar(i, { especial: $event })"
          >
            <template #option="{ option }">
              <div class="flex w-full items-baseline justify-between gap-3">
                <span class="min-w-0 truncate">{{ option.nombre }}</span>
                <span class="shrink-0 tabular-nums text-muted-color">{{ option.precio.texto }}</span>
              </div>
            </template>
          </Select>
          <InputGroup v-else class="peso">
            <InputNumber :model-value="a.peso ?? null" :min="0.1" :max="2000" :max-fraction-digits="2" placeholder="Peso" size="small" :invalid="intentado && !(Number(a.peso) > 0)" :aria-label="`Peso en libras del artículo ${i + 1}`" fluid @update:model-value="cambiar(i, { peso: $event })" />
            <InputGroupAddon>lb</InputGroupAddon>
          </InputGroup>

          <InputGroup class="cantidad">
            <InputGroupAddon>×</InputGroupAddon>
            <InputNumber :model-value="a.cantidad" :min="1" :max="100" size="small" :aria-label="`Bultos del artículo ${i + 1}`" fluid @update:model-value="cambiar(i, { cantidad: $event ?? 1 })" />
          </InputGroup>

          <span class="importe tabular-nums" :class="{ 'text-muted-color': !importes[i] }">{{ importes[i] ?? "—" }}</span>
          <Button text rounded severity="secondary" size="small" class="quitar" :aria-label="`Quitar el artículo ${i + 1}`" v-tooltip.top="'Quitar'" @click="quitar(i)">
            <template #icon><icon name="delete-outline" /></template>
          </Button>
        </div>
        <div class="fila fila-2">
          <InputText :model-value="a.descripcion ?? ''" maxlength="255" placeholder="Descripción (contenido, color, marcas…)" size="small" class="descripcion" :aria-label="`Descripción del artículo ${i + 1}`" @update:model-value="cambiar(i, { descripcion: $event || null })" />
          <div v-if="a.tipo === 'paquete'" class="medidas" role="group" :aria-label="`Medidas en cm del artículo ${i + 1} (opcional)`">
            <InputNumber v-for="m in MEDIDAS" :key="m.campo" :model-value="a[m.campo] ?? null" :min="1" :max="1000" :placeholder="m.etiqueta" size="small" :aria-label="m.etiqueta" fluid @update:model-value="cambiar(i, { [m.campo]: $event })" />
            <span class="text-xs text-muted-color">cm</span>
          </div>
        </div>
      </li>
    </TransitionGroup>

    <p v-if="!modelValue.length" class="m-0 rounded-lg border border-dashed border-surface-300 p-3 text-center text-sm text-muted-color">Sin artículos. Agregue un paquete (por peso) o un especial (precio fijo).</p>

    <div class="flex flex-wrap items-center gap-2">
      <Button label="Paquete" size="small" severity="secondary" outlined @click="agregar('paquete')">
        <template #icon><icon name="add" class="mr-1" /></template>
      </Button>
      <Button label="Especial" size="small" severity="secondary" outlined :disabled="!especialesVisibles.length" @click="agregar('especial')">
        <template #icon><icon name="add" class="mr-1" /></template>
      </Button>
      <span v-if="bultosTotales" class="ml-auto text-sm text-muted-color">{{ bultosTotales }} {{ bultosTotales === 1 ? "bulto" : "bultos" }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { articuloNuevo, bultos } from "@/core/encomienda/reglas";
import type { ArticuloPedido, CotizacionEncomienda, EspecialOpcion, TipoArticulo } from "@/core/encomienda/types";

const props = withDefaults(
  defineProps<{
    modelValue: ArticuloPedido[];
    especiales: EspecialOpcion[];
    cotizacion?: CotizacionEncomienda | null;
    /** Por cobrar: solo los especiales que lo admiten. */
    porCobrar?: boolean;
    /** Ya se intentó enviar: marca lo que falta. */
    intentado?: boolean;
  }>(),
  { cotizacion: null, porCobrar: false, intentado: false },
);
const emit = defineEmits<{ "update:modelValue": [articulos: ArticuloPedido[]] }>();

const TIPOS: Array<{ label: string; value: TipoArticulo }> = [
  { label: "Paquete", value: "paquete" },
  { label: "Especial", value: "especial" },
];
const MEDIDAS = [
  { campo: "alto", etiqueta: "Alto" },
  { campo: "ancho", etiqueta: "Ancho" },
  { campo: "largo", etiqueta: "Largo" },
] as const;

const especialesVisibles = computed(() => (props.porCobrar ? props.especiales.filter((e) => e.permitePorCobrar) : props.especiales));
const bultosTotales = computed(() => bultos(props.modelValue));
/** El importe de cada línea, si la cotización corresponde a estos artículos. */
const importes = computed(() => (props.cotizacion?.articulos.length === props.modelValue.length ? props.cotizacion.articulos.map((l) => l.importe.texto) : []));

// Clave estable por artículo para animar altas y bajas.
let siguiente = 0;
const claves = ref<number[]>(props.modelValue.map(() => siguiente++));
watch(
  () => props.modelValue.length,
  (n) => {
    while (claves.value.length < n) claves.value.push(siguiente++);
    if (claves.value.length > n) claves.value = claves.value.slice(0, n);
  },
);

function cambiar(i: number, cambios: Partial<ArticuloPedido>) {
  emit(
    "update:modelValue",
    props.modelValue.map((a, j) => (j === i ? { ...a, ...cambios } : a)),
  );
}
function agregar(tipo: TipoArticulo) {
  claves.value.push(siguiente++);
  emit("update:modelValue", [...props.modelValue, articuloNuevo(tipo)]);
}
function quitar(i: number) {
  claves.value.splice(i, 1);
  emit(
    "update:modelValue",
    props.modelValue.filter((_, j) => j !== i),
  );
}
</script>

<style scoped>
.articulo {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.5rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.5rem);
  background: var(--p-content-background);
}
.fila {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) 5.5rem;
  grid-template-areas:
    "tipo tipo quitar"
    "valor valor valor"
    "cantidad importe importe";
  align-items: center;
  gap: 0.375rem 0.5rem;
}
.fila > :first-child {
  grid-area: tipo;
}
.especial,
.peso {
  grid-area: valor;
  min-width: 0;
}
.cantidad {
  grid-area: cantidad;
  width: 6.5rem;
}
.importe {
  grid-area: importe;
  text-align: right;
  font-weight: 600;
}
.quitar {
  grid-area: quitar;
  justify-self: end;
}
.fila-2 {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}
.descripcion {
  flex: 1 1 12rem;
  min-width: 0;
}
.medidas {
  display: grid;
  grid-template-columns: repeat(3, 4.25rem) auto;
  align-items: center;
  gap: 0.25rem;
}
@container (width >= 40rem) {
  .fila {
    grid-template-columns: auto minmax(0, 1fr) 6.5rem 6rem auto;
    grid-template-areas: "tipo valor cantidad importe quitar";
  }
}
.articulo-enter-active,
.articulo-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}
.articulo-enter-from,
.articulo-leave-to {
  opacity: 0;
  transform: translateY(-0.25rem);
}
@media (prefers-reduced-motion: reduce) {
  .articulo-enter-active,
  .articulo-leave-active {
    transition: none;
  }
}
</style>
