<template>
  <TreeSelect
    v-bind="treeAttrs"
    :model-value="context._value"
    :input-id="context.id"
    :name="context.node.name"
    :disabled="disabled"
    :invalid="invalid"
    :class="context.classes.input"
    :expanded-keys="resolvedExpandedKeys"
    :filter="filter"
    :filter-placeholder="filterPlaceholder"
    @update:model-value="update"
    @blur="blur"
  />
</template>
<script setup lang="ts">
import type { FormKitFrameworkContext } from '@formkit/core'
import { computed } from 'vue'
import { useFormKitInput } from './useFormKitInput'

defineOptions({ name: 'FkTreeSelect' })

interface TreeNode {
  key?: string
  label?: string
  data?: unknown
  children?: TreeNode[]
}

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)

const filter = computed(() => (props.context.attrs.filter as boolean) ?? false)
const filterPlaceholder = computed(() => (props.context.attrs.filterPlaceholder as string) ?? 'Buscar...')
const autoExpand = computed(() => (props.context.attrs.autoExpand as boolean) ?? true)

function collectAllKeys(nodes: TreeNode[]): Record<string, boolean> {
  const keys: Record<string, boolean> = {}
  for (const node of nodes) {
    if (node.children && node.children.length > 0) {
      const key = node.key ?? node.label ?? ''
      if (key) keys[key] = true
      Object.assign(keys, collectAllKeys(node.children))
    }
  }
  return keys
}

const resolvedExpandedKeys = computed(() => {
  const explicit = props.context.attrs.expandedKeys as Record<string, boolean> | undefined
  if (explicit) return explicit
  if (!autoExpand.value) return {}
  const options = (props.context.attrs.options ?? []) as TreeNode[]
  return collectAllKeys(options)
})

const treeAttrs = computed(() => {
  const { options, expandedKeys, filter: _f, filterPlaceholder: _fp, autoExpand: _ae, ...rest } = props.context.attrs as Record<string, unknown>
  return rest
})
</script>
