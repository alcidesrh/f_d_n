<!--
  Hoja de acciones que sube desde abajo (teléfono): las de un mensaje al
  mantenerlo pulsado y las de adjuntar. Cada acción cierra la hoja.
-->
<template>
  <Drawer :visible="visible" position="bottom" :show-close-icon="false" :block-scroll="true" class="hoja" :style="{ height: 'auto', maxHeight: '80dvh' }" :pt="{ header: { class: 'hoja__cabeza' }, content: { class: 'hoja__contenido' } }" @update:visible="emit('update:visible', $event)">
    <template #header>
      <span class="hoja__asa" aria-hidden="true" />
      <div v-if="titulo" class="hoja__titulo">{{ titulo }}</div>
    </template>
    <ul class="hoja__lista" role="menu">
      <li v-for="a in acciones" :key="a.etiqueta" role="none">
        <button type="button" role="menuitem" class="hoja__accion" :class="{ 'hoja__accion--peligro': a.peligro }" @click="elegir(a)">
          <span class="hoja__icono"><icon :name="a.icono" size="1.35rem" color="text-current" /></span>
          {{ a.etiqueta }}
        </button>
      </li>
    </ul>
  </Drawer>
</template>

<script setup lang="ts">
export interface AccionHoja {
  etiqueta: string
  icono: string
  accion: () => void
  peligro?: boolean
}

defineProps<{ visible: boolean; acciones: AccionHoja[]; titulo?: string }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean] }>()

function elegir(a: AccionHoja) {
  emit('update:visible', false)
  a.accion()
}
</script>

<style>
/* El alto va en línea: el del Drawer inferior de PrimeVue gana a una clase. */
.hoja.p-drawer {
  border-radius: 1.1rem 1.1rem 0 0;
}
.hoja .hoja__cabeza {
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.5rem 1rem 0.25rem;
}
.hoja .hoja__contenido {
  padding: 0 0.5rem calc(0.75rem + env(safe-area-inset-bottom));
}
.hoja__asa {
  width: 2.5rem;
  height: 0.3rem;
  border-radius: 999px;
  background: var(--p-surface-300);
}
.hoja__titulo {
  align-self: stretch;
  overflow: hidden;
  font-size: 0.82rem;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--p-surface-500);
}
.hoja__lista {
  margin: 0;
  padding: 0;
  list-style: none;
}
.hoja__accion {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  width: 100%;
  min-height: 3.25rem;
  padding: 0 0.75rem;
  border-radius: 0.75rem;
  font-size: 0.95rem;
  text-align: left;
  color: var(--p-surface-800);
  &:active,
  &:focus-visible {
    background: var(--p-surface-100);
  }
}
.hoja__accion--peligro {
  color: var(--c-danger);
}
.hoja__icono {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
}
</style>
