import { createInput } from '@formkit/vue'
import BaseProps from '../BaseProps'
import cmp from './icon_picker.vue'

const icon_picker = createInput(cmp, { props: BaseProps })
export default icon_picker
