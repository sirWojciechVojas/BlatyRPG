<template>
  <button
    class="ui-button"
    :class="[
      `ui-button--${variant}`,
      `ui-button--${size}`,
      {
        'ui-button--block': block,
        'ui-button--icon': iconOnly,
        'is-loading': loading,
      },
    ]"
    :type="type"
    :disabled="disabled || loading"
    :aria-busy="loading || undefined"
    :aria-label="ariaLabel || undefined"
  >
    <span v-if="loading" class="ui-button__spinner" aria-hidden="true" />
    <span v-if="$slots.icon" class="ui-button__icon" aria-hidden="true">
      <slot name="icon" />
    </span>
    <span v-if="!iconOnly || $slots.default" class="ui-button__label">
      <slot />
    </span>
  </button>
</template>

<script>
const BUTTON_VARIANTS = ["default", "primary", "danger", "ghost"];
const BUTTON_SIZES = ["small", "medium"];

export default {
  name: "UiButton",
  props: {
    type: {
      type: String,
      default: "button",
    },
    variant: {
      type: String,
      default: "default",
      validator: (value) => BUTTON_VARIANTS.includes(value),
    },
    size: {
      type: String,
      default: "medium",
      validator: (value) => BUTTON_SIZES.includes(value),
    },
    disabled: Boolean,
    loading: Boolean,
    block: Boolean,
    iconOnly: Boolean,
    ariaLabel: {
      type: String,
      default: "",
    },
  },
};
</script>
