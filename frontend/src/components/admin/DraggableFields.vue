<template>
	<div :id="`drag-${type}`" class="drag-container bg-surface-2" :style="{ height: `${h}px` }">
		<div v-for="(element, index) in localFields" :key="index" class="field-item" :ref="`itemRefs${type}`">
			<div class="w-fit row items-center no-wrap h-full flex items-center bg-white field-item-content">
				<!-- <div class="row items-center no-wrap h-full flex items-center"> -->
				<icon name="drag_indicator" />
				<div class="truncate mx-10px font-600 text-1.1rem w-80px cursor-pointer">
					<!-- <span class="mr-10px">{{ element.position }}</span> -->
					<span> {{ element.field }}</span>
					<q-tooltip anchor="top middle" self="bottom middle" :offset="[10, 10]"> {{ element.field }}</q-tooltip>
				</div>
				<div class="w-100px mr-0px">
					<q-checkbox v-model="element.visible" label="Visible" @update:model-value="toggleVisible(element, index)" />
				</div>
				<div class="w-130px mr-10px">
					<q-input v-model="element.label" dense outlined label="Label" />
				</div>
				<slot name="content" :element="element"> </slot>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
	import { Draggable } from 'gsap/Draggable'
	import { useTemplateRef } from 'vue'

	const props = defineProps({
		type: String,
	})
	const localFields = defineModel()
	// const localFields = ref(useCloned(localField2.value).cloned.value);
	const htmlRef = useTemplateRef(`itemRefs${props.type}`)
	let cells = [],
		sortables = ref([]),
		rowSize = 60,
		// colSize = 1025,
		container

	let clampRow = gsap.value.utils.clamp(0, localFields.value.length - 1)
	onMounted(() => {
		container = document.querySelector(`#drag-${props.type}`)
		gsap.value.to(container, { autoAlpha: 1, duration: 1.5 })
		htmlRef.value?.forEach((v, index) => {
			cells.push({
				row: index,
				y: index * rowSize,
			})
			sortables.value.push(Sortable(v, index))
		})
	})
	function toggleVisible(element, index) {
		index = 0
		for (; index < sortables.value.length; index++) {
			const element2 = sortables.value[index]
			if (element.id == element2.id) {
				break
			}
		}

		for (let i = index; index < sortables.value.length; index++) {
			changeIndex(sortables.value[index], index + 1)
		}
		sortables.value.forEach((v, i) => (localFields.value.find((v2) => v2.id == v.id).position = i + 1))
		return
		if (!localFields.value[index].visible) {
			// let temp = localFields.value[index];
			let temp2 = sortables.value[index]
			// localFields.value.splice(index, 1);
			sortables.value.splice(index, 1)
			// localFields.value.push(temp);
			sortables.value.push(temp2)

			sortables.value.forEach((v, i) => {
				v.setIndex(i)
				// localFields.value[i].position = i + 1;
				localFields.value.find((v2) => v2.id == v.id).position = i + 1
			})
		}
	}
	function changeIndex(item, to, sameRow) {
		if (sameRow) {
			let temp = sortables.value[to]
			sortables.value[to] = item
			sortables.value[item.index] = temp
		} else {
			sortables.value.splice(to, 0, sortables.value.splice(item.index, 1)[0])
		}
		// Simple, but not optimized way to change element's position in DOM. Not always necessary.
		// sortables.value.forEach((sortable) =>
		//   container.appendChild(sortable.element),
		// );

		// Set index for each sortable
		sortables.value.forEach((sortable, index) => {
			sortable.setIndex(index)
		})
	}

	function Sortable(element, index) {
		let content = element.querySelector('.field-item-content')
		let animation = gsap.value.to(content, {
			duration: 0.3,
			boxShadow: '0px 8px 16px #64748b',
			force3D: true,
			scale: 1.1,
			paused: true,
		})

		let dragger = new Draggable(element, {
			onDragStart: downAction,
			onRelease: upAction,
			onDrag: dragAction,
			// cursor: "inherit",
			type: 'y',
			// inertia: true,
			trigger: element.querySelector('.fdn-icon'),
		})

		let sortable = {
			id: localFields.value[index].id,
			field: localFields.value[index].field,
			index: index,
			cell: cells[index],
			dragger: dragger,
			element: element,
			setIndex: setIndex,
		}

		gsap.value.set(element, {
			y: sortable.cell.y,
		})

		function setIndex(index) {
			sortable.cell = cells[index]
			let dirty = gsap.value.getProperty(element, 'y') !== sortable.cell.y
			sortable.index = index
			// order.textContent = index + 1;
			// Don't layout if you're dragging
			if (!dragger.isDragging && dirty) {
				layout()
			}
		}
		function downAction() {
			animation.play()
			this.update()
		}
		function dragAction() {
			let row = clampRow(Math.round(this.y / rowSize))
			// if (row != sortable.cell.row) {
			changeIndex(sortable, row, row == sortable.cell.row)
			// }
		}

		function upAction() {
			animation.reverse()
			layout()
			let temp
			sortables.value.forEach((v, i) => (localFields.value.find((v2) => v2.id == v.id).position = i + 1))
		}

		function layout() {
			gsap.value.to(element, {
				duration: 0.3,
				y: sortable.cell.y,
			})
		}
		return sortable
	}
</script>
<style lang="scss">
	.drag-container {
		position: relative;
		width: 100%;
		max-width: 900px;
		margin: auto;
		height: 1000px;
		//opacity: 0;
		//visibility: hidden;
	}

	.field-item {
		position: absolute;
		top: 25px;
		left: 25px;
		height: 60px;
		padding: 5px 0;
	}

	.field-item-content {
		height: 100%;
		box-shadow: 1px 1px 10px $surface-4;
		padding: 0 15px;
	}
</style>
