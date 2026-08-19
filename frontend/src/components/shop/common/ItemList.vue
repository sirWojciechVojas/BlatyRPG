<!-- Responsibility: ItemList shop interface component. -->
<template>
  <div
    ref="viewport"
    class="item-list"
    :class="`item-list--${density}`"
    role="listbox"
    :aria-label="label"
    tabindex="0"
    @scroll="onScroll"
    @keydown="onViewportKeydown"
  >
    <div
      class="item-list__spacer"
      :style="{ height: `${items.length * rowHeight}px` }"
    >
      <button
        v-for="entry in visibleEntries"
        :key="entry.key"
        type="button"
        class="item-list__row"
        :class="{ 'item-list__row--selected': isSelected(entry.item) }"
        :style="{
          transform: `translateY(${entry.index * rowHeight}px)`,
          height: `${rowHeight}px`,
        }"
        role="option"
        :aria-selected="isSelected(entry.item)"
        @click="$emit('select', entry.item)"
        @dblclick="$emit('details', entry.item)"
      >
        <slot :item="entry.item" :index="entry.index">
          <span class="item-list__name">{{ itemLabel(entry.item) }}</span>
          <span class="item-list__meta">{{ itemMeta(entry.item) }}</span>
        </slot>
        <span
          class="item-list__details"
          @click.stop="$emit('details', entry.item)"
          >•••</span
        >
      </button>
    </div>
    <p v-if="!items.length" class="item-list__empty">{{ emptyLabel }}</p>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from "vue";

const props = defineProps({
  items: { type: Array, default: () => [] },
  itemKey: { type: [String, Function], default: "id" },
  selectedKeys: { type: Array, default: () => [] },
  density: { type: String, default: "compact" },
  label: { type: String, default: "" },
  emptyLabel: { type: String, default: "" },
  nameKey: { type: String, default: "name" },
  metaKey: { type: String, default: "" },
});
defineEmits(["select", "details"]);

const viewport = ref(null);
const scrollTop = ref(0);
const viewportHeight = ref(360);
const rowHeight = computed(() => (props.density === "comfortable" ? 48 : 40));
const keyFor = (item, index = 0) =>
  typeof props.itemKey === "function"
    ? props.itemKey(item)
    : (item?.[props.itemKey] ?? item?.ID ?? index);
const selected = computed(() => new Set(props.selectedKeys.map(String)));
const isSelected = (item) => selected.value.has(String(keyFor(item)));
const visibleEntries = computed(() => {
  const overscan = 6;
  const start = Math.max(
    0,
    Math.floor(scrollTop.value / rowHeight.value) - overscan,
  );
  const count =
    Math.ceil(viewportHeight.value / rowHeight.value) + overscan * 2;
  return props.items.slice(start, start + count).map((item, offset) => ({
    item,
    index: start + offset,
    key: keyFor(item, start + offset),
  }));
});
const onScroll = (event) => {
  scrollTop.value = event.currentTarget.scrollTop;
  viewportHeight.value = event.currentTarget.clientHeight;
};
const onViewportKeydown = async (event) => {
  if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) return;
  event.preventDefault();
  const rows = Array.from(
    viewport.value?.querySelectorAll(".item-list__row") || [],
  );
  const active = document.activeElement;
  const index = rows.indexOf(active);
  const next =
    event.key === "Home"
      ? 0
      : event.key === "End"
        ? rows.length - 1
        : Math.max(
            0,
            Math.min(
              rows.length - 1,
              index + (event.key === "ArrowUp" ? -1 : 1),
            ),
          );
  await nextTick();
  rows[next]?.focus();
};
const itemLabel = (item) => item?.[props.nameKey] ?? item?.NAME ?? "";
const itemMeta = (item) => (props.metaKey ? item?.[props.metaKey] : "");
onMounted(() => {
  viewportHeight.value = viewport.value?.clientHeight || 360;
});
</script>

<style scoped>
.item-list {
  position: relative;
  min-height: 12rem;
  height: 100%;
  overflow: auto;
  background: #111317;
}
.item-list:focus-visible {
  outline: 2px solid var(--shop-focus, #d0a862);
  outline-offset: -2px;
}
.item-list__spacer {
  position: relative;
  min-width: 100%;
}
.item-list__row {
  position: absolute;
  inset: 0 0 auto;
  display: grid;
  width: 100%;
  grid-template-columns: minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 0.5rem;
  border: 0;
  border-bottom: 1px solid #292c32;
  background: transparent;
  color: #e7e9ed;
  padding: 0 0.55rem;
  text-align: left;
}
.item-list__row:hover {
  background: #1c2026;
}
.item-list__row--selected {
  background: #28251f;
  box-shadow: inset 2px 0 #c9a461;
}
.item-list__row:focus-visible {
  z-index: 1;
  outline: 2px solid var(--shop-focus, #d0a862);
  outline-offset: -2px;
}
.item-list__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.item-list__meta {
  color: #a7abb3;
  font-size: 0.75rem;
}
.item-list__details {
  min-width: 2rem;
  text-align: center;
}
.item-list__empty {
  position: absolute;
  inset: 35% 1rem auto;
  color: #90959e;
  text-align: center;
}
@media (max-width: 600px) {
  .item-list__row {
    min-height: 44px;
  }
}
</style>
