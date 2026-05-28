<template>
	<div>
		<draggable-fields v-model="localFields" type="form">
			<template #content="{ element }">
				<div class="w-130px mx-10px">
					<q-input v-model="element.groupName" dense outlined label="grupo" />
				</div>
				<div class="w-50px">
					<icon name="html" @click="editInputProps(element)" class="text-34px ml-10px" />
				</div>
			</template>
		</draggable-fields>

		<!-- Diálogo para editar inputProps -->
		<q-dialog v-model="propsDialog.show" persistent>
			<q-card style="width: 620px; max-width: 95vw" class="px-20px">
				<q-card-section>
					<icon name="close" class="absolute right-20px" @click="propsDialog.show = false" />
					<div class="text-h6 q-mb-lg">
						Propiedades para <strong>{{ propsDialog.fieldName }}</strong>
					</div>
					<div class="row my-5px max-w-400px gap-3">
						<div class="col-4">Clave</div>
						<div class="col-4">Valor</div>
					</div>

					<div class="row max-w-400px gap-x-3">
						<div class="col-4">
							<q-input v-model="key" type="textarea" rows="14" outlined dense @blur="validateJson" />
						</div>
						<div class="col-4">
							<q-input v-model="value" type="textarea" rows="14" outlined dense @blur="validateJson" />
						</div>
						<div class="col flex items-center">
							<icon name="add" @click="addAttrb" />
						</div>
					</div>
					<q-scroll-area style="height: 200px; max-width: 400px">
						<div
							class="row max-w-400px my-15px font-600 gap-x-3"
							v-if="propsDialog.field.inputProps && propsDialog.field.inputProps.length"
							v-for="(v, i) in propsDialog.field.inputProps"
						>
							<div class="col-4">{{ Object.keys(v)[0] }}</div>
							<div class="col-4">{{ Object.values(v)[0] }}</div>
							<div class="col flex items-center">
								<icon name="delete" @click="propsDialog.field.inputProps.splice(i, 1)" />
							</div>
							<div class="col-8">
								<q-separator />
							</div>
						</div>
					</q-scroll-area>
				</q-card-section>

				<q-card-actions align="right">
					<q-btn flat @click="propsDialog.show = false">cerrar</q-btn>
				</q-card-actions>
			</q-card>
		</q-dialog>
	</div>
</template>

<script setup lang="ts">
	const localFields = defineModel()

	const key = ref(),
		value = ref()

	const propsDialog = ref({
		show: false,
		field: null as any,
		jsonText: '',
		fieldName: '',
	})

	const editInputProps = (field: any) => {
		propsDialog.value.field = field
		propsDialog.value.fieldName = field.fieldName
		propsDialog.value.jsonText = field.inputProps
		propsDialog.value.show = true
	}
	function addAttrb() {
		let temp = {}
		if (!propsDialog.value.field.inputProps) {
			temp[key.value] = value.value
			propsDialog.value.field.inputProps = [temp]
		} else if ((temp = propsDialog.value.field.inputProps.find((v) => typeof v[key.value] != 'undefined'))) {
			temp[key.value] = temp[key.value] + ' ' + value.value
		} else {
			temp = {}
			temp[key.value] = value.value
			propsDialog.value.field.inputProps.push(temp)
		}
		key.value = ''
		value.value = ''
	}
</script>
