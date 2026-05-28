<template>
  <q-btn v-if="btn" flat round :icon="`sym_o_${name}`"> </q-btn>

  <span
    v-else-if="!quasar"
    :class="[`fdn-icon cursor-pointer  material-symbols-${type}`]"
    :style="style"
    >{{ name }}
    <slot name="default"></slot>
  </span>

  <q-icon v-else :name="name" tag="span" :left="left"></q-icon>
</template>
<script setup lang="ts">
const props = defineProps({
  flat: {
    type: Boolean,
    default: false,
  },
  btn: {
    type: Boolean,
    default: false,
  },
  quasar: {
    type: Boolean,
    default: false,
  },
  left: {
    type: Boolean,
    default: false,
  },
  right: {
    type: Boolean,
    default: false,
  },
  name: {
    type: String,
    required: true,
  },
  fill: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: "outlined", //rounded and sharp
  },

  opsz: {
    type: Number,
    default: 24, //20 to 48
  },
  grad: {
    type: Number,
    default: 0, //-25 to 200
  },
  wght: {
    type: String, //100 to 700
    required: false,
  },
  clases: {
    type: Array,
    default: [],
  },
});

let style = {},
  name = ref("");
if (!props.quasar) {
  const { fill, grad, opsz, wght, type, flat } = props;
  name.value = computed(() => props.name);
  style = computed(() => {
    const temp = fill ? 1 : 0;
    const style = {
      fontVariationSettings: `'FILL' ${temp}, 'GRAD' ${grad}, 'opsz' ${opsz}`,
    };
    if (wght) {
      style.fontVariationSettings += `, 'wght' ${wght}`;
    }
    return style;
  });
} else {
  name = computed(() => (props.quasar ? "sym_o_" + props.name : props.name));
}
</script>
