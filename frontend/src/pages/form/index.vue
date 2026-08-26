<template>
  <div>
    <!-- <PageHead
      title="FormKit + PrimeVue"
      subtitle="Custom inputs de PrimeVue v4 registrados como tipos de FormKit"
    /> -->
    <Tabs value="0">
      <TabList>
        <Tab value="0">Entity Form</Tab>
        <Tab value="1">Form Builder</Tab>
        <Tab value="2">Form Inputs</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="0">
          <EntityFormDemo />
        </TabPanel>
        <TabPanel value="1">
          <FormBuilder />
        </TabPanel>
        <TabPanel value="2">
          <FormInputs />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import type { TreeNode } from "primevue/treenode";
import EntityFormDemo from "./EntityFormDemo.vue";
import FormBuilder from "./FormBuilder.vue";
import FormInputs from "./FormInputs.vue";
const formData = ref<Record<string, unknown>>({ activo: true });
const submitted = ref<Record<string, unknown> | null>(null);

const onSubmit = (data: Record<string, unknown>) => {
  submitted.value = data;
};

const paises = [
  { name: "Guatemala", code: "GT" },
  { name: "España", code: "ES" },
  { name: "México", code: "MX" },
  { name: "Argentina", code: "AR" },
];

const intereses = [
  { label: "Tecnología", value: "tech" },
  { label: "Deportes", value: "sports" },
  { label: "Música", value: "music" },
  { label: "Cine", value: "movies" },
];

const prioridades = [
  { label: "Baja", value: "baja" },
  { label: "Media", value: "media" },
  { label: "Alta", value: "alta" },
];

const dias = [
  { label: "Lun", value: "mon" },
  { label: "Mar", value: "tue" },
  { label: "Mié", value: "wed" },
  { label: "Jue", value: "thu" },
  { label: "Vie", value: "fri" },
];

const ubicaciones = [
  {
    label: "América",
    children: [
      { label: "Guatemala", children: [{ label: "Ciudad de Guatemala" }, { label: "Antigua" }] },
      { label: "México", children: [{ label: "CDMX" }] },
    ],
  },
  {
    label: "Europa",
    children: [{ label: "España", children: [{ label: "Madrid" }, { label: "Barcelona" }] }],
  },
];

const generos = [
  { label: "Masculino", value: "M" },
  { label: "Femenino", value: "F" },
  { label: "Otro", value: "O" },
];

const archivos: TreeNode[] = [
  {
    key: "0",
    label: "Documentos",
    data: "Documentos",
    children: [
      { key: "0-0", label: "Facturas", data: "Facturas" },
      { key: "0-1", label: "Contratos", data: "Contratos" },
    ],
  },
  {
    key: "1",
    label: "Imágenes",
    data: "Imágenes",
    children: [
      { key: "1-0", label: "Logos", data: "Logos" },
      { key: "1-1", label: "Capturas", data: "Capturas" },
    ],
  },
];

const marcas = ["Mercedes", "BMW", "Audi", "Tesla", "Toyota", "Honda", "Ford"];
const sugerencias = ref<string[]>([]);

const buscarMarcas = (e: { query: string }) => {
  const query = e.query.trim().toLowerCase();
  sugerencias.value = query ? marcas.filter((m) => m.toLowerCase().includes(query)) : [...marcas];
};
</script>
