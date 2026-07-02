import { gql } from '@apollo/client/core'

export interface IconItem {
	id: string
	codigo: string
	name: string
	codepoint?: number
	popularity?: number
	tags?: string[]
}

export interface CategoryItem {
	id: string
	name: string
	totalIcons?: number
	icons?: { id: string }[]
}

const GET_ICONS = gql`
	query GetIcons {
		icons {
			id
			codigo
			name
			codepoint
			popularity
			tags
		}
	}
`

const GET_CATEGORIES = gql`
	query GetCategories {
		iconCategories {
			id
			name
			totalIcons
			icons {
				id
			}
		}
	}
`

const iconsCache = ref<IconItem[]>([])
const categoriesCache = ref<CategoryItem[]>([])
const iconCategoryMap = ref<Map<string, string[]>>(new Map())
const loading = ref(false)
const loaded = ref(false)

function buildCategoryMap(categories: CategoryItem[]): Map<string, string[]> {
	const map = new Map<string, string[]>()
	for (const cat of categories) {
		if (cat.icons) {
			for (const icon of cat.icons) {
				const existing = map.get(icon.id) || []
				existing.push(cat.name)
				map.set(icon.id, existing)
			}
		}
	}
	return map
}

export function useIcons() {
	const apollo = useApolloStore()

	async function loadIcons(): Promise<IconItem[]> {
		if (iconsCache.value.length > 0) return iconsCache.value
		loading.value = true
		try {
			const { data } = await apollo.query({ query: GET_ICONS, fetchPolicy: 'network-only' })
			iconsCache.value = data.icons || []
			return iconsCache.value
		} finally {
			loading.value = false
			loaded.value = true
		}
	}

	async function loadCategories(): Promise<CategoryItem[]> {
		if (categoriesCache.value.length > 0) return categoriesCache.value
		loading.value = true
		try {
			const { data } = await apollo.query({ query: GET_CATEGORIES, fetchPolicy: 'network-only' })
			categoriesCache.value = data.iconCategories || []
			iconCategoryMap.value = buildCategoryMap(categoriesCache.value)
			return categoriesCache.value
		} finally {
			loading.value = false
		}
	}

	async function loadAll() {
		await Promise.all([loadIcons(), loadCategories()])
	}

	function iconCategories(iconId: string): string[] {
		return iconCategoryMap.value.get(iconId) || []
	}

	function searchIcons(term: string, category?: string): IconItem[] {
		let items = iconsCache.value

		if (category && category !== 'all') {
			const catIconIds = new Set<string>()
			for (const cat of categoriesCache.value) {
				if (cat.name === category && cat.icons) {
					cat.icons.forEach((i) => catIconIds.add(i.id))
				}
			}
			items = items.filter((icon) => catIconIds.has(icon.id))
		}

		if (term) {
			const q = term.toLowerCase()
			items = items.filter((icon) => icon.codigo.toLowerCase().includes(q) || icon.name?.toLowerCase().includes(q) || icon.tags?.some((t) => t.toLowerCase().includes(q)))
		}

		return [...items].sort((a, b) => (b.popularity ?? 0) - (a.popularity ?? 0))
	}

	function findByCodigo(codigo: string): IconItem | undefined {
		return iconsCache.value.find((i) => i.codigo === codigo)
	}

	function findById(id: string | number): IconItem | undefined {
		return iconsCache.value.find((i) => i.id === String(id))
	}

	return {
		icons: iconsCache,
		categories: categoriesCache,
		loading,
		loaded,
		loadIcons,
		loadCategories,
		loadAll,
		searchIcons,
		iconCategories,
		findByCodigo,
		findById,
	}
}
