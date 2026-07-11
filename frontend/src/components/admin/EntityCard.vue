<template>
  <div class="entity-card">
    <div class="entity-card__icon">
      <icon :name="icon_name" fill />
    </div>
    <div class="h-33px">
      <div class="entity-card__name">{{ entity.name }}</div>
    <div v-if="recordCount != null" class="entity-card__count mt-5px"
      >{{ recordCount.toLocaleString("es-BO") }} registros</div
    >
    <div v-else-if="loading" class="entity-card__count">cargando...</div>
    </div>
    <div class="entity-card__action flex gap-x-3 mt-10px justify-around w-full max-w-150px">
      <icon
        name="reorder"
        class="hover"
        @click="$router.push({ name: 'list', params: { entity: entity.name } })"
      />
      <Icon
        name="docs_add_on"
        class="hover"
        @click="$router.push({ name: 'form', params: { entity: entity.name } })"
      ></Icon>
      <Icon
        name="settings"
        class="hover"
        @click="
          $router.push({
            name: 'entity_config',
            params: { entity: entity.name, action: 'editar' },
          })
        "
      ></Icon>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Entity } from "@/types/graphql";

defineProps<{
  entity: Entity;
  icon_name: string;
  recordCount?: number | null;
  loading?: boolean;
}>();
</script>

<style scoped lang="scss">
.entity-card {
  background: #fff;
  border: 1px solid $surface-4;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
  transition:
    box-shadow 0.2s,
    transform 0.2s;
  cursor: default;

  &:hover {
    box-shadow: 0 4px 16px 2px $surface-3;
    transform: translateY(-2px);
  }

  &__icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, $primary 10%, transparent);
    color: $primary;
    font-size: 1.75rem;
  }

  &__name {
    font-weight: 600;
    font-size: 1rem;
    color: $dark;
  }
  &__action {
    & > .hover {
      padding: 0.25rem;
      border-radius: 6px;
      // cursor: pointer;
      color: $surface-6;
      // transition: color 0.2s;
      // &:hover {
        // color: $primary;
      // }
    }
  }

  &__count {
    font-size: 0.75rem;
    color: $surface-6;
  }

  &__actions {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.25rem;
  }
}
</style>
