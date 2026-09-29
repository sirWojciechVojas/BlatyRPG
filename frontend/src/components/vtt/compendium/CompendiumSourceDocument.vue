<template>
  <div class="compendium-source-document">
    <div
      ref="content"
      class="compendium-source-document__content"
      @click="handleClick"
      v-html="html"
    />
  </div>
</template>

<script>
export default {
  name: "CompendiumSourceDocument",
  props: {
    html: { type: String, default: "" },
    links: { type: Array, default: () => [] },
  },
  emits: ["navigate"],
  methods: {
    handleClick(event) {
      const anchor = event.target?.closest?.("a");
      if (!anchor || !this.$refs.content?.contains(anchor)) return;
      const sourceId = anchor.dataset.compendiumSourceId;
      if (!sourceId) return;
      event.preventDefault();
      const target = this.links.find((link) => link.sourceId === sourceId);
      if (target?.targetEntryId) {
        this.$emit("navigate", {
          entryId: target.targetEntryId,
          anchor: anchor.dataset.compendiumAnchor || target.anchor || null,
        });
      }
    },
  },
};
</script>
