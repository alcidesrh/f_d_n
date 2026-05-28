# Plan: Responsive Two-Column Layout for DynamicForm.vue

## Current State
- Form inputs render in a single vertical stack via `<FormKitSchema>`
- Schema generated in `storeFactory.ts:277-283` as flat array of inputs
- FormKit theme at `formkit.theme.ts` controls individual input styling
- UnoCSS available with responsive breakpoints

## Changes Required

### 1. `src/components/crud/form/DynamicForm.vue`

Wrap `<FormKitSchema>` in a CSS grid container. The grid will be:
- **Mobile**: 1 column (`grid-cols-1`)
- **Tablet+**: 2 columns (`md:grid-cols-2`)
- Gap: `gap-x-4 gap-y-2`

The `#crudBtn` slot wrapper gets `md:col-span-2` to span full width.

```vue
<!-- Inside <FormKit> -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2">
  <FormKitSchema :schema="store.formSchema" :data="store.formData" :library="library">
    <template #crudBtn>
      <div class="flex flex-wrap justify-end gap-5 align-middle md:col-span-2">
        <!-- ... existing slot content ... -->
      </div>
    </template>
  </FormKitSchema>
</div>
```

### 2. `formkit.config.ts`

Add global FormKit classes so certain input types span both columns:

```ts
generateClasses({
  global: {
    label: "whitespace-nowrap",
  },
  // These should span full width in the 2-col grid
  group: {
    outer: "md:col-span-2",
  },
  textarea: {
    outer: "md:col-span-2",
  },
  file: {
    outer: "md:col-span-2",
  },
  // Fieldset $el elements get span via schema (handled in storeFactory)
})
```

### 3. `src/stores/storeFactory.ts` (line ~277)

When building `formSchema`, add `md:col-span-2` class to `$el: 'div'` and `$el: 'fieldset'` wrappers so they span full width:

```ts
this.formSchema = [
  {
    $el: 'div',
    attrs: { class: 'md:col-span-2' },
    children: '$slots.crudBtn',
  },
  ...fields.map((v) => {
    // If field is a group/fieldset, make it span 2 columns
    if (v.input?.$el === 'fieldset') {
      return {
        ...v.input,
        attrs: { ...v.input.attrs, class: 'md:col-span-2' },
      }
    }
    return v.input
  }),
]
```

## Result
- **Mobile (< 768px)**: All inputs single column
- **Tablet+ (≥ 768px)**: Two columns, textareas/fieldsets/buttons span full width
- No backend changes needed
- Uses existing UnoCSS responsive utilities
