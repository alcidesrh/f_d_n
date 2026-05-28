import {
	defineConfig,
	presetUno,
	presetAttributify,
	Rule,
	transformerDirectives,
	transformerVariantGroup,
	presetWind4,
} from 'unocss';
import { utopia_rules, color_rules } from './src/utils/unocss_rules';
import { colors } from './src/utils/colors';
import { theme } from '@unocss/preset-wind4';

export default defineConfig({
	rules: [
		...(utopia_rules as Rule<object>[]),
		...(color_rules as Rule<object>[]),
	],
	presets: [
		presetUno(),
		presetAttributify(),
		presetWind4({
			preflights: {
				reset: true,
				theme: true,
			},
		}),
	],
	// theme
	extendTheme: (theme) => {
		return {
			...theme,
			colors: {
				...theme.colors,
				...colors,
			},
		};
	},
	// layers: {
	//   reset: -10,
	//   quasar: -5,
	//   theme: -3,
	//   base: 10,
	//   components: 20,
	//   overrides: 30,
	// },
	transformers: [transformerDirectives(), transformerVariantGroup()],
	content: {
		pipeline: {
			include: [
				'src/form/formkit.theme.ts',
				/\.(vue|svelte|[jt]sx|mdx?|astro|elm|php|phtml|html)($|\?)/,
				'src/utils/**/*.{ts,js,vue}',
				'src/composables/**/*.{ts,js,vue}',
				'src/stores/**/*.{ts,js,vue}',
				'src/pages/**/*.{ts,js,vue}',
				'src/layouts/**/*.{ts,js,vue}',
				'src/components/**/*.{ts,js,vue}',
				'src/models/**/*.{ts,js,vue}',
			],
			// exclude files
			// exclude: []
		},
		filesystem: [
			// 'layers/**/*.{js,ts.vue}',
			// 'layers/auth/components/form/*.{ts,js,vue}',
			// 'composables/**/*.{ts,js,vue}',
			// './pages/**/*.{ts,js,vue}',
			// './node_modules/@primevue/**/*.{vue,js,ts,jsx,tsx}'
		],
	},
});
