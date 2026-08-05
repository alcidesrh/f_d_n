declare module "@formkit/inputs" {
  interface FormKitInputProps<Props extends FormKitInputs<Props>> {
    InputText: {
      type: "InputText";
      value?: string;
      variant?: "filled" | "outlined";
      size?: "small" | "large";
      fluid?: boolean;
    };
    InputMask: {
      type: "InputMask";
      value?: string;
      mask?: string;
      slotChar?: string;
      autoClear?: boolean;
      unmask?: boolean;
    };
    InputNumber: {
      type: "InputNumber";
      value?: number | null;
      mode?: "decimal" | "currency";
      currency?: string;
      locale?: string;
      min?: number;
      max?: number;
      prefix?: string;
      suffix?: string;
      minFractionDigits?: number;
      maxFractionDigits?: number;
    };
    Checkbox: {
      type: "Checkbox";
      value?: boolean | unknown[];
      options?: unknown[];
      optionLabel?: string;
      optionValue?: string;
    };
    RadioButton: {
      type: "RadioButton";
      value?: unknown;
      options?: unknown[];
      optionLabel?: string;
      optionValue?: string;
    };
    Select: {
      type: "Select";
      value?: unknown;
      options?: unknown[];
      optionLabel?: string;
      optionValue?: string;
      showClear?: boolean;
      filter?: boolean;
    };
    MultiSelect: {
      type: "MultiSelect";
      value?: unknown[];
      options?: unknown[];
      optionLabel?: string;
      optionValue?: string;
      showClear?: boolean;
      filter?: boolean;
      display?: "comma" | "chip";
    };
    SelectButton: {
      type: "SelectButton";
      value?: unknown;
      options?: unknown[];
      optionLabel?: string;
      optionValue?: string;
      multiple?: boolean;
      unselectable?: boolean;
    };
    CascadeSelect: {
      type: "CascadeSelect";
      value?: unknown;
      options?: unknown[];
      optionLabel?: string;
      optionGroupLabel?: string;
      optionGroupChildren?: string;
    };
    AutoComplete: {
      type: "AutoComplete";
      value?: unknown;
      suggestions?: unknown[];
      optionLabel?: string;
      multiple?: boolean;
      dropdown?: boolean;
    };
    Password: {
      type: "Password";
      value?: string;
      toggleMask?: boolean;
      feedback?: boolean;
      promptLabel?: string;
      weakLabel?: string;
      mediumLabel?: string;
      strongLabel?: string;
    };
    DatePicker: {
      type: "DatePicker";
      value?: Date | Date[] | string | null;
      dateFormat?: string;
      showIcon?: boolean;
      selectionMode?: "single" | "multiple" | "range";
    };
    TextArea: {
      type: "TextArea";
      value?: string;
      rows?: number;
      cols?: number;
      autoResize?: boolean;
    };
    TreeSelect: {
      type: "TreeSelect";
      value?: unknown;
      options?: unknown[];
      selectionMode?: "single" | "multiple" | "checkbox";
      filter?: boolean;
      showClear?: boolean;
    };
    ToggleSwitch: {
      type: "ToggleSwitch";
      value?: boolean;
    };
  }

  interface FormKitInputEventsAsProps<Props extends FormKitInputs<Props>> {
    AutoComplete: {
      onComplete: (e: { originalEvent: Event; query: string }) => void;
    };
  }

  interface FormKitInputSlots<Props extends FormKitInputs<Props>> {
    InputText: FormKitBaseSlots<Props>;
    InputMask: FormKitBaseSlots<Props>;
    InputNumber: FormKitBaseSlots<Props>;
    Checkbox: FormKitBaseSlots<Props>;
    RadioButton: FormKitBaseSlots<Props>;
    Select: FormKitBaseSlots<Props>;
    MultiSelect: FormKitBaseSlots<Props>;
    SelectButton: FormKitBaseSlots<Props>;
    CascadeSelect: FormKitBaseSlots<Props>;
    AutoComplete: FormKitBaseSlots<Props>;
    Password: FormKitBaseSlots<Props>;
    DatePicker: FormKitBaseSlots<Props>;
    TextArea: FormKitBaseSlots<Props>;
    TreeSelect: FormKitBaseSlots<Props>;
    ToggleSwitch: FormKitBaseSlots<Props>;
  }
}
