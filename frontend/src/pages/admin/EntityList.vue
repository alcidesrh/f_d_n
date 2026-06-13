<template>
	<div class="q-pa-md">
		<q-list bordered separator>
			<q-item v-for="(v, i) in useSchemaStore().entities" :key="i" v-ripple>
				<q-item-section class="u-text-1">{{ v.name }}</q-item-section>
				<q-item-section class="items-end">
					<div class="flex w-fit gap-x-5">
						<icon
							name="format_list_bulleted"
							@click="
								$router.push({
									name: 'list',
									params: { entity: v.name },
								})
							"
						/>
						<icon
							name="add_2"
							class="text-surface-6"
							@click="
								$router.push({
									name: 'entity_config',
									params: { entity: v.name, action: 'editar' },
								})
							"
						/>
						<icon
							name="settings"
							@click="
								$router.push({
									name: 'entity_config',
									params: { entity: v.name, action: 'editar' },
								})
							"
						/>
					</div>
				</q-item-section>
			</q-item>
		</q-list>
	</div>
</template>
<script setup lang="ts"></script>
