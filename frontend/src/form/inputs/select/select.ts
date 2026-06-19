import { createInput } from '@formkit/vue'
import BaseProps from '../BaseProps'
import cmp from './select.vue'
const props = {
	autocomplete: {
		type: Boolean,
		default: false,
	},
	multiple: {
		type: Boolean,
		default: false,
	},
	options: {
		type: Array,
		default: [],
	},
	target: {
		type: 'String',
	},
	optionLabel: {
		type: String,
		default: 'label',
	},
	optionValue: {
		type: String,
		default: 'value',
	},
	placeholder: {
		type: String,
		default: null,
	},
}
const select = createInput(cmp, { props: { ...BaseProps, ...props } })
export default select
