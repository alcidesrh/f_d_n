import { createInput } from "@formkit/vue";
import cmp from "./datepicker.vue";
import BaseProps from "../BaseProps";
const props = {
  range: {
    type: Boolean,
    default: false,
  },
  modelValue: {
    type: String | Object,
    default: "",
  },
};
const input = createInput(cmp, { props: { ...BaseProps, ...props } });
export default input;
