<template>
  <form class="token-resource-quick" @submit.prevent="save">
    <header>
      <strong>{{ $t("vtt.token.resources.quickTitle") }}</strong>
      <button type="button" @click="$emit('close')">×</button>
    </header>
    <p v-if="!active.bars.length && !active.bubbles.length">
      {{ $t("vtt.token.resources.empty") }}
    </p>
    <label v-for="bar in active.bars" :key="bar.index">
      <span
        ><i :style="{ background: bar.item.color }" />{{
          bar.item.label || `#${bar.index + 1}`
        }}</span
      >
      <input
        v-model.number="draft.bars[bar.index].value"
        type="number"
        step="any"
        :disabled="isMovementBar(bar)"
      />
      <b>/</b>
      <input
        v-model.number="draft.bars[bar.index].max"
        type="number"
        step="any"
        :disabled="isMovementBar(bar)"
      />
    </label>
    <label v-for="bubble in active.bubbles" :key="`bubble-${bubble.index}`">
      <span>{{ bubble.item.label || `#${bubble.index + 1}` }}</span>
      <input
        v-model.number="draft.bubbles[bubble.index].value"
        type="number"
        step="any"
      />
    </label>
    <footer v-if="active.bars.length || active.bubbles.length">
      <button type="button" @click="$emit('close')">
        {{ $t("vtt.token.settings.cancel") }}
      </button>
      <button type="submit" class="primary">
        {{ $t("vtt.token.settings.save") }}
      </button>
    </footer>
  </form>
</template>

<script>
import {
  cloneTokenResources,
  normalizeTokenResources,
} from "@/lib/vtt/tokenResources";

export default {
  name: "TokenResourceQuickPanel",
  props: {
    resources: { type: Object, default: () => ({}) },
  },
  emits: ["save", "close"],
  data() {
    return { draft: cloneTokenResources(this.resources) };
  },
  computed: {
    active() {
      const indexed = (items) =>
        items
          .map((item, index) => ({ item, index }))
          .filter(({ item }) => item.enabled);
      return {
        bars: indexed(this.draft.bars),
        bubbles: indexed(this.draft.bubbles),
      };
    },
  },
  methods: {
    isMovementBar(bar) {
      return bar.index === 1 && bar.item.label.toLocaleUpperCase() === "PR";
    },
    save() {
      this.$emit("save", normalizeTokenResources(this.draft));
    },
  },
};
</script>
