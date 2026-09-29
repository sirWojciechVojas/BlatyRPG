<template>
  <span v-if="bars.length" class="token-resource-bars">
    <i
      v-for="(bar, index) in bars"
      :key="`${bar.label}-${index}`"
      :title="`${bar.label || '—'}: ${bar.value} / ${bar.max}`"
      :style="{ '--token-resource-color': bar.color }"
    >
      <b :style="barStyle(bar)" />
      <small>{{ bar.label }}</small>
      <em>{{ bar.value }}/{{ bar.max }}</em>
    </i>
  </span>
</template>

<script>
import {
  tokenBarPercent,
  tokenDisplayResourceBars,
} from "@/lib/vtt/tokenResources";

export default {
  name: "TokenResourceBars",
  props: {
    token: { type: Object, required: true },
  },
  computed: {
    bars() {
      return tokenDisplayResourceBars(this.token);
    },
  },
  methods: {
    barStyle(bar) {
      return {
        width: `${tokenBarPercent(bar)}%`,
        backgroundColor: bar.color,
      };
    },
  },
};
</script>
