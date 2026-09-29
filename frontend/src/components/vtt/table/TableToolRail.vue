<template>
  <nav class="table-tool-rail" :aria-label="$t('vtt.table.tools.label')">
    <SceneToolIconButton
      v-for="tool in visibleTools"
      :key="tool.id"
      class="table-tool-rail__button"
      :icon="tool.icon"
      :label="$t(tool.labelKey)"
      :description="description(tool)"
      :active="tool.id === activeId"
      :disabled="!available(tool)"
      toggle
      @click="$emit('select', tool.id)"
    />
  </nav>
</template>

<script>
import SceneToolIconButton from "./SceneToolIconButton.vue";
import { implementedSceneTool, TABLE_SCENE_TOOLS } from "./tableSceneTools";

export default {
  name: "TableToolRail",
  components: { SceneToolIconButton },
  props: {
    activeId: { type: String, default: "select" },
    canManage: { type: Boolean, default: false },
  },
  emits: ["select"],
  data: () => ({ tools: TABLE_SCENE_TOOLS }),
  computed: {
    visibleTools() {
      return this.tools.filter((tool) => !tool.gmOnly || this.canManage);
    },
  },
  methods: {
    available(tool) {
      return implementedSceneTool(tool.id);
    },
    label(tool) {
      const value = this.$t(tool.labelKey);
      return this.available(tool)
        ? value
        : `${value} — ${this.$t("vtt.table.tools.unavailable")}`;
    },
    description(tool) {
      if (!this.available(tool)) return this.$t("vtt.table.tools.unavailable");
      const key = `vtt.table.tools.descriptions.${tool.id}`;
      return this.$te(key) ? this.$t(key) : this.label(tool);
    },
  },
};
</script>
