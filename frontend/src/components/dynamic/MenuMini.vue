<template>
  <div
    v-for="(v, i) in items"
    :key="i"
    class="flex justify-center my-20px cursor-pointer"
  >
    <icon
      :name="v.icon"
      wght="600"
      class="surface-5"
      @mouseover="flags[i] = !flags[i]"
    >
    </icon>
    <SubMenuMini v-if="v.children" :toogle="flags[i]" :items="v.children" />
  </div>
</template>

<script setup lang="ts">
import { vElementHover } from "@vueuse/components";
const props = defineProps({
  items: {
    type: Array<any>,
    default: null,
  },
});
const flags = ref(Array.from({ length: props.items.length }, () => false));
</script>
`
