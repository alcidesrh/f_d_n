import { createInput } from "@formkit/vue";
import cmp from "./select.vue";
import BaseProps from "../BaseProps";
const props = {
  multiple: {
    type: Boolean,
    default: false,
  },
  options: {
    type: Array,
    default: [],
  },
  target: {
    type: "String",
  },
  optionLabel: {
    type: String,
    default: "label",
  },
  optionValue: {
    type: String,
    default: "value",
  },
  placeholder: {
    type: String,
    default: null,
  },
};
const select = createInput(cmp, { props: { ...BaseProps, ...props } });
export default select;
