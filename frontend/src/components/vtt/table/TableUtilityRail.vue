<template>
  <nav class="table-utility-rail" :aria-label="$t('vtt.table.rail.label')">
    <button
      v-for="utility in utilities"
      :key="utility.id"
      type="button"
      class="table-utility-rail__button"
      :class="{ 'table-utility-rail__button--active': utility.id === activeId }"
      :aria-label="$t(utility.labelKey)"
      :aria-pressed="utility.id === activeId"
      :title="$t(utility.labelKey)"
      @click="$emit('select', utility.id)"
    >
      <TableRailIcon :name="utility.icon" />
      <span>{{ $t(utility.labelKey) }}</span>
    </button>
  </nav>
</template>

<script>
import TableRailIcon from "./TableRailIcon.vue";
import { TABLE_UTILITIES } from "./tableUtilities";

export default {
  name: "TableUtilityRail",
  components: { TableRailIcon },
  props: { activeId: { type: String, default: "" } },
  emits: ["select"],
  data: () => ({ utilities: TABLE_UTILITIES }),
};
</script>
