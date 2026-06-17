<template>
  <div class="stats-card" :style="{ '--accent': color }">
    <div class="stats-card__header">
      <div class="stats-card__icon-wrap">
        <Icon :name="icon" />
      </div>
      <span class="stats-card__value">{{ value }}</span>
    </div>
    <div class="stats-card__footer">
      <span class="stats-card__label">{{ label }}</span>
      <span v-if="trend" class="stats-card__trend" :class="`trend--${trend}`">
        <Icon :name="trendIcon" />
        {{ percentage }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  label: string
  value: string
  icon: string
  trend?: 'up' | 'down' | 'neutral'
  percentage?: string
  color: string
}>()

const trendIcon = computed(() => {
  if (!props.trend || props.trend === 'neutral') return 'trending_flat'
  return props.trend === 'up' ? 'trending_up' : 'trending_down'
})
</script>

<style scoped lang="scss">
.stats-card {
  background: #fff;
  border: 1px solid $surface-4;
  border-radius: 12px;
  padding: 1.25rem;
  transition: box-shadow 0.2s, transform 0.2s;

  &:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
    transform: translateY(-2px);
  }

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
  }

  &__icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    color: var(--accent);
    font-size: 1.5rem;
  }

  &__value {
    font-size: 1.75rem;
    font-weight: 700;
    color: $dark;
    line-height: 1.2;
  }

  &__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__label {
    font-size: 0.875rem;
    color: $surface-6;
  }

  &__trend {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.8rem;
    font-weight: 600;

    &.trend--up { color: $positive; }
    &.trend--down { color: $negative; }
    &.trend--neutral { color: $surface-6; }
  }
}
</style>
