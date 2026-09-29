<template>
  <form class="tile-creator" @submit.prevent="submit" @pointerdown.stop>
    <input
      v-model.trim="assetUrl"
      type="text"
      required
      maxlength="2048"
      :placeholder="$t('vtt.tile.assetUrl')"
      :aria-label="$t('vtt.tile.assetUrl')"
    />
    <select v-model="mediaType" :aria-label="$t('vtt.tile.mediaType')">
      <option value="image">{{ $t("vtt.tile.image") }}</option>
      <option value="video">{{ $t("vtt.tile.video") }}</option>
    </select>
    <select v-model="layer" :aria-label="$t('vtt.tile.layer')">
      <option value="background">{{ $t("vtt.tile.background") }}</option>
      <option value="foreground">{{ $t("vtt.tile.foreground") }}</option>
    </select>
    <button type="submit" :disabled="busy" :title="$t('vtt.tile.create')">
      +
    </button>
  </form>
</template>

<script>
export default {
  name: "TileCreator",
  props: {
    scene: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["create"],
  data: () => ({ assetUrl: "", mediaType: "image", layer: "background" }),
  watch: {
    assetUrl(value) {
      if (/\.(?:webm|mp4|ogv)(?:[?#].*)?$/iu.test(value)) {
        this.mediaType = "video";
      }
    },
  },
  methods: {
    submit() {
      if (!this.assetUrl || this.busy) return;
      const grid = Math.max(8, Number(this.scene.gridSize) || 100);
      const file =
        this.assetUrl.split(/[/?#]/u).filter(Boolean).pop() || "Tile";
      this.$emit("create", {
        name: file.slice(0, 150),
        assetUrl: this.assetUrl,
        mediaType: this.mediaType,
        layer: this.layer,
        x: grid,
        y: grid,
        width: grid * 4,
        height: grid * 4,
      });
      this.assetUrl = "";
    },
  },
};
</script>
