import { createInput } from '@formkit/vue'
import BaseProps from '../BaseProps'
import p from './password.vue'
const password = createInput(p, { props: BaseProps })
export default password
