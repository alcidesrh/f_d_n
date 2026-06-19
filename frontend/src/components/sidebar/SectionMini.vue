<template>
	<div class="mini-items">
		<template v-for="(item, i) in items" :key="i">
			<!-- Item with children: hover opens menu -->
			<div
				v-if="item.children"
				class="mini-item"
				@mouseover="openMenu(i)"
				@mouseleave="closeMenu(i)"
			>
				<q-menu
					:model-value="menuStates[i]"
					@update:model-value="(v) => menuStates[i] = v"
					anchor="top right"
					self="top left"
					@mouseover="keepOpen(i)"
					@mouseleave="scheduleClose(i)"
				>
					<q-list dense class="mini-menu-list">
						<template v-for="(child, j) in item.children" :key="j">
							<!-- Child with own children: nested menu on hover -->
							<div
								v-if="child.children"
								class="mini-menu-item has-children"
								@mouseover="openNested(i, j)"
								@mouseleave="closeNested(i, j)"
							>
								<q-item clickable dense class="nested-trigger">
									<q-item-section>
										<q-item-label>
											<icon :name="child.icon || 'star'" class="menu-item-icon" />
											{{ child.label }}
										</q-item-label>
									</q-item-section>
									<q-item-section side>
										<icon name="chevron_right" class="sub-arrow" />
									</q-item-section>
								</q-item>
								<q-menu
									:model-value="getNestedState(i, j)"
									@update:model-value="(v) => setNestedState(i, j, v)"
									anchor="top right"
									self="top left"
									@mouseover="keepOpenNested(i, j)"
									@mouseleave="scheduleCloseNested(i, j)"
								>
									<q-list dense class="mini-menu-list">
										<SectionMini
											:items="[child]"
											@action="(name: string) => emit('action', name)"
										/>
									</q-list>
								</q-menu>
							</div>

							<!-- Leaf child -->
							<q-item v-else clickable dense @click="handleLeaf(child)" class="mini-menu-item">
								<q-item-section>
									<q-item-label>
										<icon :name="child.icon || 'star'" class="menu-item-icon" />
										{{ child.label }}
									</q-item-label>
								</q-item-section>
							</q-item>
						</template>
					</q-list>
				</q-menu>

				<icon :name="item.icon || 'star'" class="mini-icon" />
			</div>

			<!-- Leaf root item -->
			<div v-else class="mini-item">
				<icon :name="item.icon || 'star'" class="mini-icon" @click="handleLeaf(item)" />
				<q-tooltip anchor="center left" self="center right">
					{{ item.label }}
				</q-tooltip>
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
	import type { Seccion } from '@/types/seccion'
	import { useRouter } from 'vue-router'

	const props = defineProps<{
		items: Seccion[]
	}>()

	const emit = defineEmits<{
		action: [name: string]
	}>()

	const router = useRouter()
	const menuStates = ref<boolean[]>(Array(props.items.length).fill(false))
	const nestedStates = ref<Record<number, Record<number, boolean>>>({})

	const closeTimers = ref<(ReturnType<typeof setTimeout> | null)[]>([])
	const nestedCloseTimers = ref<Record<number, Record<number, ReturnType<typeof setTimeout> | null>>>({})

	onMounted(() => {
		closeTimers.value = Array(props.items.length).fill(null)
		nestedCloseTimers.value = {}
	})

	function getNestedState(parentIdx: number, childIdx: number): boolean {
		return !!nestedStates.value[parentIdx]?.[childIdx]
	}

	function setNestedState(parentIdx: number, childIdx: number, val: boolean) {
		if (!nestedStates.value[parentIdx]) {
			nestedStates.value[parentIdx] = {}
		}
		nestedStates.value[parentIdx][childIdx] = val
	}

	function openMenu(i: number) {
		clearTimeout(closeTimers.value[i] ?? undefined)
		menuStates.value[i] = true
	}

	function closeMenu(i: number) {
		clearTimeout(closeTimers.value[i] ?? undefined)
		closeTimers.value[i] = setTimeout(() => {
			menuStates.value[i] = false
			delete nestedStates.value[i]
		}, 200)
	}

	function keepOpen(i: number) {
		clearTimeout(closeTimers.value[i] ?? undefined)
	}

	function scheduleClose(i: number) {
		closeMenu(i)
	}

	function openNested(parentIdx: number, childIdx: number) {
		const timer = nestedCloseTimers.value[parentIdx]?.[childIdx]
		if (timer != null) clearTimeout(timer)
		setNestedState(parentIdx, childIdx, true)
	}

	function closeNested(parentIdx: number, childIdx: number) {
		const timer = nestedCloseTimers.value[parentIdx]?.[childIdx]
		if (timer != null) clearTimeout(timer)
		if (!nestedCloseTimers.value[parentIdx]) {
			nestedCloseTimers.value[parentIdx] = {}
		}
		nestedCloseTimers.value[parentIdx][childIdx] = setTimeout(() => {
			setNestedState(parentIdx, childIdx, false)
		}, 200)
	}

	function keepOpenNested(parentIdx: number, childIdx: number) {
		const timer = nestedCloseTimers.value[parentIdx]?.[childIdx]
		if (timer != null) clearTimeout(timer)
	}

	function scheduleCloseNested(parentIdx: number, childIdx: number) {
		closeNested(parentIdx, childIdx)
	}

	function handleLeaf(item: Seccion) {
		if (item.to) {
			router.push(item.to)
		} else {
			emit('action', item.name)
		}
	}
</script>

<style scoped lang="scss">
	.mini-items {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.5rem;

		.mini-item {
			display: flex;
			flex-direction: column;
			align-items: center;

			.mini-icon {
				font-size: 22px;
				color: $surface-6;
				cursor: pointer;
				padding: 4px;
				border-radius: 6px;
				transition: background 0.15s;

				&:hover {
					background: $surface-2;
				}
			}
		}
	}

	.mini-menu-list {
		min-width: 160px;
		padding: 4px 0;
	}

	.mini-menu-item {
		.menu-item-icon {
			font-size: 18px;
			margin-right: 6px;
			vertical-align: middle;
			color: $surface-6;
		}

		.sub-arrow {
			font-size: 16px;
			color: $surface-5;
		}

		&.has-children {
			position: relative;
		}
	}

	.nested-trigger {
		.q-item__section--side {
			min-width: auto;
		}
	}
</style>
