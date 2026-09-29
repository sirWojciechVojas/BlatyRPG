<template>
  <button
    type="button"
    class="inventory-cell"
    :class="[
      `inventory-cell--${side}`,
      `inventory-cell--${cell.slot.replace(/[^a-zA-Z0-9]/gu, '-')}`,
      `inventory-cell--frame-${cell.frame || 'single'}`,
      {
        'inventory-cell--occupied': cell.item,
        'inventory-cell--placement-source': placement.source,
        'inventory-cell--placement-target': placement.target,
        'inventory-cell--placement-dimmed': placement.dimmed,
        'inventory-cell--two-handed-blocked': placement.blockedByTwoHanded,
        'inventory-cell--two-handed-ghost': ghostItem,
        'inventory-cell--weapon-set-active': isActiveWeaponSlot,
      },
    ]"
    :aria-disabled="placement.disabled ? 'true' : undefined"
    :aria-label="label()"
    :aria-current="isActiveWeaponSlot ? 'true' : undefined"
    :title="cell.item ? null : label()"
    @click="scheduleActivate"
    @dragover="dragOver"
    @drop="drop"
    @mouseenter="showTooltip"
    @mouseleave="hideTooltip"
    @focus="showTooltip"
    @blur="hideTooltip"
    @contextmenu.prevent="openDetails"
    @dblclick.prevent="activateBlockedSlot"
  >
    <span
      v-if="cell.item"
      class="inventory-cell__item"
      :class="itemTierClass(cell.item)"
      :draggable="!placement.disabled"
      @dragstart.stop="dragStart"
      @dragend.stop="dragEnd"
      @dblclick.prevent.stop="activatePrimaryAction"
    >
      <ItemIcon :item="cell.item" :size="42" />
      <small
        v-if="Number(cell.item.QUANTITY) > 1"
        class="inventory-cell__quantity"
      >
        {{ cell.item.QUANTITY }}
      </small>
    </span>
    <span
      v-else-if="ghostItem"
      class="inventory-cell__item inventory-cell__item--two-handed-ghost"
      aria-hidden="true"
    >
      <ItemIcon :item="ghostItem" :size="42" />
    </span>
  </button>
</template>

<script>
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import { itemVisualTier, slotLabelKey } from "./bountifyInventory";

export default {
  name: "CharacterStatsInventoryCell",
  components: { ItemIcon },
  props: {
    cell: { type: Object, required: true },
    side: { type: String, required: true },
    placement: { type: Object, default: () => ({}) },
    activeWeaponSet: { type: Number, default: 0 },
  },
  emits: [
    "activate",
    "drag-start",
    "drag-end",
    "drop-item",
    "open-details",
    "primary-action",
    "show-tooltip",
    "hide-tooltip",
  ],
  data: () => ({
    activationTimer: null,
  }),
  computed: {
    ghostItem() {
      return !this.cell.item && this.placement.blockedItem
        ? this.placement.blockedItem
        : null;
    },
    weaponSetNumber() {
      const match = /^arm[RL]([12])$/u.exec(this.cell.slot);
      return match ? Number(match[1]) : 0;
    },
    isActiveWeaponSlot() {
      return (
        this.weaponSetNumber > 0 &&
        this.weaponSetNumber === this.activeWeaponSet
      );
    },
  },
  beforeUnmount() {
    this.clearPendingActivation();
  },
  methods: {
    clearPendingActivation() {
      if (!this.activationTimer) return;
      clearTimeout(this.activationTimer);
      this.activationTimer = null;
    },
    scheduleActivate() {
      this.clearPendingActivation();
      this.activationTimer = setTimeout(() => {
        this.activationTimer = null;
        this.$emit("activate", this.cell.slot);
      }, 500);
    },
    activatePrimaryAction() {
      this.clearPendingActivation();
      if (!this.cell.item || this.placement.disabled) return;
      this.hideTooltip();
      this.$emit("primary-action", this.cell.slot);
    },
    activateBlockedSlot() {
      if (!this.placement.disabled) return;
      this.clearPendingActivation();
      this.$emit("activate", this.cell.slot);
    },
    openDetails() {
      this.clearPendingActivation();
      if (!this.cell.item) return;
      this.hideTooltip();
      this.$emit("open-details", this.cell.item, this.cell.slot);
    },
    itemTierClass(item) {
      const tier = itemVisualTier(item);
      return tier ? `inventory-cell__item--${tier}` : "";
    },
    label() {
      const slot = this.$t(slotLabelKey(this.cell.slot));
      const displayedItem = this.cell.item || this.ghostItem;
      const itemLabel = displayedItem
        ? `${slot}: ${displayedItem.NAME}`
        : `${slot}: ${this.$t("vtt.table.characterStats.inventory.empty")}`;
      const placementLabel = this.placement.blockedByTwoHanded
        ? `${itemLabel}. ${this.$t(
            "vtt.table.characterStats.inventory.twoHandedDisabled",
          )}`
        : itemLabel;
      return this.isActiveWeaponSlot
        ? `${placementLabel}. ${this.$t(
            "vtt.table.characterStats.inventory.activeWeaponSlot",
          )}`
        : placementLabel;
    },
    showTooltip(event) {
      if (!this.cell.item || typeof window === "undefined") return;
      const rect = event.currentTarget?.getBoundingClientRect?.();
      if (!rect) return;
      this.$emit("show-tooltip", {
        slot: this.cell.slot,
        item: this.cell.item,
        rect: {
          top: rect.top,
          right: rect.right,
          left: rect.left,
        },
      });
    },
    hideTooltip() {
      this.$emit("hide-tooltip", this.cell.slot);
    },
    dragStart(event) {
      this.clearPendingActivation();
      if (!this.cell.item || this.placement.disabled) {
        event.preventDefault();
        return;
      }
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", this.cell.slot);
      this.hideTooltip();
      this.$emit("drag-start", this.cell.slot);
    },
    dragEnd() {
      this.$emit("drag-end");
    },
    dragOver(event) {
      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = "move";
      }
    },
    drop(event) {
      event.preventDefault();
      this.$emit("drop-item", this.cell.slot);
    },
  },
};
</script>
