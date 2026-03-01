<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  checked?: boolean
  disabled?: boolean
  class?: HTMLAttributes['class']
}>()

const emits = defineEmits<{
  (e: 'update:checked', payload: boolean): void
}>()

const toggle = () => {
  if (props.disabled) return
  emits('update:checked', !props.checked)
}
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="checked"
    :disabled="disabled"
    :class="cn(
      'peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
      props.class
    )"
    :data-state="checked ? 'checked' : 'unchecked'"
    @click="toggle"
  >
    <span
      :data-state="checked ? 'checked' : 'unchecked'"
      :class="cn(
        'pointer-events-none block h-4 w-4 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0'
      )"
    />
  </button>
</template>
