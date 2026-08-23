<template>
  <nav class="table-tool-rail" :aria-label="$t('vtt.table.tools.label')">
    <button
      v-for="tool in visibleTools"
      :key="tool.id"
      type="button"
      class="table-tool-rail__button"
      :class="{ 'table-tool-rail__button--active': tool.id === activeId }"
      :disabled="!available(tool)"
      :aria-label="label(tool)"
      :aria-pressed="available(tool) ? tool.id === activeId : undefined"
      :title="label(tool)"
      @click="$emit('select', tool.id)"
    >
      <TableRailIcon :name="tool.icon" />
      <span>{{ $t(tool.labelKey) }}</span>
    </button>
  </nav>
</template>

<script>
import TableRailIcon from "./TableRailIcon.vue";
import { implementedSceneTool, TABLE_SCENE_TOOLS } from "./tableSceneTools";

export default {
  name: "TableToolRail",
  components: { TableRailIcon },
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
  },
};
</script>
