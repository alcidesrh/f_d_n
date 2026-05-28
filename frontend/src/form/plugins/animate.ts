import { createAutoAnimatePlugin } from '@formkit/addons';

export default () =>
	createAutoAnimatePlugin(
		{
			/* optional AutoAnimate config */
			// default:
			duration: 250,
			easing: 'ease-in-out',
		},
		{
			/* optional animation targets object */
			// default:
			global: ['outer', 'inner'],
			form: ['form'],
			repeater: ['items'],
		},
	);
