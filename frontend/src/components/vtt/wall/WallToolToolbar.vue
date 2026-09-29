<template>
  <nav
    class="wall-tool-toolbar"
    :aria-label="$t('vtt.wall.toolbar')"
    @pointerdown.stop
  >
    <SceneToolIconButton
      icon="cursor"
      :label="$t('vtt.wall.selectMode')"
      :description="$t('vtt.wall.tooltips.select')"
      shortcut="↑ ↓ ← → · Shift · Alt · Ctrl/Cmd+C/V"
      :active="interactionMode === 'select'"
      :disabled="busy"
      toggle
      @click="$emit('mode', 'select')"
    />
    <SceneToolIconField
      icon="wall"
      :label="$t('vtt.wall.preset')"
      :description="$t('vtt.wall.tooltips.preset')"
      :model-value="preset"
      :options="presetOptions"
      :disabled="busy"
      @change="$emit('preset', $event)"
    />
    <SceneToolIconButton
      icon="pencil"
      :label="$t('vtt.wall.drawMode')"
      :description="$t('vtt.wall.tooltips.draw')"
      shortcut="Ctrl/Cmd"
      :active="interactionMode === 'draw'"
      :disabled="busy"
      toggle
      @click="$emit('mode', 'draw')"
    />
    <SceneToolIconButton
      v-if="drawing"
      icon="check"
      :label="$t('vtt.wall.finishDrawing')"
      :description="$t('vtt.wall.tooltips.finish')"
      shortcut="Enter"
      active
      :disabled="busy"
      @click="$emit('finish-drawing')"
    />
    <SceneToolIconButton
      icon="door"
      :label="$t('vtt.wall.insertDoor')"
      :description="$t('vtt.wall.tooltips.door')"
      :active="interactionMode === 'door'"
      :disabled="busy"
      toggle
      @click="$emit('mode', 'door')"
    />
    <SceneToolIconButton
      icon="window"
      :label="$t('vtt.wall.insertWindow')"
      :description="$t('vtt.wall.tooltips.window')"
      :active="interactionMode === 'window'"
      :disabled="busy"
      toggle
      @click="$emit('mode', 'window')"
    />
    <SceneToolIconButton
      icon="grid"
      :label="$t('vtt.wall.snapToGrid')"
      :description="$t('vtt.wall.tooltips.snap')"
      shortcut="Shift"
      :active="snapToGrid"
      :disabled="busy"
      toggle
      @click="$emit('snap', !snapToGrid)"
    />
    <SceneToolIconButton
      icon="chain"
      :label="$t('vtt.wall.connectPoints')"
      :description="$t('vtt.wall.tooltips.connect')"
      :active="connectPoints"
      :disabled="busy"
      toggle
      @click="$emit('connect', !connectPoints)"
    />
    <SceneToolIconField
      icon="palette"
      kind="color"
      :label="$t('vtt.wall.color')"
      :description="$t('vtt.wall.tooltips.color')"
      :model-value="displayColor"
      :disabled="busy || !wall"
      @change="$emit('update', { color: $event })"
    />
    <SceneToolIconButton
      icon="power"
      :label="allEnabled ? $t('vtt.wall.disableAll') : $t('vtt.wall.enableAll')"
      :description="$t('vtt.wall.tooltips.enabled')"
      :active="allEnabled"
      :disabled="busy || count === 0"
      toggle
      @click="$emit('toggle-all-enabled')"
    />
    <SceneToolIconButton
      :icon="allVisible ? 'eye' : 'eyeOff'"
      :label="allVisible ? $t('vtt.wall.hideAll') : $t('vtt.wall.showAll')"
      :description="$t('vtt.wall.tooltips.visibility')"
      :active="allVisible"
      :disabled="busy || count === 0"
      toggle
      @click="$emit('toggle-all-visible')"
    />
    <SceneToolIconButton
      icon="list"
      :label="$t('vtt.wall.managerShort')"
      :description="$t('vtt.wall.tooltips.manager')"
      :badge="count"
      :active="listOpen"
      toggle
      @click="$emit('toggle-list')"
    />
    <SceneToolIconButton
      icon="settings"
      :label="$t('vtt.wall.editShort')"
      :description="$t('vtt.wall.tooltips.edit')"
      :disabled="busy || !wall"
      @click="$emit('edit')"
    />
    <SceneToolIconButton
      icon="door"
      :label="$t('vtt.wall.closeAllDoors')"
      :description="$t('vtt.wall.tooltips.closeDoors')"
      :disabled="busy || count === 0"
      @click="$emit('close-doors')"
    />
    <SceneToolIconButton
      icon="region"
      :label="$t('vtt.wall.createRegion')"
      :description="$t('vtt.wall.tooltips.region')"
      :disabled="busy || selectedCount < 3"
      @click="$emit('create-region')"
    />
    <SceneToolIconButton
      icon="trashAll"
      :label="$t('vtt.wall.deleteAll')"
      :description="$t('vtt.wall.tooltips.deleteAll')"
      :disabled="busy || count === 0"
      danger
      @click="$emit('delete-all')"
    />
    <SceneToolIconButton
      icon="trash"
      :label="$t('vtt.wall.deleteShort')"
      :description="$t('vtt.wall.tooltips.delete')"
      shortcut="Delete"
      :disabled="busy || !wall"
      danger
      @click="$emit('delete')"
    />
  </nav>
</template>

<script>
import { wallColor } from "@/lib/vtt/wallGeometry";
import SceneToolIconButton from "@/components/vtt/table/SceneToolIconButton.vue";
import SceneToolIconField from "@/components/vtt/table/SceneToolIconField.vue";

export default {
  name: "WallToolToolbar",
  components: { SceneToolIconButton, SceneToolIconField },
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
    preset: { type: String, default: "solid" },
    selectedCount: { type: Number, default: 0 },
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
    "preset",
    "close-doors",
    "delete-all",
    "create-region",
  ],
  computed: {
    presets() {
      return [
        "solid",
        "terrain",
        "invisible",
        "ethereal",
        "door",
        "secret",
        "window",
      ];
    },
    presetOptions() {
      return this.presets.map((value) => ({
        value,
        label: this.$t(`vtt.wall.presets.${value}`),
      }));
    },
    displayColor() {
      return wallColor(this.wall || { type: "wall" });
    },
  },
};
</script>
