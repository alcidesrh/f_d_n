export default {
  // https://formkit.com/inputs/select#sections
  outer: ``,
  wrapper: ``,
  label: ``,
  prefixIcon: `
    ml-$spacing
    text-$colorTemperature-$colorTemperatureStrength

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  suffixIcon: `
    mr-$spacing
    text-$colorTemperature-$colorTemperatureStrength

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  inner: ``,
  input: ``,
  selectIcon: `
    absolute
    w-[1em]
    text-$colorTemperature-$colorTemperatureStrength
    pointer-events-none
    right-$spacing
    group-data-[suffix-icon]:mr-[1.5em]

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
  `,
  optGroup: `
    group/optgroup
    bg-white
    text-$colorTemperature-$colorTemperatureStrength
    font-bold
    text-$scale(-1,*)

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
    dark:bg-$colorTemperature-900
  `,
  option: `
    text-$colorTemperature-$colorTemperatureStrength
    group-data-[multiple]:text-$scale(-1,*)
    group-data-[multiple]:outline-hidden
    group-data-[multiple]:border-none
    group-data-[multiple]:py-$spacing(-1)
    group-data-[multiple]:px-$spacing

    dark:text-$colorTemperature-$colorTemperatureStrengthDark
    dark:bg-$colorTemperature-900
  `,
  help: ``,
  messages: ``,
  message: ``,
};
