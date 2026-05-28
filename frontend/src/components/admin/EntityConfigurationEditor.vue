<template>
  <div class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h6">
        Configuración: {{ entityClass.split("\\").pop() }}
      </div>
      <q-space />
      <q-btn flat icon="sym_o_close" @click="emit('close')" />
    </div>

    <q-tabs v-model="tab" align="left" class="q-mb-md">
      <q-tab name="list" label="Listado" />
      <q-tab name="form" label="Formulario" />
    </q-tabs>

    <q-tab-panels v-model="tab" animated class="">
      <q-tab-panel name="list">
        <CollectionFieldEditor
          v-if="collectionFieldConfig.length"
          :fields="collectionFieldConfig"
          @update:fields="collectionFieldConfig = $event"
        />
      </q-tab-panel>
      <q-tab-panel name="form">
        <FormFieldsEditor
          :fields="formFields"
          @update:fields="formFields = $event"
        />
      </q-tab-panel>
    </q-tab-panels>

    <q-card-actions align="right" class="q-mt-lg">
      <q-btn flat color="negative" @click="emit('close')">Cancelar</q-btn>
      <q-btn
        color="positive"
        icon="sym_o_save"
        label="Guardar Cambios"
        @click="save"
      />
    </q-card-actions>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useEntityConfig } from "src/composables/useEntityConfig";
import CollectionFieldEditor from "./CollectionFieldEditor.vue";
import FormFieldsEditor from "./FormFieldsEditor.vue";

const props = defineProps<{ configId: number }>();
const emit = defineEmits(["close"]);

const { fetchConfig, saveConfig } = useEntityConfig();
const tab = ref<"list" | "form">("list");

const entityClass = ref<string>("");
const configIdInternal = ref<number>(0);
const collectionFieldConfig = ref<any[]>([]);
const formFields = ref<any[]>([]);

onMounted(async () => {
  const data = await fetchConfig(props.configId);
  configIdInternal.value = data.id;
  entityClass.value = data.entityClass;
  collectionFieldConfig.value = [...data.collectionFieldConfig].sort(
    (a, b) => a.position - b.position,
  );
  formFields.value = [...data.formFields].sort(
    (a, b) => a.position - b.position,
  );
});

const save = async () => {
  collectionFieldConfig.value.forEach((f, i) => (f.position = i + 1));
  formFields.value.forEach((f, i) => (f.position = i + 1));

  await saveConfig(
    configIdInternal.value,
    collectionFieldConfig.value,
    formFields.value,
  );
  emit("close");
};
</script>
