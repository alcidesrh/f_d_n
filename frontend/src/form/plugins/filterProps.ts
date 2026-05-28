export default function filterProps(node) {
  node.props = util.omitKeysContaining(node.props);
  node.props = util.omitKeysContaining(node.props);
  return node;

  node.on("created", () => {
    if (node.props.definition.schemaMemoKey) {
      node.props.definition.schemaMemoKey += `${
        node.props.options ? "_multi" : ""
      }_add_asterisk2`;
    }

    const schemaFn = node.props.definition.schema;
    node.props.definition.schema = (sectionsSchema = {}) => {
      sectionsSchema[legendOrLabel] = {
        children: [
          "$label",
          {
            $el: "span",
            if: "$state.required",
            attrs: {
              class: "$classes.asterisk",
            },
            children: ["*"],
          },
        ],
      };

      return schemaFn(sectionsSchema);
    };
  });
}
