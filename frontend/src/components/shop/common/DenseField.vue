<!-- Responsibility: DenseField shop interface component. -->
<template>
  <component
    :is="group ? 'div' : 'label'"
    class="dense-field"
    :class="{ 'dense-field--wide': wide }"
    :role="group ? 'group' : undefined"
    :aria-label="group ? label : undefined"
  >
    <span class="dense-field__label">
      {{ label }}
      <span v-if="required" aria-hidden="true">*</span>
      <ShopHelpTooltip
        v-if="tooltip"
        :label="label"
        :text="tooltip"
        align="left"
      />
    </span>
    <slot />
    <span v-if="hint" class="dense-field__hint">{{ hint }}</span>
    <span v-if="error" class="dense-field__error" role="alert">
      {{ error }}
    </span>
  </component>
</template>

<script setup>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";

defineProps({
  label: { type: String, required: true },
  hint: { type: String, default: "" },
  error: { type: String, default: "" },
  required: { type: Boolean, default: false },
  wide: { type: Boolean, default: false },
  group: { type: Boolean, default: false },
  tooltip: { type: String, default: "" },
});
</script>

<style scoped>
.dense-field {
  display: grid;
  min-width: 0;
  gap: 0.22rem;
  margin: 0;
}
.dense-field--wide {
  grid-column: 1 / -1;
}
.dense-field__label {
  display: inline-flex;
  align-items: center;
  gap: 0.22rem;
  color: var(--shop-muted, #a9adb5);
  font-size: 0.68rem;
  font-weight: 650;
  letter-spacing: 0.01em;
  line-height: 1.1;
  text-transform: none;
}
.dense-field :deep(input),
.dense-field :deep(select),
.dense-field :deep(textarea) {
  width: 100%;
  min-height: 2rem;
  border: 1px solid var(--shop-border, #343841);
  border-radius: 0.28rem;
  background: var(--shop-input, #15171b);
  color: var(--shop-text, #eff1f4);
  padding: 0.35rem 0.48rem;
  font: inherit;
}
.dense-field :deep(textarea) {
  min-height: 5.2rem;
  resize: vertical;
}
.dense-field :deep(:focus-visible) {
  outline: 2px solid var(--shop-focus, #d0a862);
  outline-offset: 1px;
}
.dense-field__hint,
.dense-field__error {
  font-size: 0.7rem;
  line-height: 1.2;
}
.dense-field__hint {
  color: var(--shop-muted, #969ba4);
}
.dense-field__error {
  color: #ef8b8b;
}
</style>
