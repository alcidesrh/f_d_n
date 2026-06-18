<template>
	<div class="entity-card">
		<div class="entity-card__icon">
			<icon :name="icon_name" fill />
		</div>
		<span class="entity-card__name">{{ entity.name }}</span>
		<span v-if="entity.fields" class="entity-card__count">{{ Object.keys(entity.fields).length }} campos</span>
		<div class="flex gap-x-3 mt-10px justify-center w-full">
			<icon name="search" @click="$router.push({ name: 'list', params: { entity: entity.name } })" />
			<Icon name="add" @click="$router.push({ name: 'form', params: { entity: entity.name } })"></Icon>
			<Icon
				name="settings"
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
	import type { Entity } from '@/types/graphql'

	defineProps<{
		entity: Entity
		icon_name: string
	}>()
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
			box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
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
