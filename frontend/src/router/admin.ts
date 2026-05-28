import { Router } from 'vue-router'

export default [
	{
		path: 'admin',
		name: 'admin',
		meta: { breadcrumb: 'admin', icon: 'security' },
		children: [
			{
				path: 'entities',
				meta: { breadcrumb: 'entidades', icon: 'format_list_bulleted' },
				children: [
					{
						name: 'entity_list',
						path: '',
						component: () => import('@/pages/admin/EntityList.vue'),
					},
					{
						name: 'entity_config',
						path: ':action/:entity',
						meta: {
							breadcrumb: (v: Router) => {
								const params = v.params
								return [
									{
										label: params.action,
										to: false,
										icon: 'edit',
									},
									{
										label: params.entity,
										to: v.path,
										icon: false,
									},
								]
							},
						},
						component: () => import('@/pages/admin/EntityConfig.vue'),
					},
				],
			},
		],
	},
]
