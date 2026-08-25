<template>
  <span v-if="hasResources" class="token-resource-overlay">
    <span
      v-for="entry in bubbleEntries"
      :key="`${entry.item.position}-${entry.index}`"
      class="token-resource-bubble"
      :class="[
        `token-resource-bubble--${entry.item.position}`,
        `token-resource-bubble--slot-${entry.index + 1}`,
        { 'token-resource-bubble--editable': editable },
      ]"
      :role="editable ? 'button' : undefined"
      :tabindex="editable ? 0 : undefined"
      :title="editTitle(entry.item)"
      @pointerdown.stop
      @click.stop="startEdit(entry)"
      @keydown.enter.stop.prevent="startEdit(entry)"
    >
      <input
        v-if="editingIndex === entry.index"
        ref="bubbleInput"
        v-model="draftValue"
        type="number"
        step="any"
        @click.stop
        @keydown.enter.stop.prevent="commitEdit"
        @keydown.esc.stop.prevent="cancelEdit"
        @blur="commitEdit"
      />
      <template v-else>
        <small v-if="entry.item.label">{{ entry.item.label }}</small>
        <b>{{ entry.item.value }}</b>
      </template>
    </span>
  </span>
</template>

<script>
import {
  cloneTokenResources,
  normalizeTokenResources,
} from "@/lib/vtt/tokenResources";

export default {
  name: "TokenResourceOverlay",
  props: {
    resources: { type: Object, default: () => ({}) },
    editable: { type: Boolean, default: false },
  },
  emits: ["update"],
  data: () => ({ editingIndex: null, draftValue: "" }),
  computed: {
    hasResources() {
      return this.bubbleEntries.length > 0;
    },
    bubbleEntries() {
      return normalizeTokenResources(this.resources)
        .bubbles.map((item, index) => ({ item, index }))
        .filter(({ item }) => item.enabled);
    },
  },
  methods: {
    editTitle(bubble) {
      if (!this.editable) return bubble.label || String(bubble.value);
      return this.$t("vtt.token.resources.editBubble", {
        label: bubble.label || this.$t("vtt.token.resources.value"),
      });
    },
    startEdit(entry) {
      if (!this.editable || this.editingIndex === entry.index) return;
      this.editingIndex = entry.index;
      this.draftValue = String(entry.item.value);
      this.$nextTick(() => {
        const reference = this.$refs.bubbleInput;
        const input = Array.isArray(reference) ? reference[0] : reference;
        input?.select();
      });
    },
    commitEdit() {
      if (this.editingIndex === null) return;
      const value = Number(this.draftValue);
      const index = this.editingIndex;
      this.editingIndex = null;
      if (!Number.isFinite(value)) return;
      const next = cloneTokenResources(this.resources);
      next.bubbles[index].value = value;
      this.$emit("update", next);
    },
    cancelEdit() {
      this.editingIndex = null;
    },
  },
};
</script>
