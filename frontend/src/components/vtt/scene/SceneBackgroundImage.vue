<template>
  <img
    v-if="resolvedSrc"
    :src="resolvedSrc"
    :alt="alt"
    :draggable="draggable"
    @load="handleLoad"
    @error="$emit('error')"
  />
</template>

<script>
import {
  sceneAssetApiClient,
  sceneAssetLocation,
} from "@/lib/vtt/sceneAssetApiClient";

export default {
  name: "SceneBackgroundImage",
  props: {
    src: { type: String, default: "" },
    alt: { type: String, default: "" },
    draggable: { type: Boolean, default: false },
  },
  emits: ["load", "error"],
  data: () => ({ resolvedSrc: "", objectUrl: "", generation: 0 }),
  watch: {
    src: { immediate: true, handler: "resolveSource" },
  },
  beforeUnmount() {
    this.generation += 1;
    this.releaseObjectUrl();
  },
  methods: {
    releaseObjectUrl() {
      if (this.objectUrl) URL.revokeObjectURL(this.objectUrl);
      this.objectUrl = "";
    },
    async resolveSource(value) {
      const generation = ++this.generation;
      this.releaseObjectUrl();
      this.resolvedSrc = "";
      const source = String(value || "").trim();
      if (!source) return;
      const location = sceneAssetLocation(source);
      if (!location) {
        this.resolvedSrc = source;
        return;
      }
      try {
        const blob = await sceneAssetApiClient.fetchBlob(
          location.campaignId,
          location.assetKey,
        );
        if (generation !== this.generation) return;
        this.objectUrl = URL.createObjectURL(blob);
        this.resolvedSrc = this.objectUrl;
      } catch (_error) {
        if (generation === this.generation) this.$emit("error");
      }
    },
    handleLoad(event) {
      this.$emit("load", {
        width: Number(event.target?.naturalWidth) || 0,
        height: Number(event.target?.naturalHeight) || 0,
      });
    },
  },
};
</script>
