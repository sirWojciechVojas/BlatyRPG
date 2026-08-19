<template>
  <span
    class="ui-tooltip"
    :class="`ui-tooltip--${placement}`"
    :data-tooltip-open="open || undefined"
  >
    <slot name="trigger" :tooltip-id="tooltipId">
      <button
        type="button"
        class="ui-tooltip__trigger"
        :aria-label="label || text"
        :aria-describedby="tooltipId"
        @click="open = !open"
        @blur="open = false"
        @keydown.esc="open = false"
      >
        <slot name="icon">?</slot>
      </button>
    </slot>
    <span :id="tooltipId" class="ui-tooltip__bubble" role="tooltip">
      <slot>{{ text }}</slot>
    </span>
  </span>
</template>

<script>
let tooltipSequence = 0;
const TOOLTIP_PLACEMENTS = ["top", "right", "bottom", "left"];

export default {
  name: "UiTooltip",
  props: {
    text: {
      type: String,
      required: true,
    },
    label: {
      type: String,
      default: "",
    },
    placement: {
      type: String,
      default: "top",
      validator: (value) => TOOLTIP_PLACEMENTS.includes(value),
    },
  },
  data() {
    tooltipSequence += 1;
    return {
      open: false,
      tooltipId: "ui-tooltip-" + tooltipSequence,
    };
  },
};
</script>
