<template>
  <nav
    class="light-tool-toolbar"
    :aria-label="$t('vtt.light.toolbar')"
    @pointerdown.stop
  >
    <SceneToolIconField
      :icon="sourceIcon"
      :label="$t('vtt.light.sourceType')"
      :description="$t('vtt.light.tooltips.sourceType')"
      :model-value="sourceType"
      :options="sourceOptions"
      :disabled="busy"
      @change="$emit('source-type', $event)"
    />
    <SceneToolIconButton
      icon="plus"
      :label="$t('vtt.light.add')"
      :description="$t('vtt.light.tooltips.add')"
      :disabled="busy"
      @click="$emit('add')"
    />
    <SceneToolIconField
      icon="sun"
      :label="$t('vtt.scene.fields.globalLightLevel')"
      :description="$t('vtt.light.tooltips.global')"
      :model-value="nearestGlobalLevel"
      :options="globalLevelOptions"
      :disabled="busy"
      @change="$emit('global-update', Number($event))"
    />
    <SceneToolIconButton
      icon="copy"
      :label="$t('vtt.light.copyShort')"
      :description="$t('vtt.light.tooltips.copy')"
      shortcut="Ctrl/Cmd+C · Ctrl/Cmd+V"
      :disabled="busy || !light"
      @click="$emit('copy')"
    />
    <SceneToolIconField
      icon="palette"
      kind="color"
      :label="$t('vtt.light.color')"
      :description="$t('vtt.light.tooltips.color')"
      :model-value="light?.color?.slice(0, 7) || '#FFD27A'"
      :disabled="busy || !light"
      @change="$emit('update', { color: $event })"
    />
    <SceneToolIconButton
      icon="settings"
      :label="$t('vtt.light.editShort')"
      :description="$t('vtt.light.tooltips.edit')"
      :disabled="busy || !light"
      @click="$emit('edit')"
    />
    <SceneToolIconButton
      icon="list"
      :label="$t('vtt.light.list')"
      :description="$t('vtt.light.tooltips.manager')"
      :badge="count"
      :active="listOpen"
      toggle
      @click="$emit('toggle-list')"
    />
    <SceneToolIconButton
      icon="trash"
      :label="$t('vtt.light.deleteShort')"
      :description="$t('vtt.light.tooltips.delete')"
      shortcut="Delete"
      :disabled="busy || !light"
      danger
      @click="$emit('delete')"
    />
    <SceneToolIconButton
      icon="trashAll"
      :label="$t('vtt.light.deleteAll')"
      :description="$t('vtt.light.tooltips.deleteAll')"
      :disabled="busy || count === 0"
      danger
      @click="$emit('delete-all')"
    />
  </nav>
</template>

<script>
import { LIGHT_TYPES } from "@/lib/vtt/lightOptions";
import SceneToolIconButton from "@/components/vtt/table/SceneToolIconButton.vue";
import SceneToolIconField from "@/components/vtt/table/SceneToolIconField.vue";

export default {
  name: "LightToolToolbar",
  components: { SceneToolIconButton, SceneToolIconField },
  props: {
    light: { type: Object, default: null },
    sourceType: { type: String, default: "omni" },
    count: { type: Number, default: 0 },
    listOpen: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
    globalLightLevel: { type: Number, default: 0 },
  },
  emits: [
    "add",
    "copy",
    "update",
    "global-update",
    "source-type",
    "edit",
    "toggle-list",
    "delete",
    "delete-all",
  ],
  data: () => ({ types: LIGHT_TYPES }),
  computed: {
    sourceOptions() {
      return this.types.map((value) => ({
        value,
        label: this.$t(`vtt.light.types.${value}`),
      }));
    },
    sourceIcon() {
      if (this.sourceType === "darkness") return "darkness";
      if (["cone", "directional"].includes(this.sourceType)) return "cone";
      if (this.sourceType === "area") return "area";
      return "light";
    },
    globalLevelOptions() {
      return [0, 0.25, 0.5, 0.75, 1].map((value) => ({
        value,
        label: `${Math.round(value * 100)}%`,
      }));
    },
    nearestGlobalLevel() {
      return Math.round(Number(this.globalLightLevel || 0) * 4) / 4;
    },
  },
};
</script>
