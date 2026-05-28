export default {
  outer: ``,
  wrapper: ``,
  label: ``,
  legend: `
    ${
      "" /* The legend key is used for inputs that have multiple child inputs within them.
      It is the "label" for a fieldset. Used by checkbox (multiple), radio (multiple), and repeater */
    }
    block
    text-$colorTemperature-$colorTemperatureStrength
    text-$scale(-1,*)

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  input: ``,
  placeholder: ``,
  prefixIcon: `
    flex
    shrink-0
    items-center

    ${
      "" /** It is recommended to pull icons back into whatever default inner padding you have set */
    }
    -ml-$spacing(-2,0,1)

    mr-$spacing
    text-$scale
    h-[1em]
    w-[1em]

    ${
      "" /** === ⚠️ CAUTION: Styling nested SVGs ===
    Because the actual icon SVGs output by FormKit do not get their own addressable section name
    you need to access them from a parent element. */
    }
    [&>svg]:w-full
  `,
  /** Same (but inverted) as prefixIcon above, just not commented */
  suffixIcon: `
    flex
    items-center
    -mr-$spacing(-2,0,1.5)
    ml-$spacing
    text-$scale
    h-[1em]
    w-[1em]
    shrink-0
    [&>svg]:w-full
  `,
  /** Used across inputs when a loading action is happening */
  loaderIcon: `
    animate-spin
    flex
    items-center
    my-auto
    ml-$spacing
    text-$scale
    text-$colorTemperature-$colorTemperatureStrength
    h-[1em]
    w-[1em]
    shrink-0
    [&>svg]:w-full

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  /** Used across inputs when a loading action is happening */
  loadMoreInner: `
    flex
    text-$scale(-1,*)
    text-$colorTemperature-$colorTemperatureStrength
    p-$spacing
    [&>span]:mr-$spacing

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  help: `
    text-$colorTemperature-$colorTemperatureStrength
    text-$scale(-2,*)

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  messages: ``,
  message: `
    text-red-6
    m-auto
    max-w-[20rem]
    mb-$spacing(-1)
    text-$scale(-2,*)

    dark:text-red-400
  `,
  /**
   * The following overlay* section keys are used for inputs that have
   * masking functionality (eg. mask, datepicker: with overlay).
   * https://formkit.com/inputs/mask#overlay-colorizing-a-mask
   */
  overlay: `
    text-$colorTemperature-$colorTemperatureStrength
    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  overlayPlaceholder: `
    text-$colorTemperature-$colorTemperatureStrength
    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  overlayLiteral: `
    text-$colorTemperature-$colorTemperatureStrength
    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  overlayChar: `
    text-$colorTemperature-$colorTemperatureStrength
    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  overlayEnum: `
    text-$colorTemperature-$colorTemperatureStrength
    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
};
