<!-- Komponent modułu Sklep - dodaj/edytuj. Ten plik opakowuje pojedyncze pole formularza wraz z etykietą, tooltipem i komunikatem błędu. -->
<template>
  <div class="shop-editor-field" :class="fieldClass">
    <div class="shop-mode-template-label" :class="labelClass">
      <label class="trade-label" :for="forId">
        {{ label }}
      </label>
      <ShopHelpTooltip
        v-if="tooltip"
        :align="tooltipAlign"
        :label="label"
        :text="tooltip"
      />
    </div>

    <slot />

    <div v-if="error" class="trade-field-error">
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";

defineProps({
  error: {
    type: String,
    default: "",
  },
  fieldClass: {
    type: [String, Array, Object],
    default: "",
  },
  forId: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  labelClass: {
    type: [String, Array, Object],
    default: "",
  },
  tooltip: {
    type: String,
    default: "",
  },
  tooltipAlign: {
    type: String,
    default: "left",
  },
});
</script>
