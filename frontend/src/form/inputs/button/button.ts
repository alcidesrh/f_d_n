import { createInput } from '@formkit/vue'
import BaseProps from '../BaseProps'
import b from './button.vue'
const button = createInput(b, { props: BaseProps })
export default button
