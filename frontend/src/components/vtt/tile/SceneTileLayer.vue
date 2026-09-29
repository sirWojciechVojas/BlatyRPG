<template>
  <div class="scene-tile-layer">
    <TileMedia
      v-for="tile in sortedTiles"
      :key="tile.id"
      :tile="tile"
      :class="tileClasses(tile)"
      :style="tileStyle(tile)"
    />
    <div
      v-if="canManage && active"
      class="scene-tile-editor"
      @pointermove="move"
      @pointerup="finish"
      @pointercancel="cancel"
      @click.self="$emit('select', null)"
    >
      <div
        v-for="tile in sortedTiles"
        :key="`editor-${tile.id}`"
        class="scene-tile-frame"
        :class="{
          'scene-tile-frame--selected': tile.id === selectedId,
          'scene-tile-frame--locked': tile.locked,
        }"
        :style="frameStyle(tile)"
        @pointerdown.stop="start($event, tile, 'move')"
        @click.stop="$emit('select', tile.id)"
      >
        <span>{{ tile.name }}</span>
        <button
          v-if="tile.id === selectedId"
          type="button"
          class="scene-tile-frame__resize"
          :aria-label="$t('vtt.tile.resize')"
          @pointerdown.stop="start($event, tile, 'resize')"
        />
      </div>
      <TileCreator
        :scene="scene"
        :busy="busy"
        @create="$emit('create', $event)"
      />
    </div>
    <TileHud
      v-if="canManage && active && selectedTile"
      :tile="selectedTile"
      :busy="busy"
      :style="hudStyle"
      @update="$emit('update', { tile: selectedTile, changes: $event })"
      @delete="$emit('delete', selectedTile)"
    />
  </div>
</template>

<script>
import TileCreator from "./TileCreator.vue";
import TileHud from "./TileHud.vue";
import TileMedia from "./TileMedia.vue";

export default {
  name: "SceneTileLayer",
  components: { TileCreator, TileHud, TileMedia },
  props: {
    scene: { type: Object, required: true },
    tiles: { type: Array, default: () => [] },
    activeTool: { type: String, default: "select" },
    selectedId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    scale: { type: Number, default: 1 },
  },
  emits: ["select", "create", "update", "delete"],
  data: () => ({ drag: null, preview: {} }),
  computed: {
    active() {
      return this.activeTool === "tiles";
    },
    sortedTiles() {
      return [...this.tiles].sort(
        (left, right) =>
          left.layer.localeCompare(right.layer) ||
          left.sortOrder - right.sortOrder ||
          left.id - right.id,
      );
    },
    selectedTile() {
      return this.tiles.find((tile) => tile.id === this.selectedId) || null;
    },
    hudStyle() {
      return {
        left: `${this.selectedTile.x + this.selectedTile.width / 2}px`,
        top: `${this.selectedTile.y}px`,
      };
    },
  },
  watch: {
    active(value) {
      if (!value) this.cancel();
    },
  },
  methods: {
    display(tile) {
      return this.preview[tile.id] || tile;
    },
    tileStyle(tile) {
      const value = this.display(tile);
      return {
        width: `${value.width}px`,
        height: `${value.height}px`,
        opacity: tile.hidden ? tile.opacity * 0.35 : tile.opacity,
        transform: `translate(${value.x}px, ${value.y}px) rotate(${tile.rotation}deg)`,
        zIndex: tile.layer === "foreground" ? 10 : 2,
      };
    },
    frameStyle(tile) {
      const value = this.display(tile);
      return {
        width: `${value.width}px`,
        height: `${value.height}px`,
        transform: `translate(${value.x}px, ${value.y}px) rotate(${tile.rotation}deg)`,
      };
    },
    tileClasses(tile) {
      return [
        `scene-tile-media--${tile.layer}`,
        { "scene-tile-media--hidden": tile.hidden },
      ];
    },
    start(event, tile, mode) {
      this.$emit("select", tile.id);
      if (this.busy || tile.locked || event.button !== 0) return;
      this.drag = {
        pointerId: event.pointerId,
        clientX: event.clientX,
        clientY: event.clientY,
        tile,
        mode,
      };
      this.preview = { ...this.preview, [tile.id]: { ...tile } };
      event.currentTarget.setPointerCapture?.(event.pointerId);
    },
    move(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      const { tile, mode, clientX, clientY } = this.drag;
      const factor = Math.max(0.05, this.scale);
      const dx = (event.clientX - clientX) / factor;
      const dy = (event.clientY - clientY) / factor;
      const value =
        mode === "resize"
          ? {
              ...tile,
              width: Math.min(50000, Math.max(8, tile.width + dx)),
              height: Math.min(50000, Math.max(8, tile.height + dy)),
            }
          : {
              ...tile,
              x: Math.min(this.scene.width, Math.max(0, tile.x + dx)),
              y: Math.min(this.scene.height, Math.max(0, tile.y + dy)),
            };
      this.preview = { ...this.preview, [tile.id]: value };
    },
    finish(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      const { tile, mode } = this.drag;
      const value = this.preview[tile.id];
      const changes =
        mode === "resize"
          ? { width: value.width, height: value.height }
          : { x: value.x, y: value.y };
      this.cancel();
      this.$emit("update", { tile, changes });
    },
    cancel() {
      this.drag = null;
      this.preview = {};
    },
  },
};
</script>
