<script setup lang="ts">
defineProps({
  rows: {
    type: Number,
    default: 1,
  },
  cols: {
    type: Number,
    default: 1,
  },
});
</script>

<template>
  <div class="flex gap-5">
    <q-skeleton
      type="QBtn"
      v-for="(column, i) in Number(cols * rows)"
      :key="i"
      class="max-w-107px"
      height="2rem"
      width="150px"
    />
  </div>
</template>
