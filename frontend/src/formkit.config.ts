import { createInput, defineFormKitConfig } from "@formkit/vue";

import FkAutoComplete from "@/components/formkit/FkAutoComplete.vue";
import FkCascadeSelect from "@/components/formkit/FkCascadeSelect.vue";
import FkCheckbox from "@/components/formkit/FkCheckbox.vue";
import FkDatePicker from "@/components/formkit/FkDatePicker.vue";
import FkInputMask from "@/components/formkit/FkInputMask.vue";
import FkInputNumber from "@/components/formkit/FkInputNumber.vue";
import FkInputText from "@/components/formkit/FkInputText.vue";
import FkMultiSelect from "@/components/formkit/FkMultiSelect.vue";
import FkPassword from "@/components/formkit/FkPassword.vue";
import FkRadioButton from "@/components/formkit/FkRadioButton.vue";
import FkSelect from "@/components/formkit/FkSelect.vue";
import FkSelectButton from "@/components/formkit/FkSelectButton.vue";
import FkTextArea from "@/components/formkit/FkTextArea.vue";
import FkToggleSwitch from "@/components/formkit/FkToggleSwitch.vue";
import FkTreeSelect from "@/components/formkit/FkTreeSelect.vue";

export default defineFormKitConfig({
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
  },
  config: {
    classes: {
      outer: "mb-4",
      label: "block text-sm font-medium mb-1.5",
      help: "mt-1 text-xs text-muted-color",
      messages: "list-none p-0 m-0 mt-1",
      message: "mt-1 text-xs text-red-500",
    },
  },
});
