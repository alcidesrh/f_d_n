// import { genesisIcons } from '@formkit/icons'
import { rootClasses } from '@/form/formkit.theme'
import * as inputs from '@/form/inputs'
import addAsteriskPlugin from '@/form/plugins/addAsterisk'
import createAutoAnimatePlugin from '@/form/plugins/animate'
import scrollToErrors from '@/form/plugins/scrollToErrors'
import { es } from '@formkit/i18n'
import { generateClasses } from '@formkit/themes'

export default {
	locale: 'es',
	locales: { es },
	// icons: { ...genesisIcons },
	config: {
		rootClasses,
		classes: generateClasses({
			global: {
				label: 'whitespace-nowrap',
			},
			group: {
				outer: 'md:col-span-2',
			},
			textarea: {
				outer: 'md:col-span-2',
			},
			file: {
				outer: 'md:col-span-2',
			},
		}),
	},
	inputs,

	plugins: [
		addAsteriskPlugin,
		scrollToErrors,
		createAutoAnimatePlugin(),
		// filterProps,
		// createLoadingSpinnerPlugin(),
	],
}
