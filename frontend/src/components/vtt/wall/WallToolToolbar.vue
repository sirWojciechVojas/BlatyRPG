<template>
  <nav
    class="wall-tool-toolbar"
    :aria-label="$t('vtt.wall.toolbar')"
    @pointerdown.stop
  >
    <button
      type="button"
      :class="{ active: interactionMode === 'select' }"
      :disabled="busy"
      @click="$emit('mode', 'select')"
    >
      ↖ {{ $t("vtt.wall.selectMode") }}
    </button>
    <button
      type="button"
      :class="{ active: interactionMode === 'draw' }"
      :title="$t('vtt.wall.drawHint')"
      :disabled="busy"
      @click="$emit('mode', 'draw')"
    >
      ╱ {{ $t("vtt.wall.drawMode") }}
    </button>
    <button
      v-if="drawing"
      type="button"
      class="active"
      :disabled="busy"
      @click="$emit('finish-drawing')"
    >
      ✓ {{ $t("vtt.wall.finishDrawing") }}
    </button>
    <button
      type="button"
      :class="{ active: interactionMode === 'door' }"
      :title="$t('vtt.wall.insertHint')"
      :disabled="busy"
      @click="$emit('mode', 'door')"
    >
      ▯ {{ $t("vtt.wall.insertDoor") }}
    </button>
    <button
      type="button"
      :class="{ active: interactionMode === 'window' }"
      :title="$t('vtt.wall.insertHint')"
      :disabled="busy"
      @click="$emit('mode', 'window')"
    >
      ▤ {{ $t("vtt.wall.insertWindow") }}
    </button>
    <button
      type="button"
      :class="{ active: snapToGrid }"
      :disabled="busy"
      @click="$emit('snap', !snapToGrid)"
    >
      # {{ $t("vtt.wall.snapToGrid") }}
    </button>
    <button
      type="button"
      :class="{ active: connectPoints }"
      :disabled="busy"
      @click="$emit('connect', !connectPoints)"
    >
      ⛓ {{ $t("vtt.wall.connectPoints") }}
    </button>
    <input
      type="color"
      :value="displayColor"
      :title="$t('vtt.wall.color')"
      :disabled="busy || !wall"
      @change="$emit('update', { color: $event.target.value })"
    />
    <button
      type="button"
      :class="{ active: allEnabled }"
      :disabled="busy || count === 0"
      @click="$emit('toggle-all-enabled')"
    >
      ◉ {{ allEnabled ? $t("vtt.wall.disableAll") : $t("vtt.wall.enableAll") }}
    </button>
    <button
      type="button"
      :class="{ active: allVisible }"
      :disabled="busy || count === 0"
      @click="$emit('toggle-all-visible')"
    >
      ◐ {{ allVisible ? $t("vtt.wall.hideAll") : $t("vtt.wall.showAll") }}
    </button>
    <button
      type="button"
      :class="{ active: listOpen }"
      @click="$emit('toggle-list')"
    >
      ☷ {{ $t("vtt.wall.managerShort") }} ({{ count }})
    </button>
    <button type="button" :disabled="busy || !wall" @click="$emit('edit')">
      ⚙ {{ $t("vtt.wall.editShort") }}
    </button>
    <button
      type="button"
      class="wall-tool-toolbar__danger"
      :disabled="busy || !wall"
      @click="$emit('delete')"
    >
      × {{ $t("vtt.wall.deleteShort") }}
    </button>
  </nav>
</template>

<script>
import { wallColor } from "@/lib/vtt/wallGeometry";

export default {
  name: "WallToolToolbar",
  props: {
    wall: { type: Object, default: null },
    interactionMode: { type: String, default: "draw" },
    connectPoints: { type: Boolean, default: true },
    snapToGrid: { type: Boolean, default: true },
    count: { type: Number, default: 0 },
    listOpen: { type: Boolean, default: true },
    allEnabled: { type: Boolean, default: true },
    allVisible: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
    drawing: { type: Boolean, default: false },
  },
  emits: [
    "mode",
    "finish-drawing",
    "connect",
    "snap",
    "update",
    "toggle-all-enabled",
    "toggle-all-visible",
    "toggle-list",
    "edit",
    "delete",
  ],
  computed: {
    displayColor() {
      return wallColor(this.wall || { type: "wall" });
    },
  },
};
</script>
