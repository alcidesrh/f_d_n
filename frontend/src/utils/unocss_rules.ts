export const utopia_rules = [
  [
    /^u-(-)?([mpg])([a-z]?)-(\d{0,2}[a-z]{0,2})$/,
    (match: string[]) => {
      const property = getProperty(match);
      if (property && property.length) {
        const temp = match[1] == "-" ? "-" : "";
        return [
          {
            [property[0]]: `var(--space${temp}-${match[4]})`,
          },
          ...(property.length > 1
            ? [
                {
                  [property[1]]: `var(--space${temp}-${match[4]})`,
                },
              ]
            : []),
        ];
      }
      return [];
    },
  ],
  [
    /^u-(-)?([a-z]+)-(\d{1,2})$/,
    (match: string[]) => {
      const prop = match[2];
      const rule: Record<string, any> = {};
      const val = `var(--step${match[1] == "-" ? "-" : ""}-${match[3]})`;

      switch (prop) {
        case "text":
          rule["font-size"] = val;
          return [rule];
        default:
          rule[prop] = val;
          return [rule];
      }
    },
  ],
  [
    /^font-(\d{1,2})$/,
    (match: string[]) => {
      return [{ "font-weight": match[1] }];
    },
  ],
  ["text-base", { "line-height": "normal" }],
];

export const color_rules = [
  [
    /^(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|surface|surface-contrast)-([1-9]|([1-9]00)|50|950)$/,
    (match: string[]) => {
      return { color: `var(--${match[1]}-${match[2]})` };
    },
  ],
];
const temp = {
  m: "margin",
  p: "padding",
  g: "gap",
};
function getProperty(match: string[]): string[] | null {
  const property: string | string[] | null = temp[match[2]];
  // match[2] == "m" ? "margin" : "padding";

  if (!property) {
    return null;
  }
  if (match[3]) {
    if (match[3] == "x" || match[3] == "y") {
      return match[3] == "x"
        ? [`${property}-left`, `${property}-right`]
        : [`${property}-top`, `${property}-bottom`];
    }
    const direction = [
      ["l", "-left"],
      ["t", "-top"],
      ["r", "-right"],
      ["b", "-bottom"],
    ];
    for (let index = 0; index < direction.length; index++) {
      const element = direction[index];
      if (element[0] == match[3]) {
        return [property + element[1]];
      }
    }
  }
  return [property];
}
