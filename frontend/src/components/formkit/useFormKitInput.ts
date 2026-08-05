import type { FormKitFrameworkContext } from "@formkit/core";

export interface FormKitInputProps {
  context: FormKitFrameworkContext;
}

export function useFormKitInput(props: FormKitInputProps) {
  const context = toRef(props, "context");

  const update = (value: unknown) => {
    props.context.node.input(value);
  };

  const blur = (e?: FocusEvent) => {
    props.context.handlers.blur(e);
  };

  const invalid = computed(() => props.context.state.invalid === true);
  const disabled = computed(() => props.context.disabled === true);

  return { context, update, blur, invalid, disabled };
}
