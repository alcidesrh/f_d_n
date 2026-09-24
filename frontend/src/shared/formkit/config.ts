import { createInput, defineFormKitConfig } from "@formkit/vue";
import { es } from "@formkit/i18n";
import FkAutoComplete from "@/shared/formkit/inputs/FkAutoComplete.vue";
import FkButton from "@/shared/formkit/inputs/FkButton.vue";
import FkCascadeSelect from "@/shared/formkit/inputs/FkCascadeSelect.vue";
import FkCheckbox from "@/shared/formkit/inputs/FkCheckbox.vue";
import FkDatePicker from "@/shared/formkit/inputs/FkDatePicker.vue";
import FkIconPicker from "@/shared/formkit/inputs/FkIconPicker.vue";
import FkInputMask from "@/shared/formkit/inputs/FkInputMask.vue";
import FkInputNumber from "@/shared/formkit/inputs/FkInputNumber.vue";
import FkInputText from "@/shared/formkit/inputs/FkInputText.vue";
import FkMultiSelect from "@/shared/formkit/inputs/FkMultiSelect.vue";
import FkPassword from "@/shared/formkit/inputs/FkPassword.vue";
import FkRadioButton from "@/shared/formkit/inputs/FkRadioButton.vue";
import FkSelect from "@/shared/formkit/inputs/FkSelect.vue";
import FkSelectButton from "@/shared/formkit/inputs/FkSelectButton.vue";
import FkTextArea from "@/shared/formkit/inputs/FkTextArea.vue";
import FkToggleSwitch from "@/shared/formkit/inputs/FkToggleSwitch.vue";
import FkTreeSelect from "@/shared/formkit/inputs/FkTreeSelect.vue";

export default defineFormKitConfig({
  locales: { es },
  locale: "es",
  inputs: {
    InputText: createInput(FkInputText),
    InputMask: createInput(FkInputMask),
    InputNumber: createInput(FkInputNumber),
    Checkbox: createInput(FkCheckbox),
    RadioButton: createInput(FkRadioButton),
    Select: createInput(FkSelect),
    MultiSelect: createInput(FkMultiSelect),
    SelectButton: createInput(FkSelectButton),
    CascadeSelect: createInput(FkCascadeSelect),
    AutoComplete: createInput(FkAutoComplete),
    Password: createInput(FkPassword),
    DatePicker: createInput(FkDatePicker),
    TextArea: createInput(FkTextArea),
    TreeSelect: createInput(FkTreeSelect),
    ToggleSwitch: createInput(FkToggleSwitch),
    IconPicker: createInput(FkIconPicker),
    // El label se pinta en el botón PrimeVue: se suprime la sección `label` de FormKit.
    Button: createInput(FkButton, {}, { label: null }),
  },
  config: {
    classes: {
      outer: "mb-4",
      label: "block font-medium mb-1.5",
      help: "mt-1 text-xs text-muted-color",
      messages: "list-none p-0 m-0 mt-1",
      message: "mt-1 text-xs text-red-500",
    },
  },
});
