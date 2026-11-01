<!--
  Destinatarios como fichas: se escribe un nombre o una dirección y se elige
  de la lista (cuentas de la casa con quienes puede hablar y direcciones de
  fuera ya usadas) o se confirma con Enter, coma o al salir del campo.
  Retroceso en el campo vacío quita la última. Pegar una lista también vale.
-->
<template>
  <div class="campo" :class="{ 'campo--foco': foco }" @click="entrada?.focus()">
    <span class="campo__etiqueta">{{ etiquetaCampo }}</span>
    <span class="fichas">
      <span v-for="d in modelValue" :key="d.direccion" class="ficha" :class="{ 'ficha--fuera': !esDeLaCasa(d.direccion) }" :title="d.direccion">
        <span class="ficha__texto">{{ etiqueta(d) }}</span>
        <button type="button" class="ficha__quitar" :aria-label="`Quitar ${d.direccion}`" @click.stop="quitar(d.direccion)"><icon name="close" size=".8rem" color="text-current" /></button>
      </span>
      <input
        ref="entrada"
        v-model="texto"
        type="text"
        class="campo__entrada"
        :aria-label="etiquetaCampo"
        autocomplete="off"
        spellcheck="false"
        @focus="foco = true"
        @blur="alSalir"
        @keydown="alTeclear"
        @paste="alPegar"
      />
    </span>
    <slot />
    <ul v-if="foco && sugerencias.length" class="sugerencias" role="listbox">
      <li v-for="(s, i) in sugerencias" :key="s.direccion" role="option" :aria-selected="i === elegida" class="sugerencia" :class="{ 'sugerencia--activa': i === elegida }" @mousedown.prevent="agregar(s)" @mouseenter="elegida = i">
        <ChatAvatar :id="tono(s.direccion)" :nombre="etiqueta(s)" tamano="sm" />
        <span class="min-w-0 flex-1">
          <span class="sugerencia__nombre">{{ etiqueta(s) }}</span>
          <span class="sugerencia__dir">{{ s.direccion }}</span>
        </span>
        <span v-if="!s.interno" class="externo">Fuera</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { fetchContactos } from "@/core/correo/api";
import { etiqueta, leerDirecciones } from "@/core/correo/modelo";
import type { Contacto, Direccion } from "@/core/correo/types";
import ChatAvatar from "@/shared/chat/ChatAvatar.vue";

const props = defineProps<{ modelValue: Direccion[]; etiquetaCampo: string; dominio: string }>();
const emit = defineEmits<{ "update:modelValue": [valor: Direccion[]] }>();

const entrada = ref<HTMLInputElement | null>(null);
const texto = ref("");
const foco = ref(false);
const sugerencias = ref<Contacto[]>([]);
const elegida = ref(0);

const esDeLaCasa = (direccion: string) => direccion.endsWith(`@${props.dominio}`);

let espera: ReturnType<typeof setTimeout> | undefined;
let pedido = 0;
watch(texto, (q) => {
  clearTimeout(espera);
  const t = q.trim();
  if (!t) {
    sugerencias.value = [];
    return;
  }
  espera = setTimeout(async () => {
    const n = ++pedido;
    const r = await fetchContactos(t).catch(() => []);
    if (n !== pedido) return;
    const ya = new Set(props.modelValue.map((d) => d.direccion));
    sugerencias.value = r.filter((c) => !ya.has(c.direccion)).slice(0, 8);
    elegida.value = 0;
  }, 200);
});
onUnmounted(() => clearTimeout(espera));

function agregar(...nuevas: Direccion[]) {
  const ya = new Set(props.modelValue.map((d) => d.direccion));
  const sumar = nuevas.filter((d) => !ya.has(d.direccion)).map((d) => ({ direccion: d.direccion, nombre: d.nombre }));
  if (sumar.length) emit("update:modelValue", [...props.modelValue, ...sumar]);
  texto.value = "";
  sugerencias.value = [];
}

function quitar(direccion: string) {
  emit("update:modelValue", props.modelValue.filter((d) => d.direccion !== direccion));
}

/** Confirma lo escrito si es una dirección; lo que no lo es se queda en el campo. */
function confirmar(): boolean {
  if (!texto.value.trim()) return false;
  const { direcciones, resto } = leerDirecciones(texto.value);
  if (direcciones.length) agregar(...direcciones);
  texto.value = resto;
  return direcciones.length > 0;
}

function alTeclear(e: KeyboardEvent) {
  if (e.key === "ArrowDown" && sugerencias.value.length) {
    e.preventDefault();
    elegida.value = (elegida.value + 1) % sugerencias.value.length;
  } else if (e.key === "ArrowUp" && sugerencias.value.length) {
    e.preventDefault();
    elegida.value = (elegida.value - 1 + sugerencias.value.length) % sugerencias.value.length;
  } else if (e.key === "Enter" || e.key === "Tab" || e.key === "," || e.key === ";") {
    const s = sugerencias.value[elegida.value];
    if (s && e.key !== ",") {
      e.preventDefault();
      agregar(s);
    } else if (texto.value.trim()) {
      if (confirmar() || e.key !== "Tab") e.preventDefault();
    }
  } else if (e.key === "Backspace" && !texto.value && props.modelValue.length) {
    quitar(props.modelValue.at(-1)!.direccion);
  } else if (e.key === "Escape") {
    sugerencias.value = [];
  }
}

function alPegar(e: ClipboardEvent) {
  const pegado = e.clipboardData?.getData("text") ?? "";
  if (!/[,;\n]/.test(pegado) && !/@/.test(pegado)) return;
  e.preventDefault();
  texto.value = `${texto.value}${pegado}`;
  confirmar();
}

function alSalir() {
  foco.value = false;
  confirmar();
}

function tono(direccion: string): number {
  let h = 0;
  for (const c of direccion) h = (h * 31 + c.charCodeAt(0)) | 0;
  return Math.abs(h);
}

defineExpose({ enfocar: () => entrada.value?.focus(), confirmar });
</script>

<style scoped>
.campo {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  min-height: 2.6rem;
  padding: 0.35rem 0;
  border-bottom: 1px solid var(--p-surface-200);
  cursor: text;
}
.campo--foco {
  border-bottom-color: color-mix(in srgb, var(--p-primary-color) 60%, transparent);
}
.campo__etiqueta {
  flex: none;
  width: 2.6rem;
  padding-top: 0.4rem;
  font-size: 0.82rem;
  color: var(--p-surface-500);
}
.fichas {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.3rem;
  min-width: 0;
  min-height: 1.9rem;
}
.ficha {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  max-width: 100%;
  height: 1.65rem;
  padding: 0 0.15rem 0 0.6rem;
  border-radius: 999px;
  font-size: 0.8rem;
  color: var(--p-surface-800);
  background: var(--p-surface-100);
  border: 1px solid var(--p-surface-200);
}
.ficha--fuera {
  background: #fff7ed;
  border-color: #fed7aa;
}
.ficha__texto {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ficha__quitar {
  display: grid;
  place-items: center;
  width: 1.3rem;
  height: 1.3rem;
  border-radius: 50%;
  color: var(--p-surface-500);
  cursor: pointer;
  &:hover {
    color: var(--p-surface-900);
    background: var(--p-surface-200);
  }
}
.campo__entrada {
  flex: 1;
  min-width: 8rem;
  height: 1.8rem;
  border: 0;
  outline: 0;
  font-size: 0.875rem;
  color: var(--p-surface-900);
  background: transparent;
}
.sugerencias {
  position: absolute;
  top: calc(100% + 2px);
  left: 2.6rem;
  right: 0;
  z-index: 20;
  max-height: 18rem;
  margin: 0;
  padding: 0.3rem;
  overflow-y: auto;
  list-style: none;
  border-radius: 0.8rem;
  background: var(--p-content-background);
  box-shadow: 0 12px 32px rgb(0 0 0 / 0.16), 0 0 0 1px var(--p-surface-200);
}
.sugerencia {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.4rem 0.5rem;
  border-radius: 0.55rem;
  cursor: pointer;
}
.sugerencia--activa {
  background: var(--p-surface-100);
}
.sugerencia__nombre,
.sugerencia__dir {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sugerencia__nombre {
  font-size: 0.85rem;
  color: var(--p-surface-900);
}
.sugerencia__dir {
  font-size: 0.75rem;
  color: var(--p-surface-500);
}
.externo {
  flex: none;
  padding: 0 0.35rem;
  border-radius: 0.3rem;
  font-size: 0.62rem;
  font-weight: 600;
  line-height: 1.1rem;
  text-transform: uppercase;
  color: #9a3412;
  background: #ffedd5;
}
</style>
