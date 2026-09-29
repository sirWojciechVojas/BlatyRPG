<template>
  <div class="scene-tile-media" :class="{ 'scene-tile-media--failed': failed }">
    <video
      v-if="tile.mediaType === 'video' && !failed"
      :src="tile.assetUrl"
      :autoplay="tile.autoplay"
      :loop="tile.loop"
      :muted="tile.muted"
      playsinline
      preload="metadata"
      @error="failed = true"
    />
    <img
      v-else-if="!failed"
      :src="tile.assetUrl"
      :alt="tile.name"
      draggable="false"
      @error="failed = true"
    />
    <span v-else>{{ tile.name }}</span>
  </div>
</template>

<script>
export default {
  name: "TileMedia",
  props: { tile: { type: Object, required: true } },
  data: () => ({ failed: false }),
  watch: {
    "tile.assetUrl"() {
      this.failed = false;
    },
  },
};
</script>
