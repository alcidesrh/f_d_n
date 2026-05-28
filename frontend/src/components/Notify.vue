<template></template>
<script setup lang="ts">
import { useQuasar } from "quasar";

const $q = useQuasar();
function show(arg) {
  const temp = {
    type: arg.type,
    multiLine: true,
    textColor: "surface-900",
    // textColor: arg.color,
    actions: [
      {
        icon: "sym_o_close",
        color: "dark",
        round: true,
        handler: () => {
          /* ... */
        },
      },
    ],
  };
  if (typeof arg == "object") {
    $q.notify({
      ...temp,
      ...arg,
      ...(typeof arg.message == "object" ? arg.message : {}),
    });
    return;
  }
  $q.notify({ ...temp, message: arg });
}

bus.on("error", (msg) => {
  show({
    message: msg,
    type: "negative",
    timeout: 0,
  });
});
bus.on("positive", (msg) => {
  show({
    message: msg,
    type: "positive",
    // timeout: 0,
  });
});

bus.on("info", (msg) => {
  show({
    message: msg,
    type: "info",
  });
});
</script>
