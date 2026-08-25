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
        :value="draft.bars[bar.index].value"
        type="number"
        step="any"
        :disabled="isMovementBar(bar) && !canManageMovement"
        @input="syncBar(bar.index, 'value', $event.target.value)"
      />
      <b>/</b>
      <input
        :value="draft.bars[bar.index].max"
        type="number"
        step="any"
        :disabled="isMovementBar(bar) && !canManageMovement"
        @input="syncBar(bar.index, 'max', $event.target.value)"
      />
    </label>
    <label v-for="bubble in active.bubbles" :key="`bubble-${bubble.index}`">
      <span>{{ bubble.item.label || `#${bubble.index + 1}` }}</span>
      <input
        :value="draft.bubbles[bubble.index].value"
        type="number"
        step="any"
        :disabled="bubbleLocked(bubble)"
        @input="syncBubble(bubble.index, $event.target.value)"
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
  synchronizeTokenResourceLinks,
  updateLinkedTokenBubble,
} from "@/lib/vtt/tokenResources";

export default {
  name: "TokenResourceQuickPanel",
  props: {
    resources: { type: Object, default: () => ({}) },
    canManageMovement: { type: Boolean, default: false },
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
      return bar.item.movementSource;
    },
    bubbleLocked(bubble) {
      const linked = bubble.item.linkedBarIndex;
      return (
        linked !== null &&
        this.draft.bars[linked].movementSource &&
        !this.canManageMovement
      );
    },
    syncBar(index, field, value) {
      this.draft.bars[index][field] = Number(value) || 0;
      this.draft = synchronizeTokenResourceLinks(this.draft);
    },
    syncBubble(index, value) {
      this.draft.bubbles[index].value = Number(value) || 0;
      this.draft = updateLinkedTokenBubble(this.draft, index);
    },
    save() {
      this.$emit("save", synchronizeTokenResourceLinks(this.draft));
    },
  },
};
</script>
