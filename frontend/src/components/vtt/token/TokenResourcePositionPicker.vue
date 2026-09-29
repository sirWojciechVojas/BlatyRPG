<template>
  <section class="token-resource-position-picker">
    <div>
      <strong>{{ $t("vtt.token.resources.barPosition") }}</strong>
      <small>{{ $t("vtt.token.resources.barPositionHint") }}</small>
    </div>
    <div class="token-resource-position-picker__options" role="radiogroup">
      <button
        v-for="position in positions"
        :key="position"
        type="button"
        role="radio"
        :class="{ active: normalized === position }"
        :aria-checked="normalized === position"
        @click="$emit('update:modelValue', position)"
      >
        <span :class="`token-resource-position-picker__mock--${position}`">
          <i aria-hidden="true" />
          <b aria-hidden="true"><em /><em /><em /></b>
        </span>
        <small>{{ $t(`vtt.token.resourceBarPositions.${position}`) }}</small>
      </button>
    </div>
  </section>
</template>

<script>
import {
  TOKEN_RESOURCE_BAR_POSITIONS,
  normalizeTokenResourceBarPosition,
} from "@/lib/vtt/tokenResourcePosition";

export default {
  name: "TokenResourcePositionPicker",
  props: {
    modelValue: { type: String, default: "below" },
  },
  emits: ["update:modelValue"],
  data: () => ({ positions: TOKEN_RESOURCE_BAR_POSITIONS }),
  computed: {
    normalized() {
      return normalizeTokenResourceBarPosition(this.modelValue);
    },
  },
};
</script>
