import { computed } from 'vue'
import { useRoute } from 'vue-router'

export function useBreadcrumbs() {
	const route = useRoute()

	const breadcrumbs = computed(() => {
		const breadcrumb = []
		route.matched
			.filter((r) => r.meta && r.meta.breadcrumb)
			.forEach((r) => {
				const data = typeof r.meta.breadcrumb === 'function' ? r.meta.breadcrumb(route) : r.meta.breadcrumb
				if (Array.isArray(data)) {
					data.forEach((v) => {
						breadcrumb.push({
							label: v.label,
							to: v.to,
							icon: v.icon,
						})
					})
				} else {
					breadcrumb.push({
						label: data,
						to: r.path !== route.path ? r.path : null,
						icon: r.meta.icon || false,
					})
				}
			})
		return breadcrumb
	})

	return { breadcrumbs }
}
