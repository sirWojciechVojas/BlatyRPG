<template><span class="wall-spatial-audio" aria-hidden="true" /></template>

<script>
import { wallSoundListenerPoint } from "@/lib/vtt/wallSound";
import { wallAudioRuntime } from "@/services/wallAudioRuntime";

export default {
  name: "WallSpatialAudioController",
  props: {
    scene: { type: Object, default: null },
    walls: { type: Array, default: () => [] },
    tokens: { type: Array, default: () => [] },
    selectedTokenId: { type: [Number, String], default: null },
    selectedTokenIds: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
  },
  data: () => ({ syncTimer: null }),
  computed: {
    tracks() {
      return this.$store.state.jukebox?.tracks || [];
    },
    listeners() {
      if (this.canManage) {
        const selected = this.selectedTokenIds.length
          ? this.selectedTokenIds
          : this.selectedTokenId === null
            ? []
            : [this.selectedTokenId];
        if (selected.length !== 1) return [];
        const token = this.tokens.find(
          (item) => String(item.id) === String(selected[0]),
        );
        return token ? [wallSoundListenerPoint(token)] : [];
      }
      return this.tokens
        .filter((token) => token.capabilities?.canControl === true)
        .map(wallSoundListenerPoint);
    },
  },
  watch: {
    scene: { deep: true, handler: "refresh" },
    walls: { deep: true, handler: "refresh" },
    tokens: { deep: true, handler: "refresh" },
    tracks: { deep: true, handler: "refresh" },
    listeners: { deep: true, handler: "refresh" },
  },
  mounted() {
    this.refresh();
  },
  beforeUnmount() {
    window.clearTimeout(this.syncTimer);
    wallAudioRuntime.clear();
  },
  methods: {
    refresh() {
      wallAudioRuntime.setContext({
        scene: this.scene,
        walls: this.walls,
        tracks: this.tracks,
        listeners: this.listeners,
      });
      this.scheduleSecureSync();
    },
    scheduleSecureSync() {
      if (!this.scene?.id || this.syncTimer !== null) return;
      this.syncTimer = window.setTimeout(() => {
        this.syncTimer = null;
        const selected = this.selectedTokenIds.length
          ? this.selectedTokenIds
          : this.selectedTokenId === null
            ? []
            : [this.selectedTokenId];
        this.$store
          .dispatch("realtime/syncWallAudio", {
            sceneId: this.scene.id,
            selectedTokenId:
              this.canManage && selected.length === 1 ? selected[0] : null,
          })
          .catch(() => {});
      }, 120);
    },
  },
};
</script>
