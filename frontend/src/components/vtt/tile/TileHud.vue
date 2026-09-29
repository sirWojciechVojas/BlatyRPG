<template>
  <div class="tile-hud" @pointerdown.stop>
    <strong :title="tile.name">{{ tile.name }}</strong>
    <button
      type="button"
      :class="{ active: tile.layer === 'background' }"
      :title="$t('vtt.tile.background')"
      @click="update({ layer: 'background' })"
    >
      B
    </button>
    <button
      type="button"
      :class="{ active: tile.layer === 'foreground' }"
      :title="$t('vtt.tile.foreground')"
      @click="update({ layer: 'foreground' })"
    >
      F
    </button>
    <button
      type="button"
      :title="$t('vtt.tile.rotateLeft')"
      @click="rotate(-15)"
    >
      ↶
    </button>
    <button
      type="button"
      :title="$t('vtt.tile.rotateRight')"
      @click="rotate(15)"
    >
      ↷
    </button>
    <button type="button" :title="$t('vtt.tile.sendBack')" @click="order(-1)">
      ↓
    </button>
    <button type="button" :title="$t('vtt.tile.bringFront')" @click="order(1)">
      ↑
    </button>
    <label :title="$t('vtt.tile.opacity')">
      <input
        type="range"
        min="0"
        max="1"
        step="0.05"
        :value="tile.opacity"
        @change="update({ opacity: Number($event.target.value) })"
      />
    </label>
    <button
      type="button"
      :class="{ active: tile.locked }"
      :title="$t('vtt.tile.locked')"
      @click="update({ locked: !tile.locked })"
    >
      🔒
    </button>
    <button
      type="button"
      :class="{ active: tile.hidden }"
      :title="$t('vtt.tile.hidden')"
      @click="update({ hidden: !tile.hidden })"
    >
      ◉
    </button>
    <button
      v-if="tile.mediaType === 'video'"
      type="button"
      :class="{ active: tile.loop }"
      :title="$t('vtt.tile.loop')"
      @click="update({ loop: !tile.loop })"
    >
      ↻
    </button>
    <button
      type="button"
      class="tile-hud__danger"
      :title="$t('vtt.tile.delete')"
      :disabled="busy"
      @click="$emit('delete')"
    >
      ×
    </button>
  </div>
</template>

<script>
export default {
  name: "TileHud",
  props: {
    tile: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["update", "delete"],
  methods: {
    update(changes) {
      if (!this.busy) this.$emit("update", changes);
    },
    rotate(delta) {
      this.update({ rotation: this.tile.rotation + delta });
    },
    order(delta) {
      this.update({ sortOrder: this.tile.sortOrder + delta });
    },
  },
};
</script>
