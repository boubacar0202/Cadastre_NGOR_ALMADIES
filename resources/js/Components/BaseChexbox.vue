<script setup>
  import { computed, ref } from "vue"

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: String,
    name: String,
    id: String,
    wrapperClass: { type: String, default: "sm:col-span-1" },
    required: Boolean,
    disabled: Boolean,
    error: String,
    customError: String,
    fontSize: { type: String, default: "0.875rem" },
  })

  const emit = defineEmits(["update:modelValue", "change"])

  const inputEl = ref(null)
  const inputId = computed(() => props.id || props.name || undefined)

  function onChange(event) {
    emit("update:modelValue", event.target.checked)
    emit("change", event)
  }

  defineExpose({ focus: () => inputEl.value?.focus() })
</script>

<template>
  <div :class="wrapperClass">
    <label
      :for="inputId"
      class="inline-flex items-center gap-2 select-none"
      :class="disabled ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'"
    >
      <input
        ref="inputEl"
        type="checkbox"
        :id="inputId"
        :name="name"
        :checked="modelValue"
        :required="required"
        :disabled="disabled"
        :aria-label="label || undefined"
        class="h-4 w-4 rounded border-gray-300 text-primary shadow-sm transition focus:ring-2 focus:ring-primary/25 focus:ring-offset-0 disabled:cursor-not-allowed"
        :class="(error || customError) ? 'border-red-500' : ''"
        @change="onChange"
      />
      <span
        v-if="label"
        class="text-sm font-semibold text-primary"
        :style="{ fontSize: fontSize }"
      >
        {{ label }}
        <span v-if="required" class="text-red-500 ml-0.5">*</span>
      </span>
    </label>

    <p v-if="error || customError" class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
      <svg class="h-3.5 w-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-4a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
      </svg>
      <span :style="{ fontSize: fontSize }">
        {{ error || customError }}
      </span>
    </p>
  </div>
</template>
