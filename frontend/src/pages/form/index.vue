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
import EntityFormDemo from "./EntityFormDemo.vue";
import FormBuilder from "./FormBuilder.vue";
import FormInputs from "./FormInputs.vue";
</script>
