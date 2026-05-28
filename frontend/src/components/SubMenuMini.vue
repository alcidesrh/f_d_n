<template>

  <q-menu v-model="open" anchor="top right" self="top left">
    <q-list style="min-width: 100px">
      <template v-for="v, i in items" :key="i">
        <template v-if="v.children">
          <q-separator />
          <q-item clickable dense>
            <q-item-section @mouseover="flags[i] = !flags[i]">
              {{ v.label }}
            </q-item-section>
            <q-item-section side v-if="v.children">
              <icon name="keyboard_arrow_right" />
            </q-item-section>
            <SubMenuMini v-if="v.children" :toogle="flags[i]" :items="v.children" />
          </q-item>
          <q-separator />

        </template>

        <q-item v-else clickable dense>
          <q-item-section>
            {{ v.label }}
          </q-item-section>
        </q-item>
      </template>
    </q-list>
  </q-menu>

</template>

<script setup lang="ts">
import { vElementHover } from '@vueuse/components'
const props = defineProps({
  items: {
    type: Array<any>,
    default: null,
  },
  toogle: {
    type: Boolean,
    default: false,
  }
})
const open = ref(false);
watch(() => props.toogle, (v) => {
  open.value = !open.value
})
const flags = ref(Array.from({ length: props.items.length }, () => false))
</script>
`
