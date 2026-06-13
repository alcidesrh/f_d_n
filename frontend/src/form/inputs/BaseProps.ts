export default {
	// modelValue: {
	// 	type: null,
	// 	default: undefined,
	// },

	labelQuasar: {
		type: String,
		default: undefined,
	},
	validation: {
		type: String,
		default: undefined,
	},
	name: {
		type: String,
		default: undefined,
	},
	invalid: {
		type: Boolean,
		default: undefined,
	},
	clear: {
		type: Boolean,
		default: false,
	},
	disabled: {
		type: Boolean,
		default: false,
	},
	size: {
		type: String,
		default: null,
	},
	loading: {
		type: Boolean,
		default: null,
	},
	variant: {
		type: String,
		default: null,
	},
	eventbus: {
		type: [String],
		default: null,
	},
	inputType: {
		type: String,
		default: 'text',
	},
}
