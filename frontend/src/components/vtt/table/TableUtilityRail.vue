<template>
  <nav class="table-utility-rail" :aria-label="$t('vtt.table.rail.label')">
    <button
      v-for="utility in utilities"
      :key="utility.id"
      type="button"
      class="table-utility-rail__button"
      :class="{ 'table-utility-rail__button--active': utility.id === activeId }"
      :disabled="!available(utility.id)"
      :aria-label="label(utility)"
      :aria-pressed="
        available(utility.id) ? utility.id === activeId : undefined
      "
      :title="label(utility)"
      @click="scheduleSelect(utility.id)"
      @dblclick="openWindow(utility.id)"
    >
      <TableRailIcon :name="utility.icon" />
      <span>{{ $t(utility.labelKey) }}</span>
      <b v-if="badge(utility.id)" aria-hidden="true">{{ badge(utility.id) }}</b>
    </button>
  </nav>
</template>

<script>
import TableRailIcon from "./TableRailIcon.vue";
import { IMPLEMENTED_TABLE_UTILITIES, TABLE_UTILITIES } from "./tableUtilities";

export default {
  name: "TableUtilityRail",
  components: { TableRailIcon },
  props: {
    activeId: { type: String, default: "" },
    availableIds: {
      type: Array,
      default: () => [...IMPLEMENTED_TABLE_UTILITIES],
    },
    badges: { type: Object, default: () => ({}) },
  },
  emits: ["select", "open"],
  data: () => ({ utilities: TABLE_UTILITIES, clickTimer: null }),
  beforeUnmount() {
    window.clearTimeout(this.clickTimer);
  },
  methods: {
    available(id) {
      return this.availableIds.includes(id);
    },
    badge(id) {
      const count = Math.max(0, Number(this.badges[id]) || 0);
      return count > 99 ? "99+" : count;
    },
    label(utility) {
      const value = this.$t(utility.labelKey);
      return this.available(utility.id)
        ? value
        : `${value} — ${this.$t("vtt.table.tools.unavailable")}`;
    },
    scheduleSelect(id) {
      window.clearTimeout(this.clickTimer);
      this.clickTimer = window.setTimeout(() => {
        this.$emit("select", id);
        this.clickTimer = null;
      }, 220);
    },
    openWindow(id) {
      window.clearTimeout(this.clickTimer);
      this.clickTimer = null;
      this.$emit("open", id);
    },
  },
};
</script>
