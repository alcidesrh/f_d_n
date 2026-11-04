import { defineAsyncComponent } from "vue";
import { createInput, defineFormKitConfig } from "@formkit/vue";
import { es } from "@formkit/i18n";

/**
 * Cada input se baja la primera vez que un formulario lo usa: juntos traen
 * medio PrimeVue (DatePicker, TreeSelect, MultiSelect…) y no hacen falta
 * para arrancar la app.
 */
const INPUTS = {
  FkAutoComplete: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkAutoComplete.vue")),
  FkButton: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkButton.vue")),
  FkCascadeSelect: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkCascadeSelect.vue")),
  FkCheckbox: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkCheckbox.vue")),
  FkDatePicker: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkDatePicker.vue")),
  FkIconPicker: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkIconPicker.vue")),
  FkInputMask: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkInputMask.vue")),
  FkInputNumber: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkInputNumber.vue")),
  FkInputText: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkInputText.vue")),
  FkMultiSelect: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkMultiSelect.vue")),
  FkPassword: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkPassword.vue")),
  FkRadioButton: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkRadioButton.vue")),
  FkSelect: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkSelect.vue")),
  FkSelectButton: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkSelectButton.vue")),
  FkTextArea: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkTextArea.vue")),
  FkToggleSwitch: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkToggleSwitch.vue")),
  FkTransferList: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkTransferList.vue")),
  FkTreeSelect: defineAsyncComponent(() => import("@/shared/formkit/inputs/FkTreeSelect.vue")),
};

/**
 * Baja los inputs y los deja resueltos: desde ahí se pintan sin esperar.
 * La app lo llama en un rato libre tras arrancar; los tests, antes de montar.
 */
export function precargarInputs(): Promise<unknown> {
  return Promise.all(Object.values(INPUTS).map((c) => (c as { __asyncLoader?: () => Promise<unknown> }).__asyncLoader?.()));
}

export default defineFormKitConfig({
  locales: { es },
  locale: "es",
  inputs: {
    InputText: createInput(INPUTS.FkInputText),
    InputMask: createInput(INPUTS.FkInputMask),
    InputNumber: createInput(INPUTS.FkInputNumber),
    Checkbox: createInput(INPUTS.FkCheckbox),
    RadioButton: createInput(INPUTS.FkRadioButton),
    Select: createInput(INPUTS.FkSelect),
    MultiSelect: createInput(INPUTS.FkMultiSelect),
    SelectButton: createInput(INPUTS.FkSelectButton),
    CascadeSelect: createInput(INPUTS.FkCascadeSelect),
    AutoComplete: createInput(INPUTS.FkAutoComplete),
    Password: createInput(INPUTS.FkPassword),
    DatePicker: createInput(INPUTS.FkDatePicker),
    TextArea: createInput(INPUTS.FkTextArea),
    TreeSelect: createInput(INPUTS.FkTreeSelect),
    ToggleSwitch: createInput(INPUTS.FkToggleSwitch),
    IconPicker: createInput(INPUTS.FkIconPicker),
    TransferList: createInput(INPUTS.FkTransferList),
    // El label se pinta en el botón PrimeVue: se suprime la sección `label` de FormKit.
    Button: createInput(INPUTS.FkButton, {}, { label: null }),
  },
  config: {
    classes: {
      outer: "mb-4 w-full max-w-[25rem]",
      label: "block font-medium mb-1.5",
      help: "mt-1 text-xs text-muted-color",
      messages: "list-none p-0 m-0 mt-1",
      message: "mt-1 text-xs text-red-500",
    },
  },
});
