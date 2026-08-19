<!-- Responsibility: TradeTypeFilterRail shop interface component. -->
<template>
  <div
    v-if="options.length"
    class="trade-filter-rail"
    :class="railClass"
    :aria-label="ariaLabel"
  >
    <button
      v-for="option in options"
      :key="`${side}-type-filter-${option.value}`"
      type="button"
      class="trade-filter-btn"
      :class="{ active: activeValue === option.value }"
      :title="optionTitle(option)"
      :aria-label="optionTitle(option)"
      :aria-pressed="String(activeValue === option.value)"
      @click="$emit('select', option.value)"
    >
      <span v-if="option.value === 'all'" class="trade-filter-btn__all">
        {{ allLabel }}
      </span>
      <span
        v-else
        class="trade-filter-btn__icon trade-icon inventory-item legacy-inventory-icon"
        :class="option.iconClass"
        role="img"
        :aria-label="option.label"
      ></span>
      <span v-if="option.count" class="trade-filter-btn__count">
        {{ option.count }}
      </span>
    </button>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  activeValue: {
    type: [String, Number],
    default: "",
  },
  allLabel: {
    type: String,
    default: "ALL",
  },
  ariaLabel: {
    type: String,
    default: "",
  },
  options: {
    type: Array,
    default: () => [],
  },
  side: {
    type: String,
    default: "left",
  },
});

defineEmits(["select"]);

const railClass = computed(() =>
  props.side === "right"
    ? "trade-filter-rail--right"
    : "trade-filter-rail--left",
);

const optionTitle = (option = {}) => `${option.label} (${option.count})`;
</script>
