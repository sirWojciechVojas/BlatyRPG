<template>
  <aside class="wall-hud" @pointerdown.stop>
    <button
      v-for="flag in flags"
      :key="flag.field"
      type="button"
      :class="{ active: wall[flag.field] }"
      :title="$t(flag.label)"
      :disabled="busy"
      @click="$emit('update', { [flag.field]: !wall[flag.field] })"
    >
      {{ flag.symbol }}
    </button>
    <template v-if="wall.type !== 'wall'">
      <button
        type="button"
        :class="{ active: wall.doorState === 'open' }"
        :title="$t('vtt.wall.toggleDoor')"
        :disabled="busy"
        @click="toggleDoor"
      >
        ◇
      </button>
      <button
        type="button"
        :class="{ active: wall.doorState === 'locked' }"
        :title="$t('vtt.wall.toggleLock')"
        :disabled="busy"
        @click="toggleLock"
      >
        ⌑
      </button>
      <button
        type="button"
        :class="{ active: wall.type === 'secret' }"
        :title="$t('vtt.wall.toggleSecret')"
        :disabled="busy"
        @click="
          $emit('update', { type: wall.type === 'secret' ? 'door' : 'secret' })
        "
      >
        S
      </button>
    </template>
    <button
      type="button"
      class="wall-hud__danger"
      :title="$t('vtt.wall.delete')"
      :disabled="busy"
      @click="$emit('delete')"
    >
      ×
    </button>
  </aside>
</template>

<script>
export default {
  name: "WallHud",
  props: {
    wall: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["update", "delete"],
  data: () => ({
    flags: [
      { field: "blocksMovement", symbol: "M", label: "vtt.wall.movement" },
      { field: "blocksSight", symbol: "V", label: "vtt.wall.sight" },
      { field: "blocksLight", symbol: "L", label: "vtt.wall.light" },
    ],
  }),
  methods: {
    toggleDoor() {
      this.$emit("update", {
        doorState: this.wall.doorState === "open" ? "closed" : "open",
      });
    },
    toggleLock() {
      this.$emit("update", {
        doorState: this.wall.doorState === "locked" ? "closed" : "locked",
      });
    },
  },
};
</script>
