<!-- Responsibility: TradeCommandButton shop interface component. -->
<template>
  <button
    :id="buttonId"
    type="button"
    class="btn btn-sm trade-command-button fantasy-command-button"
    :class="[`trade-command-button--${variant}`]"
    :disabled="disabled"
    :aria-label="displayLabel"
    :title="displayLabel"
    @click="$emit('click')"
  >
    <span>{{ displayLabel }}</span>
  </button>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  buttonId: {
    type: String,
    default: "",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    required: true,
  },
  variant: {
    type: String,
    default: "buy",
  },
});

defineEmits(["click"]);

const displayLabel = computed(() => {
  const normalized = String(props.label || "")
    .trim()
    .toLocaleLowerCase("pl");
  if (!normalized) {
    return "";
  }
  return normalized.charAt(0).toLocaleUpperCase("pl") + normalized.slice(1);
});
</script>
