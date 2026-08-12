

<template>
  <div>
    <PageHead
      :title="current ? current.typeName : 'CRUD dinámico'"
      :subtitle="
        current
          ? `Entidad ${current.typeName} · paginación ${current.shape} · ${entities.length} entidades`
          : 'Selecciona una entidad para explorar'
      "
    >
      <Select
        :model-value="selectedEntity"
        :options="entities"
        option-label="typeName"
        option-value="typeName"
        placeholder="Entidad…"
        show-clear
        :loading="!schema"
        class="w-56"
        @update:model-value="selectedEntity = $event"
      />
    </PageHead>

    <div v-if="!schema" class="card text-sm text-muted-color py-8 text-center">
      Cargando schema…
    </div>

    <div v-else-if="!current" class="card bg-white p-8 shadow-sm border border-surface-300">
      <p class="text-sm text-muted-color mb-4">Elige una entidad para ver su CRUD dinámico.</p>
      <div class="flex flex-wrap gap-2">
        <Button
          v-for="e in entities"
          :key="e.typeName"
          :label="`${e.typeName} (${e.shape})`"
          size="small"
          @click="goEntity(e.typeName)"
        />
      </div>
    </div>

    <template v-else>
      <AgnosticEntityList
        :key="current.typeName"
        :entity="current.typeName"
        :schema="schema"
        @create="openCreate"
        @edit="openEdit"
      />
    </template>

    <Dialog
      v-model:visible="dialogOpen"
      :header="dialogHeader"
      modal
      :style="{ width: 'min(760px, 96vw)' }"
      @hide="closeDialog"
    >
      <AgnosticEntityForm
        v-if="current && dialogState && schema"
        :key="`${current.typeName}-${dialogState}-${editingItem?.id ?? 'new'}`"
        :entity="current.typeName"
        :schema="schema"
        :mode="dialogState"
        :item="editingItem"
        @saved="closeDialog"
        @cancel="closeDialog"
      />
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useOrm } from "@/features/crud/composables/use-orm.ts";
import { listEntities } from "@/features/crud/services/list-entities";
import AgnosticEntityList from "../components/AgnosticEntityList.vue";
import AgnosticEntityForm from "../components/AgnosticEntityForm.vue";
import type { FormMode } from "@/features/crud/types";

const route = useRoute();
const router = useRouter();

const ctx = useOrm();

const schema = computed(() => ctx.schema.value);
const entities = computed(() => (schema.value ? listEntities(schema.value) : []));

const entity = computed(() => (route.params.entity as string | undefined) ?? "");
const current = computed(() => entities.value.find((e) => e.typeName === entity.value) ?? null);

const selectedEntity = computed({
  get: () => entity.value,
  set: (v: string | null | undefined) => router.push(v ? `/agnostic/${v}` : "/agnostic"),
});

const dialogOpen = ref(false);
const dialogState = ref<FormMode | null>(null);
const editingItem = ref<Record<string, unknown> | null>(null);

const dialogHeader = computed(() => {
  if (!current.value) return "";
  return dialogState.value === "create"
    ? `Nuevo ${current.value.typeName}`
    : `Editar ${current.value.typeName}`;
});

function goEntity(typeName: string) {
  router.push(`/agnostic/${typeName}`);
}

function openCreate() {
  editingItem.value = null;
  dialogState.value = "create";
  dialogOpen.value = true;
}

function openEdit(row: Record<string, unknown>) {
  editingItem.value = row;
  dialogState.value = "update";
  dialogOpen.value = true;
}

function closeDialog() {
  dialogOpen.value = false;
  dialogState.value = null;
  editingItem.value = null;
}
</script>
