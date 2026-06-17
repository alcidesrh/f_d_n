<template>
	<div class="entity-card">
		<div class="entity-card__icon">
			<Icon :name="icon" fill />
		</div>
		<span class="entity-card__name">{{ entity.name }}</span>
		<span v-if="entity.fields" class="entity-card__count">{{ entity.fields.length }} campos</span>
		<div class="flex gap-x-5 mt-10px justify-center w-full">
			<Icon name="format_list_bulleted" @click="$router.push({ name: 'list', params: { entity: entity.name } })" />
			<Icon name="list_alt_add" @click="$router.push({ name: 'form', params: { entity: entity.name } })"></Icon>
			<Icon
				name="settings"
				@click="
					$router.push({
						name: 'entity_config',
						params: { entity: entity.name, action: 'editar' },
					})
				"
			></Icon>
			<!-- <q-btn flat round icon="sym_o_list_alt_add" size="lg" @click="$router.push({ name: 'form', params: { entity: entity.name } })">
				<q-tooltip anchor="top middle" self="bottom middle">Crear</q-tooltip>
			</q-btn>
			<q-btn flat round icon="sym_o_settings" size="lg" @click="$router.push({ name: 'form', params: { entity: entity.name } })">
				<q-tooltip anchor="top middle" self="bottom middle">Crear</q-tooltip>
			</q-btn> -->
		</div>
	</div>
</template>

<script setup lang="ts">
	import type { Entity } from '@/types/graphql'

	defineProps<{
		entity: Entity
		icon: string
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
