import { createInput } from '@formkit/vue'
import BaseProps from '../BaseProps'
import cmp from './text.vue'
import cmp2 from './text_search.vue'

export const text = createInput(cmp, { props: BaseProps })
export const text_search = createInput(cmp2, { props: BaseProps })
