import { createInput } from "@formkit/vue";
import BaseProps from "../BaseProps";
import cmp from "./icon.vue";

const icon = createInput(cmp, { props: BaseProps });

export default icon;