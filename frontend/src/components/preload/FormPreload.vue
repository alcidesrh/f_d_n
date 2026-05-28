<script setup lang="ts">
defineProps({
  rows: {
    type: Number,
    default: 10,
  },
  cols: {
    type: Number,
    default: 1,
  },
});
</script>

<template>
  <div class="m-auto w-80% sm:w-90% md:w-70% mt-100px">
    <div class="grid md:flex gap-3 justify-end md:justify-between items-center">
      <div class="flex justify-end">
        <q-skeleton type="text" height="33px" width="200px" />
      </div>
      <div>
        <ButtonPreload :cols="3" class="justify-end" />
      </div>
    </div>
    <div class="row gap-5 mt-10">
      <div v-for="(column, j) in Number(cols)" :key="j" class="col">
        <div v-for="(column, i) in Number(rows)" :key="i" mb-6>
          <q-skeleton type="text" class="" height="2rem" width="150px" mb-3 />
          <q-skeleton class="" height="2rem">
            <template #default></template>
          </q-skeleton>
        </div>
      </div>
    </div>
  </div>
</template>
