export default {
  /*
  color: https://formkit.com/inputs/color#sections
  colorpicker (Pro): https://formkit.com/inputs/colorpicker#sections
  date: https://formkit.com/inputs/date#sections
  datetime-local: https://formkit.com/inputs/datetime-local#sections
  email: https://formkit.com/inputs/email#sections
  mask (Pro): https://formkit.com/inputs/mask#sections
  month: https://formkit.com/inputs/month#sections
  number: https://formkit.com/inputs/number#sections
  password: https://formkit.com/inputs/password#sections
  range: https://formkit.com/inputs/range#sections
  search: https://formkit.com/inputs/search#sections
  tel: https://formkit.com/inputs/tel#sections
  text: https://formkit.com/inputs/text#sections
  time: https://formkit.com/inputs/time#sections
  url: https://formkit.com/inputs/url#sections
  week: https://formkit.com/inputs/week#sections
  */

  /** === ⚠️ CAUTION: Shared styles ===
  The text family of inputs all have very similar underlying FormKit schemas, so
  it is very likely that you'll be able to do 90% of your required styling within this
  "family:text" group. However, scaffolding for each input within the text family
  is provided within this file so that you can override individual inputs if needed.

  Some inputs within the family such as `color`, `file`, `mask (Pro)`, and `range` will
  required their own additional consideration within your theme.
  */
  outer: ``,
  wrapper: ``,
  label: ``,
  prefixIcon: `
    text-$colorTemperature-600
    dark:text-$colorTemperature-400
  `,
  suffixIcon: `
    text-$colorTemperature-600
    dark:text-$colorTemperature-400
  `,
  inner: ``,
  input: ``,
  help: ``,
  messages: ``,
  message: ``,
};
