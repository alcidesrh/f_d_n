<script setup lang="ts">
withDefaults(defineProps<{ title: string; subtitle?: string }>(), { subtitle: "" });
</script>

<template>
  <div class="page-head">
    <div>
      <h1 class="page-title">{{ title }}</h1>
      <p v-if="subtitle" class="page-sub">{{ subtitle }}</p>
    </div>
    <div class="flex gap-10">
      <Card style="width: 25rem; overflow: hidden">
        <template #header>
          <img alt="user header" src="https://primefaces.org/cdn/primevue/images/usercard.png" />
        </template>
        <template #title>Advanced Card</template>
        <template #subtitle>Card subtitle</template>
        <template #content>
          <p class="m-0">
            Lorem ipsum dolor sit amet, consectetur adipisicing elit. Inventore sed consequuntur
            error repudiandae numquam deserunt quisquam repellat libero asperiores earum nam nobis,
            culpa ratione quam perferendis esse, cupiditate neque quas!
          </p>
        </template>
        <template #footer>
          <div class="flex gap-4 mt-1">
            <Button label="Cancel" severity="secondary" variant="outlined" class="w-full" />
            <Button label="Save" class="w-full" />
          </div>
        </template>
      </Card>
      <Card style="width: 25rem; overflow: hidden">
        <template #header>
          <img alt="user header" src="https://primefaces.org/cdn/primevue/images/usercard.png" />
        </template>
        <template #title>Advanced Card</template>
        <template #subtitle>Card subtitle</template>
        <template #content>
          <p class="m-0">
            Lorem ipsum dolor sit amet, consectetur adipisicing elit. Inventore sed consequuntur
            error repudiandae numquam deserunt quisquam repellat libero asperiores earum nam nobis,
            culpa ratione quam perferendis esse, cupiditate neque quas!
          </p>
        </template>
        <template #footer>
          <div class="flex gap-4 mt-1">
            <Button label="Cancel" severity="secondary" variant="outlined" class="w-full" />
            <Button label="Save" class="w-full" />
          </div>
        </template>
      </Card>
      <Card style="width: 25rem; overflow: hidden">
        <template #header>
          <img alt="user header" src="https://primefaces.org/cdn/primevue/images/usercard.png" />
        </template>
        <template #title>Advanced Card</template>
        <template #subtitle>Card subtitle</template>
        <template #content>
          <p class="m-0">
            Lorem ipsum dolor sit amet, consectetur adipisicing elit. Inventore sed consequuntur
            error repudiandae numquam deserunt quisquam repellat libero asperiores earum nam nobis,
            culpa ratione quam perferendis esse, cupiditate neque quas!
          </p>
        </template>
        <template #footer>
          <div class="flex gap-4 mt-1">
            <Button label="Cancel" severity="secondary" variant="outlined" class="w-full" />
            <Button label="Save" class="w-full" />
          </div>
        </template>
      </Card>
    </div>
    <div class="page-actions">
      <slot />
    </div>
  </div>
</template>
