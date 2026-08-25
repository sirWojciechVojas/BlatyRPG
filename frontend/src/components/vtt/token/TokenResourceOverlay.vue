<template>
  <span v-if="hasResources" class="token-resource-overlay" aria-hidden="true">
    <span v-if="active.bars.length" class="token-resource-bars">
      <i
        v-for="(bar, index) in active.bars"
        :key="`${bar.label}-${index}`"
        :title="barTitle(bar)"
      >
        <b :style="barStyle(bar)" />
        <small>{{ bar.label }}</small>
        <em>{{ bar.value }}/{{ bar.max }}</em>
      </i>
    </span>
    <i
      v-for="(bubble, index) in active.bubbles"
      :key="`${bubble.position}-${index}`"
      class="token-resource-bubble"
      :class="`token-resource-bubble--${bubble.position}`"
    >
      <small v-if="bubble.label">{{ bubble.label }}</small>
      <b>{{ bubble.value }}</b>
    </i>
  </span>
</template>

<script>
import {
  activeTokenResources,
  tokenBarPercent,
} from "@/lib/vtt/tokenResources";

export default {
  name: "TokenResourceOverlay",
  props: {
    resources: { type: Object, default: () => ({}) },
  },
  computed: {
    active() {
      return activeTokenResources(this.resources);
    },
    hasResources() {
      return this.active.bars.length > 0 || this.active.bubbles.length > 0;
    },
  },
  methods: {
    barStyle(bar) {
      return {
        width: `${tokenBarPercent(bar)}%`,
        backgroundColor: bar.color,
      };
    },
    barTitle(bar) {
      return `${bar.label || "—"}: ${bar.value} / ${bar.max}`;
    },
  },
};
</script>
