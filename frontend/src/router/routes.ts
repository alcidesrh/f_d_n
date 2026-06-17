import type { RouteRecordRaw } from 'vue-router'
import actionRoutes from './action'
import adminRoutes from './admin'
import roleRoutes from './role'
import testRoutes from './test'
import userRoutes from './user'
import boletoRoutes from './boleto'

const routes: RouteRecordRaw[] = [
	{
		path: '/login',
		name: 'login',
		component: () => import('pages/auth/LoginPage.vue'),
		meta: { public: true },
	},
	{
		path: '/forbidden',
		name: 'forbidden',
		component: () => import('@/pages/error/Forbidden.vue'),
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
			{ path: '', name: 'home', component: () => import('@/pages/admin/DashboardPage.vue') },
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
			{
				path: '/venta/boleto',
				name: 'venta_boleto',
				component: () => import('@/pages/venta/BoletoVentaPage.vue'),
				meta: {
					breadcrumb: 'Venta de Boletos',
					icon: 'sym_o_airplane_ticket',
				},
			},

			...actionRoutes,
			...userRoutes,
			...roleRoutes,
			...testRoutes,
			...adminRoutes,
			...boletoRoutes
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
