<template>
  <nav class="table-hotbar" :aria-label="$t('vtt.table.hotbar.label')">
    <div
      v-for="(actionId, index) in slots"
      :key="index"
      class="table-hotbar__slot"
      :class="{ 'table-hotbar__slot--empty': !actionId }"
      @dragover.prevent
      @drop="drop(index)"
    >
      <button
        v-if="action(actionId)"
        type="button"
        draggable="true"
        :disabled="action(actionId).disabled"
        :title="`${indexLabel(index)} · ${action(actionId).label}`"
        @click="$emit('activate', actionId)"
        @contextmenu.prevent="clear(index)"
        @dragstart="dragIndex = index"
        @dragend="dragIndex = null"
      >
        <TableRailIcon :name="action(actionId).icon" />
        <span>{{ action(actionId).label }}</span>
      </button>
      <button
        v-else
        type="button"
        :title="$t('vtt.table.hotbar.configure', { slot: indexLabel(index) })"
        @click="paletteIndex = paletteIndex === index ? null : index"
      >
        <span aria-hidden="true">+</span>
      </button>
      <kbd>{{ indexLabel(index) }}</kbd>

      <div v-if="paletteIndex === index" class="table-hotbar__palette">
        <button
          v-for="item in availableActions"
          :key="item.id"
          type="button"
          @click="assign(index, item.id)"
        >
          <TableRailIcon :name="item.icon" />
          <span>{{ item.label }}</span>
        </button>
      </div>
    </div>
  </nav>
</template>

<script>
import TableRailIcon from "./TableRailIcon.vue";

const SLOT_COUNT = 10;
const emptySlots = () => Array(SLOT_COUNT).fill(null);

export default {
  name: "TableHotbar",
  components: { TableRailIcon },
  props: {
    actions: { type: Array, default: () => [] },
    defaultActionIds: { type: Array, default: () => [] },
    storageKey: { type: String, required: true },
  },
  emits: ["activate"],
  data: () => ({
    slots: emptySlots(),
    paletteIndex: null,
    dragIndex: null,
  }),
  computed: {
    availableActions() {
      const assigned = new Set(this.slots.filter(Boolean));
      return this.actions.filter((item) => !assigned.has(item.id));
    },
  },
  watch: {
    storageKey: { handler: "load", immediate: true },
  },
  mounted() {
    window.addEventListener("keydown", this.onKeydown);
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.onKeydown);
  },
  methods: {
    action(id) {
      return this.actions.find((item) => item.id === id) || null;
    },
    indexLabel(index) {
      return String((index + 1) % SLOT_COUNT);
    },
    defaults() {
      const slots = emptySlots();
      this.defaultActionIds.slice(0, SLOT_COUNT).forEach((id, index) => {
        if (this.action(id)) slots[index] = id;
      });
      return slots;
    },
    load() {
      let stored = null;
      try {
        stored = JSON.parse(window.localStorage.getItem(this.storageKey));
      } catch (_error) {
        stored = null;
      }
      this.slots = Array.isArray(stored)
        ? emptySlots().map((_value, index) =>
            this.action(stored[index]) ? stored[index] : null,
          )
        : this.defaults();
    },
    persist() {
      try {
        window.localStorage.setItem(
          this.storageKey,
          JSON.stringify(this.slots),
        );
      } catch (_error) {
        // Hotbar configuration remains usable for the current tab.
      }
    },
    assign(index, actionId) {
      this.slots.splice(index, 1, actionId);
      this.paletteIndex = null;
      this.persist();
    },
    clear(index) {
      this.slots.splice(index, 1, null);
      this.paletteIndex = null;
      this.persist();
    },
    drop(index) {
      if (this.dragIndex === null || this.dragIndex === index) return;
      const source = this.slots[this.dragIndex];
      const target = this.slots[index];
      this.slots.splice(index, 1, source);
      this.slots.splice(this.dragIndex, 1, target);
      this.dragIndex = null;
      this.persist();
    },
    onKeydown(event) {
      if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey)
        return;
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(event.target?.tagName)) return;
      if (!/^[0-9]$/.test(event.key)) return;
      const index = event.key === "0" ? 9 : Number(event.key) - 1;
      const actionId = this.slots[index];
      if (actionId && !this.action(actionId)?.disabled) {
        this.$emit("activate", actionId);
      }
    },
  },
};
</script>
