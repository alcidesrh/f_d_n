import { createInput } from "@formkit/vue";
import cmp from "./number.vue";
import BaseProps from "../BaseProps";
const number = createInput(cmp, { props: BaseProps });
export default number;
