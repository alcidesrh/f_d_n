<template>
  <div class=" bg-white p-[2rem] py-[4rem] border border-surface-300">
    <PageHead title="Form Builder" subtitle="Grids responsivos + custom inputs → JSON FormKitSchema" />

    <div class="card">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <span class="text-xs text-surface-500">
          Click en una celda para seleccionarla · los bordes punteados son solo de esta vista previa
        </span>
        <Button class="cursor-pointer"
          v-if="store.root"
          label="Vaciar todo"
          icon="pi pi-times"
          severity="danger"
          variant="text"
          size="small"
          @click="onClearAll"
        />
      </div>

      <Message v-if="!store.root" severity="info" :closable="false">
        Crea el grid raíz desde el panel derecho (filas × columnas).
      </Message>

      <div v-else class="fb-preview">
        <FormKit type="form" :actions="false">
          <FormKitSchema :schema="store.previewSchema" />
        </FormKit>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'FormBuilderPage' })

const store = useFormBuilderStore()

function onClearAll() {
  store.clearAll()
}
</script>

<!--
  Estilos NO scoped: los nodos los renderiza FormKitSchema fuera del árbol
  de scope ids de este componente. Namespaced bajo `.fb-preview`.
-->
<style>
.fb-preview .fb-cell {
  position: relative;
  min-height: 76px;
  padding: 0.75rem;
  border: 1px dashed var(--p-surface-500);
  border-radius: 8px;
  cursor: pointer;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.fb-preview .fb-cell:hover {
  border-color: var(--p-primary-color);
}

.fb-preview .fb-cell--selected {
  border-color: var(--p-primary-color);
  border-style: solid;
  box-shadow: inset 0 0 0 1px var(--p-primary-color);
}

.fb-preview .fb-cell--empty::after {
  content: '+';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--p-surface-500);
  font-size: 1.25rem;
  pointer-events: none;
}
</style>
