import type { RouteRecordRaw } from 'vue-router'
import actionRoutes from './action'
import adminRoutes from './admin'
import roleRoutes from './role'
import testRoutes from './test'
import userRoutes from './user'

const routes: RouteRecordRaw[] = [
	{
		path: '/login',
		name: 'login',
		component: () => import('pages/auth/LoginPage.vue'),
		meta: { public: true },
	},
	{
		path: '/',
		component: () => import('layouts/MainLayout.vue'),
		meta: {
			breadcrumb: 'Inicio',
			icon: 'home',
		},
		children: [
			{ path: '', component: () => import('pages/IndexPage.vue') },
			{
				path: '/lista/:entity',
				name: 'list',
				component: () => import('@/components/crud/collection/DynamicCollection.vue'),
				meta: {
					breadcrumb: (v) => {
						const params = v.params
						return [
							{
								label: 'entidades',
								to: '/admin/entities',
								icon: 'lists',
							},
							{
								label: params.entity,
								to: v.path,
								icon: false,
							},
						]
					},
					action: 'listar',
				},
			},
			{
				path: '/form/:entity/:id?',
				name: 'form',
				meta: {
					breadcrumb: (v) => {
						const params = v.params
						return [
							{
								label: 'lista',
								to: '/lista/' + params.entity,
								icon: 'lists',
							},
							{
								label: params.id ? 'editar' : 'crear',
								to: false,
								icon: params.id ? 'edit' : 'add_circle',
							},
							{
								label: params.entity + ' ' + params.id,
								to: v.path,
								icon: false,
							},
						]
					},
					icon: 'edit',
					action: 'form',
				},
				component: () => import('@/components/crud/form/DynamicForm.vue'),
			},
			{
				path: '/test',
				name: 'test',
				component: () => import('@/pages/Test.vue'),
			},

			...actionRoutes,
			...userRoutes,
			...roleRoutes,
			...testRoutes,
			...adminRoutes,
		],
	},

	// Always leave this as last one,
	// but you can also remove it
	{
		path: '/:action/:entity',
		component: () => import('pages/ErrorNotFound.vue'),
	},
	{
		path: '/:catchAll(.*)*',
		component: () => import('pages/ErrorNotFound.vue'),
	},
]

export default routes
