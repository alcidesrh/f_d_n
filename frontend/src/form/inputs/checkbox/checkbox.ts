import { createInput } from '@formkit/vue'
import BaseProps from '../BaseProps'
import cmp from './checkbox.vue'

export const checkbox = createInput(cmp, { props: BaseProps })
export default checkbox
