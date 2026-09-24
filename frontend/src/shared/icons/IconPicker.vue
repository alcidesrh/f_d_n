<template>
  <div class="icon-picker flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <IconField class="min-w-48 flex-1">
        <InputIcon><icon name="search" color="text-muted-color" /></InputIcon>
        <InputText
          ref="searchInput"
          v-model="query"
          class="w-full"
          placeholder="Buscar por nombre, tag o categoría…"
          aria-label="Buscar ícono"
          @keydown.enter.prevent="pickFirst"
        />
      </IconField>
      <SelectButton
        v-model="style"
        :options="styleOptions"
        option-label="label"
        option-value="value"
        :allow-empty="false"
        size="small"
        aria-label="Estilo"
      />
    </div>

    <Select
      v-model="category"
      class="w-full sm:hidden"
      :options="categoryOptions"
      option-label="label"
      option-value="value"
      placeholder="Todas las categorías"
      show-clear
    />

    <div class="flex min-h-0 gap-3" :style="{ height }">
      <nav class="hidden w-44 shrink-0 flex-col gap-0.5 overflow-y-auto pr-1 sm:flex" aria-label="Categorías">
        <button
          v-for="option in categoryOptions"
          :key="option.value ?? 'all'"
          type="button"
          class="flex items-center justify-between gap-2 rounded-md px-2 py-1 text-left text-sm transition-colors"
          :class="option.value === category ? 'bg-highlight font-medium' : 'hover:bg-emphasis'"
          @click="category = option.value"
        >
          <span class="truncate">{{ option.name }}</span>
          <span class="text-xs text-muted-color">{{ option.count }}</span>
        </button>
      </nav>

      <div ref="scroller" class="min-w-0 flex-1 overflow-y-auto">
        <div v-if="loading" class="flex h-full items-center justify-center gap-2 text-muted-color">
          <ProgressSpinner style="width: 1.5rem; height: 1.5rem" stroke-width="4" />
          <span class="text-sm">Cargando íconos…</span>
        </div>
        <div v-else-if="error" class="flex h-full flex-col items-center justify-center gap-2 text-sm">
          <span class="text-red-500">No se pudieron cargar los íconos.</span>
          <Button label="Reintentar" size="small" text @click="load" />
        </div>
        <div
          v-else-if="results.length === 0"
          class="flex h-full items-center justify-center text-sm text-muted-color"
        >
          Sin resultados para «{{ query }}»
        </div>
        <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(5.5rem,1fr))] gap-1.5">
          <button
            v-for="item in visible"
            :key="item.name"
            type="button"
            class="flex h-22 flex-col items-center justify-center gap-2 rounded-lg border px-1 transition-colors"
            :class="
              item.name === modelValue
                ? 'border-primary bg-highlight'
                : 'border-transparent hover:border-surface hover:bg-emphasis'
            "
            :title="`${item.name} · ${categoryLabel(item.category)}`"
            :data-icon="item.name"
            :aria-pressed="item.name === modelValue"
            @click="pick(item.name)"
          >
            <icon :name="item.name" lg color="text-color" />
            <span class="w-full truncate text-center text-[0.7rem] leading-tight text-muted-color">
              {{ item.name }}
            </span>
          </button>
        </div>
        <div ref="sentinel" class="h-px" />
      </div>
    </div>

    <div class="flex items-center justify-between gap-2 text-xs text-muted-color">
      <span>{{ results.length }} íconos</span>
      <span v-if="catalog">Tabler Icons v{{ catalog.version }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import {
  categoryLabel,
  loadTablerCatalog,
  searchIcons,
  type TablerCatalog,
  type TablerIconStyle,
} from "./tablerCatalog";

defineOptions({ name: "IconPicker" });

const props = withDefaults(
  defineProps<{
    /** Nombre del ícono seleccionado (formato de `<icon name>`: `bus`, `bus-filled`). */
    modelValue?: string | null;
    /** Alto del área de categorías + grilla. */
    height?: string;
    autofocus?: boolean;
  }>(),
  { modelValue: null, height: "22rem", autofocus: false },
);

const emit = defineEmits<{
  "update:modelValue": [name: string];
  select: [name: string];
}>();

/** Íconos pintados por tanda; la grilla crece al acercarse al final del scroll. */
const PAGE_SIZE = 160;

const catalog = ref<TablerCatalog | null>(null);
const loading = ref(false);
const error = ref(false);

const query = ref("");
const debouncedQuery = ref("");
const category = ref<string | null>(null);
const style = ref<TablerIconStyle | null>("outline");
const limit = ref(PAGE_SIZE);

const styleOptions = [
  { label: "Outline", value: "outline" },
  { label: "Filled", value: "filled" },
  { label: "Todos", value: null },
];

const searchInput = ref<{ $el: HTMLElement } | null>(null);
const scroller = ref<HTMLElement | null>(null);
const sentinel = ref<HTMLElement | null>(null);

async function load() {
  loading.value = true;
  error.value = false;
  try {
    catalog.value = await loadTablerCatalog();
  } catch (e) {
    console.warn("[IconPicker] No se pudo cargar el catálogo:", e);
    error.value = true;
  } finally {
    loading.value = false;
  }
}

/** Resultados por texto + estilo; los conteos por categoría salen de aquí. */
const matches = computed(() =>
  catalog.value
    ? searchIcons(catalog.value.icons, { query: debouncedQuery.value, style: style.value })
    : [],
);

const results = computed(() =>
  category.value ? matches.value.filter((icon) => icon.category === category.value) : matches.value,
);

const visible = computed(() => results.value.slice(0, limit.value));

const categoryOptions = computed(() => {
  const counts = new Map<string, number>();
  for (const icon of matches.value) counts.set(icon.category, (counts.get(icon.category) ?? 0) + 1);
  const options = (catalog.value?.categories ?? []).map((value) => {
    const name = categoryLabel(value);
    const count = counts.get(value) ?? 0;
    return { value: value as string | null, name, count, label: `${name} (${count})` };
  });
  return [
    { value: null, name: "Todas", count: matches.value.length, label: `Todas (${matches.value.length})` },
    ...options,
  ];
});

let debounceTimer: ReturnType<typeof setTimeout> | undefined;
watch(query, (value) => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => (debouncedQuery.value = value), 120);
});

watch([debouncedQuery, category, style], () => {
  limit.value = PAGE_SIZE;
  scroller.value?.scrollTo?.({ top: 0 });
});

function pick(name: string) {
  emit("update:modelValue", name);
  emit("select", name);
}

function pickFirst() {
  const first = results.value[0];
  if (first) pick(first.name);
}

let observer: IntersectionObserver | undefined;

/** Re-observar fuerza una notificación nueva si el sentinel sigue a la vista tras pintar la tanda. */
watch(
  () => visible.value.length,
  () =>
    nextTick(() => {
      if (!observer || !sentinel.value) return;
      observer.unobserve(sentinel.value);
      observer.observe(sentinel.value);
    }),
);

onMounted(async () => {
  if (props.autofocus) nextTick(() => searchInput.value?.$el?.focus());
  if (typeof IntersectionObserver !== "undefined" && sentinel.value) {
    observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting) && limit.value < results.value.length) {
          limit.value += PAGE_SIZE;
        }
      },
      { root: scroller.value, rootMargin: "200px" },
    );
    observer.observe(sentinel.value);
  }
  await load();
});

onBeforeUnmount(() => {
  observer?.disconnect();
  clearTimeout(debounceTimer);
});
</script>
