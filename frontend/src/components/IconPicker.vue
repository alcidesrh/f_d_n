<template>
	<div class="icon-picker">
		<div class="icon-picker__toolbar">
			<q-input v-model="searchTerm" outlined dense placeholder="Buscar iconos..." debounce="200" class="icon-picker__search" clearable @clear="searchTerm = ''">
				<template v-slot:prepend>
					<icon name="search" />
				</template>
			</q-input>
			<q-btn v-if="modelValue" flat round icon="sym_o_close" size="sm" @click="clearSelection" title="Limpiar selección" />
		</div>

		<div class="icon-picker__categories" ref="categoriesRef">
			<q-chip
				:selected="activeCategory === null"
				@click="activeCategory = null"
				size="sm"
				outline
				clickable
				color="primary"
				:text-color="activeCategory === null ? 'white' : 'primary'"
			>
				Todas
			</q-chip>
			<q-chip
				v-for="cat in categories"
				:key="cat.id"
				:selected="activeCategory === cat.name"
				@click="activeCategory = cat.name"
				size="sm"
				outline
				clickable
				color="primary"
				:text-color="activeCategory === cat.name ? 'white' : 'primary'"
			>
				{{ cat.name }}
			</q-chip>
		</div>

		<div v-if="loading" class="icon-picker__loading">
			<q-spinner color="primary" size="2rem" />
			<span>Cargando iconos...</span>
		</div>

		<div v-else-if="filteredIcons.length === 0" class="icon-picker__empty">
			<icon name="search_off" :opsz="48" />
			<p>No se encontraron iconos</p>
		</div>

		<div v-else class="icon-picker__grid-container" ref="gridContainer">
			<div class="icon-picker__grid">
				<div
					v-for="icon in filteredIcons"
					:key="icon.id"
					class="icon-picker__cell"
					:class="{ 'icon-picker__cell--selected': isSelected(icon) }"
					@click="selectIcon(icon)"
					:title="icon.name"
				>
					<icon :name="icon.codigo" :opsz="24" wght="300" />
					<span class="icon-picker__label">{{ icon.codigo }}</span>
				</div>
			</div>
		</div>

		<div v-if="selectedIcon" class="icon-picker__preview">
			<div class="icon-picker__preview-icon">
				<icon :name="selectedIcon.codigo" :opsz="48" wght="400" />
			</div>
			<div class="icon-picker__preview-info">
				<strong>{{ selectedIcon.codigo }}</strong>
				<small>ID: {{ selectedIcon.id }}</small>
			</div>
			<q-btn flat round icon="sym_o_close" size="sm" @click="clearSelection" />
		</div>
	</div>
</template>

<script setup lang="ts">
	import { useIcons, type IconItem } from '@/composables/useIcons'

	const props = defineProps<{
		modelValue?: string | number | null
		cols?: number
	}>()

	const emit = defineEmits<{
		'update:modelValue': [value: string | number | null]
	}>()

	const { icons, categories, loading, loadAll, searchIcons } = useIcons()

	const searchTerm = ref('')
	const activeCategory = ref<string | null>(null)
	const selectedIconId = ref<string | number | null>(props.modelValue ?? null)
	const gridContainer = ref<HTMLElement | null>(null)

	const cols = computed(() => props.cols ?? 6)

	const filteredIcons = computed(() => {
		return searchIcons(searchTerm.value, activeCategory.value ?? undefined)
	})

	const selectedIcon = computed(() => {
		if (selectedIconId.value === null) return null
		return icons.value.find((i) => i.id === String(selectedIconId.value)) ?? null
	})

	function isSelected(icon: IconItem): boolean {
		return String(selectedIconId.value) === icon.id
	}

	function selectIcon(icon: IconItem) {
		selectedIconId.value = icon.id
		emit('update:modelValue', icon.id)
	}

	function clearSelection() {
		selectedIconId.value = null
		emit('update:modelValue', null)
	}

	onMounted(async () => {
		await loadAll()
	})

	watch(
		() => props.modelValue,
		(val) => {
			selectedIconId.value = val ?? null
		},
	)
</script>

<style scoped lang="scss">
	.icon-picker {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		border: 1px solid $surface-4;
		border-radius: 8px;
		padding: 0.75rem;
		background: #fff;
		max-height: 520px;

		&__toolbar {
			display: flex;
			align-items: center;
			gap: 0.5rem;

			&__search {
				flex: 1;
			}
		}

		&__categories {
			display: flex;
			gap: 0.25rem;
			overflow-x: auto;
			padding-bottom: 0.25rem;
			flex-shrink: 0;

			&::-webkit-scrollbar {
				height: 4px;
			}
			&::-webkit-scrollbar-thumb {
				background: $surface-4;
				border-radius: 2px;
			}
		}

		&__grid-container {
			flex: 1;
			min-height: 0;
			overflow-y: auto;
			border-radius: 4px;

			&::-webkit-scrollbar {
				width: 6px;
			}
			&::-webkit-scrollbar-thumb {
				background: $surface-4;
				border-radius: 3px;
			}
		}

		&__grid {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 0.25rem;
			padding: 0.25rem 0;
		}

		&__cell {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 0.125rem;
			padding: 0.375rem 0.125rem;
			border-radius: 6px;
			cursor: pointer;
			transition:
				background 0.15s,
				box-shadow 0.15s;
			min-height: 72px;

			&:hover {
				background: $surface-1;
			}

			&--selected {
				background: color-mix(in srgb, $primary 12%, transparent);
				box-shadow: inset 0 0 0 2px $primary;
			}
		}

		&__label {
			font-size: 0.6rem;
			color: $surface-6;
			text-align: center;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			max-width: 100%;
			line-height: 1.1;
		}

		&__loading {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.75rem;
			padding: 2rem;
			color: $surface-6;
		}

		&__empty {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 2rem;
			color: $surface-5;
			gap: 0.5rem;

			p {
				margin: 0;
				font-size: 0.875rem;
			}
		}

		&__preview {
			display: flex;
			align-items: center;
			gap: 0.75rem;
			padding: 0.5rem 0.75rem;
			border-top: 1px solid $surface-3;
			flex-shrink: 0;

			&-icon {
				color: $primary;
			}

			&-info {
				flex: 1;
				display: flex;
				flex-direction: column;

				strong {
					font-size: 0.875rem;
					text-transform: lowercase;
				}
				small {
					font-size: 0.7rem;
					color: $surface-6;
				}
			}
		}
	}
</style>
