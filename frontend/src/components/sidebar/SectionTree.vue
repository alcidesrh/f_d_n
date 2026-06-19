<template>
	<div class="tree-node" :style="{ paddingLeft: depth * 12 + 'px' }">
		<!-- Section header (clickable if has children, or action item) -->
		<div
			v-if="item.children"
			class="tree-parent"
			:class="{ expanded: expanded }"
			@click="expanded = !expanded"
		>
			<icon :name="item.icon || 'star'" class="node-icon" />
			<span class="node-label">{{ item.label }}</span>
			<icon name="chevron_right" class="chevron" />
		</div>

		<div v-else class="tree-leaf" @click="handleClick">
			<icon :name="item.icon || 'star'" class="node-icon" />
			<span class="node-label">{{ item.label }}</span>
		</div>

		<!-- Children (recursive) -->
		<div v-if="item.children && expanded" class="tree-children">
			<SectionTree
				v-for="(child, i) in item.children"
				:key="i"
				:item="child"
				:depth="depth + 1"
				@action="(name: string) => emit('action', name)"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
	import type { Seccion } from '@/types/seccion'

	const props = defineProps<{
		item: Seccion
		depth?: number
	}>()

	const emit = defineEmits<{
		action: [name: string]
	}>()

	const router = useRouter()
	const expanded = ref(false)
	const depth = props.depth ?? 0

	function handleClick() {
		if (props.item.to) {
			router.push(props.item.to)
		} else {
			emit('action', props.item.name)
		}
	}
</script>

<style scoped lang="scss">
	.tree-node {
		.tree-parent,
		.tree-leaf {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			padding: 0.45rem 0.5rem;
			border-radius: 6px;
			cursor: pointer;
			transition: background 0.15s;

			&:hover {
				background: $surface-2;
			}

			.node-icon {
				font-size: 18px;
				color: $surface-6;
				flex-shrink: 0;
			}

			.node-label {
				font-size: 0.82rem;
				color: $surface-7;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}

		.tree-parent {
			.chevron {
				margin-left: auto;
				font-size: 16px;
				color: $surface-5;
				transition: transform 0.2s;
			}

			&.expanded .chevron {
				transform: rotate(90deg);
			}
		}

		.tree-children {
			border-left: 1px solid $surface-3;
			margin-left: 0.6rem;
			padding-left: 0.25rem;
			overflow: hidden;
		}
	}
</style>
