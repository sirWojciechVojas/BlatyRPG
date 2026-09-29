<template>
  <nav
    class="token-selection-toolbar"
    :aria-label="$t('vtt.table.tools.tokenSelection.label')"
    @pointerdown.stop
    @click.stop
    @dblclick.stop
  >
    <button
      v-for="mode in modes"
      :key="mode.id"
      type="button"
      :class="{
        'token-selection-toolbar__button--active': mode.id === modelValue,
      }"
      :aria-label="$t(mode.labelKey)"
      :aria-pressed="mode.id === modelValue"
      :title="$t(mode.titleKey || mode.labelKey)"
      @click.stop="$emit('update:modelValue', mode.id)"
    >
      <TableRailIcon :name="mode.icon" />
    </button>
  </nav>
</template>

<script>
import TableRailIcon from "@/components/vtt/table/TableRailIcon.vue";

const MODES = Object.freeze([
  {
    id: "point",
    icon: "cursor",
    labelKey: "vtt.table.tools.tokenSelection.point",
  },
  {
    id: "rectangle",
    icon: "selectRectangle",
    labelKey: "vtt.table.tools.tokenSelection.rectangle",
  },
  {
    id: "circle",
    icon: "selectCircle",
    labelKey: "vtt.table.tools.tokenSelection.circle",
  },
  {
    id: "polygon",
    icon: "selectPolygon",
    labelKey: "vtt.table.tools.tokenSelection.polygon",
    titleKey: "vtt.table.tools.tokenSelection.polygonHint",
  },
]);

export default {
  name: "TokenAreaSelectionToolbar",
  components: { TableRailIcon },
  props: {
    modelValue: { type: String, default: "point" },
  },
  emits: ["update:modelValue"],
  data: () => ({ modes: MODES }),
};
</script>
