<template>
	<div class="max-w-900px m-auto relative relative">
		<div @click="save" class="grid gap-2 p-16px" :class="{ 'bg-surface-2': editing.form || editing.list }">
			<q-btn @click="save" outline class="bg-surface-1 w-fit ml-auto" label="Guardar" icon="sym_o_save" type="submit"></q-btn>
		</div>
		<q-tab-panels v-model="tab" animated :swipeable="isMobile" vertical transition-prev="jump-up" transition-next="jump-up">
			<q-tab-panel name="fields">
				<div class="q-pa-md">
					<div class="q-gutter-y-md">
						<q-tabs v-model="tab2" dense class="text-grey" active-color="primary" indicator-color="primary" align="justify" narrow-indicator>
							<q-tab name="lista">
								<q-badge v-if="editing.list" floating color="red" rounded class="small" />
								<span :class="{ 'text-red': editing.list }">Lista</span>
							</q-tab>
							<q-tab name="form">
								<q-badge v-if="editing.form" floating color="red" rounded class="small" />
								<span :class="{ 'text-red': editing.form }">Form</span>
							</q-tab>
						</q-tabs>
						<q-separator />
						<q-tab-panels v-model="tab2" animated>
							<q-tab-panel name="lista" class="">
								<CollectionFieldEditor v-if="collectionFieldConfig.length" v-model="collectionFieldConfig" />
							</q-tab-panel>
							<q-tab-panel name="form" class="bg-slate-2">
								<FormFieldsEditor v-if="formFields.length" v-model="formFields" />
							</q-tab-panel>
						</q-tab-panels>
					</div>
				</div>
			</q-tab-panel>
		</q-tab-panels>
	</div>
</template>

<script setup>
import { useCloned } from '@vueuse/core'
import { useRoute } from 'vue-router'
const { fetchConfigAdmin, saveConfig } = useEntityConfig()
const route = useRoute()
const tab = ref('fields'),
	tab2 = ref('lista'),
	splitterModel = ref(15),
	entityClass = ref(),
	collectionFieldConfig = ref([]),
	formFields = ref([]),
	editing = ref({ list: false, form: false }),
	entityCofiguration = ref()

onMounted(async () => {
	entityCofiguration.value = await fetchConfigAdmin(route.params.entity)
	collectionFieldConfig.value = entityCofiguration.value.collectionFieldConfig.sort((a, b) => a.position - b.position)

	formFields.value = entityCofiguration.value.formFields.sort((a, b) => a.position - b.position)

	watch(
		() => collectionFieldConfig.value,
		(v) => {
			editing.value.list = true
		},
		{ deep: true },
	)

	watch(
		() => formFields.value,
		(v) => {
			editing.value.form = true
		},
		{ deep: true },
	)
})
function save() {
	if (!(editing.value.form || editing.value.list)) {
		return
	}
	const temp = useCloned(entityCofiguration.value).cloned.value

	temp.collectionFieldConfig = temp.collectionFieldConfig.map((v) => {
		delete v.name

		return v
	})
	temp.formFields = temp.formFields.map((v) => {
		delete v.name
		return v
	})

	saveConfig(temp).then(() => {
		editing.value.form = editing.value.list = false
		bus.emit('positive', getAlertText('update'))
	})
}
</script>
