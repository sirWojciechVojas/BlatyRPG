<template>
  <section class="character-stats-panel character-stats-equipment cLeft">
    <div class="character-stats-equipment__primary">
      <div
        class="character-stats-equipment__side character-stats-equipment__side--right"
      >
        <div
          v-for="row in rightEquipmentRows"
          :key="row.map(({ slot }) => slot).join('-')"
          class="character-stats-equipment__slot-row"
        >
          <InventoryCell
            v-for="cell in row"
            :key="cell.slot"
            :cell="cell"
            side="right"
            :placement="placementState(cell.slot)"
            :active-weapon-set="normalizedActiveWeaponSet"
            @activate="activateCell"
            @drag-start="beginDrag"
            @drag-end="endDrag"
            @drop-item="dropItem"
            @open-details="openDetails"
            @primary-action="performPrimaryAction"
            @show-tooltip="showTooltip"
            @hide-tooltip="hideTooltip"
          />
        </div>
      </div>
      <div class="character-stats-equipment__center">
        <div class="character-stats-equipment__portrait">
          <img :src="avatar" :alt="avatarAlt" />
          <span
            class="character-stats-equipment__armor-frame"
            aria-hidden="true"
          />
          <output
            v-for="location in armorLocations"
            :key="location.id"
            :class="`character-stats-equipment__armor character-stats-equipment__armor--${location.id}`"
            :aria-label="$t(location.labelKey)"
          >
            {{ model.armor[location.id] }}
          </output>
        </div>
      </div>
      <div
        class="character-stats-equipment__side character-stats-equipment__side--left"
      >
        <div
          v-for="row in leftEquipmentRows"
          :key="row.map(({ slot }) => slot).join('-')"
          class="character-stats-equipment__slot-row"
        >
          <InventoryCell
            v-for="cell in row"
            :key="cell.slot"
            :cell="cell"
            side="left"
            :placement="placementState(cell.slot)"
            :active-weapon-set="normalizedActiveWeaponSet"
            @activate="activateCell"
            @drag-start="beginDrag"
            @drag-end="endDrag"
            @drop-item="dropItem"
            @open-details="openDetails"
            @primary-action="performPrimaryAction"
            @show-tooltip="showTooltip"
            @hide-tooltip="hideTooltip"
          />
        </div>
      </div>
    </div>

    <div class="character-stats-equipment__quick-groups">
      <section class="character-stats-equipment__quick-group">
        <h3>{{ $t("vtt.table.characterStats.equipment.quiver") }}</h3>
        <div class="character-stats-equipment__quick-slots">
          <InventoryCell
            v-for="cell in quiverCells"
            :key="cell.slot"
            :cell="cell"
            side="handy"
            :placement="placementState(cell.slot)"
            @activate="activateCell"
            @drag-start="beginDrag"
            @drag-end="endDrag"
            @drop-item="dropItem"
            @open-details="openDetails"
            @primary-action="performPrimaryAction"
            @show-tooltip="showTooltip"
            @hide-tooltip="hideTooltip"
          />
        </div>
      </section>
      <section class="character-stats-equipment__quick-group">
        <h3>{{ $t("vtt.table.characterStats.equipment.handy") }}</h3>
        <div class="character-stats-equipment__quick-slots">
          <InventoryCell
            v-for="cell in handyCells"
            :key="cell.slot"
            :cell="cell"
            side="handy"
            :placement="placementState(cell.slot)"
            @activate="activateCell"
            @drag-start="beginDrag"
            @drag-end="endDrag"
            @drop-item="dropItem"
            @open-details="openDetails"
            @primary-action="performPrimaryAction"
            @show-tooltip="showTooltip"
            @hide-tooltip="hideTooltip"
          />
        </div>
      </section>
    </div>

    <div class="character-stats-equipment__status-area">
      <p
        class="character-stats-equipment__status"
        :class="{
          'character-stats-equipment__status--error': isErrorStatus,
          'character-stats-equipment__status--success':
            movementSuccess && !inventoryError,
          'character-stats-equipment__status--empty': !statusMessage,
        }"
        :role="isErrorStatus ? 'alert' : 'status'"
        :aria-live="isErrorStatus ? 'assertive' : 'polite'"
      >
        {{ statusMessage || "\u00a0" }}
      </p>
      <div
        class="character-stats-weapon-set-switcher"
        role="group"
        :aria-label="$t('vtt.table.characterStats.inventory.weaponSets.label')"
      >
        <span aria-hidden="true">{{
          $t("vtt.table.characterStats.inventory.weaponSets.short")
        }}</span>
        <button
          v-for="set in [1, 2]"
          :key="set"
          type="button"
          :class="{
            'character-stats-weapon-set-switcher__button--active':
              normalizedActiveWeaponSet === set,
          }"
          :aria-pressed="normalizedActiveWeaponSet === set"
          :disabled="!canEditInventory || weaponSetSaving"
          @click="requestWeaponSet(set)"
        >
          {{ set }}
        </button>
      </div>
    </div>

    <div class="character-stats-equipment__lower">
      <div class="character-stats-cargo-band">
        <div class="character-stats-cargo-band__backpack-stack">
          <figure class="character-stats-cargo-band__backpack">
            <img :src="backpackIllustration" alt="" aria-hidden="true" />
            <figcaption>
              {{ $t("vtt.table.characterStats.inventory.backpack") }}
            </figcaption>
          </figure>
          <section
            class="character-stats-backpack-load"
            :class="
              'character-stats-backpack-load--' + backpackEncumbranceStatus
            "
            :aria-label="backpackEncumbranceAriaLabel"
          >
            <header>
              <span>{{
                $t("vtt.table.characterStats.inventory.encumbrance.label")
              }}</span>
              <output>
                {{ backpackEncumbranceCurrent }}/{{ backpackEncumbranceLimit }}
                {{ backpackEncumbranceUnit }}
              </output>
            </header>
            <span
              v-for="meter in backpackEncumbranceMeters"
              :key="meter.id"
              class="character-stats-backpack-load__row"
            >
              <span class="character-stats-backpack-load__label">
                <span>{{ meter.label }}</span>
                <output
                  >{{ meter.current }} {{ backpackEncumbranceUnit }}</output
                >
              </span>
              <span
                class="character-stats-backpack-load__meter"
                role="progressbar"
                :aria-label="meter.ariaLabel"
                aria-valuemin="0"
                :aria-valuemax="backpackEncumbranceLimit"
                :aria-valuenow="meter.ariaValue"
                :aria-valuetext="meter.ariaLabel"
              >
                <span
                  class="character-stats-backpack-load__fill"
                  :style="{ width: meter.percent + '%' }"
                />
              </span>
            </span>
          </section>
        </div>
        <section class="character-stats-pouch">
          <figure class="character-stats-pouch__illustration">
            <img :src="pouchIllustration" alt="" aria-hidden="true" />
          </figure>
          <div class="character-stats-pouch__content">
            <strong>{{
              $t("vtt.table.characterStats.equipment.pouch")
            }}</strong>
            <div class="character-stats-wallet">
              <span
                class="character-stats-wallet__coin character-stats-wallet__coin--gold"
                aria-hidden="true"
              />
              <output>{{ model.wallet.crown }} {{ crownSuffix }}</output>
              <span
                class="character-stats-wallet__coin character-stats-wallet__coin--silver"
                aria-hidden="true"
              />
              <output>{{ model.wallet.shilling }} {{ shillingSuffix }}</output>
              <template v-if="model.wallet.penny !== null">
                <span
                  class="character-stats-wallet__coin character-stats-wallet__coin--brass"
                  aria-hidden="true"
                />
                <output>{{ model.wallet.penny }} p</output>
              </template>
            </div>
          </div>
        </section>
      </div>

      <div class="character-stats-inventory">
        <section
          class="character-stats-inventory__panel character-stats-inventory__panel--personal"
        >
          <h3>
            {{
              $t("vtt.table.characterStats.inventory.backpackContents", {
                count: personalItemCount,
              })
            }}
          </h3>
          <div
            class="character-stats-inventory__surface character-stats-inventory__personal"
            :aria-label="$t('vtt.table.characterStats.inventory.backpack')"
          >
            <InventoryCell
              v-for="cell in layout.personal"
              :key="cell.slot"
              :cell="cell"
              side="personal"
              :placement="placementState(cell.slot)"
              @activate="activateCell"
              @drag-start="beginDrag"
              @drag-end="endDrag"
              @drop-item="dropItem"
              @open-details="openDetails"
              @primary-action="performPrimaryAction"
              @show-tooltip="showTooltip"
              @hide-tooltip="hideTooltip"
            />
          </div>
        </section>
        <section
          class="character-stats-inventory__panel character-stats-inventory__panel--ground"
        >
          <h3>{{ $t("vtt.table.characterStats.inventory.groundGrid") }}</h3>
          <div
            class="character-stats-inventory__surface character-stats-inventory__ground"
            :aria-label="$t('vtt.table.characterStats.inventory.ground')"
          >
            <InventoryCell
              v-for="cell in layout.ground"
              :key="cell.slot"
              :cell="cell"
              side="ground"
              :placement="placementState(cell.slot)"
              @activate="activateCell"
              @drag-start="beginDrag"
              @drag-end="endDrag"
              @drop-item="dropItem"
              @open-details="openDetails"
              @primary-action="performPrimaryAction"
              @show-tooltip="showTooltip"
              @hide-tooltip="hideTooltip"
            />
          </div>
        </section>
      </div>
    </div>
    <Teleport to="body">
      <aside
        v-if="tooltipItem"
        :id="tooltipId"
        class="character-stats-item-tooltip"
        :style="tooltipStyle"
        role="tooltip"
      >
        <div class="character-stats-item-tooltip__header">
          <ItemIcon
            :item="tooltipItem"
            :size="64"
            class="character-stats-item-tooltip__icon"
          />
          <strong class="character-stats-item-tooltip__title">{{
            tooltipItem.NAME
          }}</strong>
        </div>
        <span
          v-if="tooltipDescription"
          class="character-stats-item-tooltip__description"
          >{{ tooltipDescription }}</span
        >
        <small>{{
          $t("vtt.table.characterStats.inventory.tooltipHint")
        }}</small>
      </aside>
    </Teleport>
    <CharacterStatsItemDialog
      :item="detailItem"
      :action-label="detailActionLabel"
      :action-disabled="!canEditInventory || moving || !detailAction.ok"
      :action-hint="detailActionHint"
      :action-busy="moving"
      :consume-action-label="detailConsumeActionLabel"
      :consume-action-disabled="
        !canEditInventory || moving || !detailConsumeAction.ok
      "
      :consume-action-hint="detailConsumeActionHint"
      @close="closeDetails"
      @primary-action="performDetailPrimaryAction"
      @consume-action="performDetailConsumeAction"
    />
  </section>
</template>

<script>
import CharacterStatsInventoryCell from "./CharacterStatsInventoryCell.vue";
import CharacterStatsItemDialog from "./CharacterStatsItemDialog.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";
import {
  createInventoryLayout,
  createInventoryPlacementPreview,
  createItemPrimaryAction,
  createMovePlan,
  EQUIPMENT_SLOT_ROWS,
  inventoryEncumbranceGroups,
  slotKind,
  slotLabelKey,
} from "./bountifyInventory";
import {
  BG_CARRY_LIMIT,
  BG_CARRY_UNIT_SHORT,
  calculateInventoryEncumbrance,
  resolveEncumbranceStatus,
} from "@/lib/trade/encumbrance";
import backpackIllustration from "@/assets/app-ui/img/character-stats/inventory/backpack-illustration.png";
import pouchIllustration from "@/assets/app-ui/img/character-stats/inventory/pouch-illustration.png";

export default {
  name: "CharacterStatsEquipmentPanel",
  components: {
    CharacterStatsItemDialog,
    InventoryCell: CharacterStatsInventoryCell,
    ItemIcon,
  },
  props: {
    model: { type: Object, required: true },
    avatar: { type: String, required: true },
    avatarAlt: { type: String, required: true },
    inventoryItems: { type: Array, default: () => [] },
    templateItems: { type: Array, default: () => [] },
    canEditInventory: { type: Boolean, default: false },
    moving: { type: Boolean, default: false },
    inventoryError: { type: String, default: "" },
    activeWeaponSet: { type: Number, default: 1 },
    weaponSetSaving: { type: Boolean, default: false },
  },
  emits: ["move-items", "consume-item", "activate-weapon-set"],
  data: () => ({
    draggedSlot: "",
    selectedSlot: "",
    movementMessage: "",
    movementError: false,
    movementSuccess: false,
    movementMessageTimer: null,
    detailItem: null,
    detailSlot: "",
    tooltipSlot: "",
    tooltipItem: null,
    tooltipStyle: {},
    backpackIllustration,
    pouchIllustration,
    armorLocations: [
      { id: "head", labelKey: "vtt.table.characterStats.armor.head" },
      { id: "body", labelKey: "vtt.table.characterStats.armor.body" },
      { id: "rightArm", labelKey: "vtt.table.characterStats.armor.rightArm" },
      { id: "leftArm", labelKey: "vtt.table.characterStats.armor.leftArm" },
      { id: "rightLeg", labelKey: "vtt.table.characterStats.armor.rightLeg" },
      { id: "leftLeg", labelKey: "vtt.table.characterStats.armor.leftLeg" },
    ],
  }),
  computed: {
    layout() {
      return createInventoryLayout(this.inventoryItems);
    },
    rightEquipmentRows() {
      return this.equipmentRows("right");
    },
    leftEquipmentRows() {
      return this.equipmentRows("left");
    },
    statusMessage() {
      return (
        this.inventoryError ||
        this.movementMessage ||
        (this.moving
          ? this.$t("vtt.table.characterStats.inventory.moving")
          : "")
      );
    },
    isErrorStatus() {
      return Boolean(this.inventoryError || this.movementError);
    },
    crownSuffix() {
      return this.model.wallet.system === "bretonnia" ? "zf" : "zk";
    },
    shillingSuffix() {
      return this.model.wallet.system === "bretonnia" ? "sg" : "s";
    },
    quiverCells() {
      return this.layout.handy.filter((cell) => cell.slot.startsWith("quiver"));
    },
    handyCells() {
      return this.layout.handy.filter((cell) => cell.slot.startsWith("handy"));
    },
    personalItemCount() {
      return this.layout.personal.filter((cell) => cell.item).length;
    },
    templateItemsMap() {
      return this.templateItems.reduce((templates, item) => {
        templates[Number(item?.ID)] = item;
        return templates;
      }, {});
    },
    backpackEncumbranceCurrent() {
      return (
        this.equippedEncumbranceCurrent +
        this.backpackContentsEncumbranceCurrent
      );
    },
    encumbranceGroups() {
      return inventoryEncumbranceGroups(this.layout);
    },
    placementPreview() {
      const sourceSlot =
        this.canEditInventory && !this.moving
          ? this.draggedSlot || this.selectedSlot
          : "";
      return createInventoryPlacementPreview(this.layout, sourceSlot);
    },
    normalizedActiveWeaponSet() {
      return Number(this.activeWeaponSet) === 2 ? 2 : 1;
    },
    detailAction() {
      return this.detailItem
        ? createItemPrimaryAction(this.layout, this.detailSlot)
        : { ok: false, code: "missing_item", action: "unavailable" };
    },
    detailActionLabel() {
      const action = this.moving ? "working" : this.detailAction.action;
      return this.$t(
        `vtt.table.characterStats.inventory.itemDialog.actions.${action}`,
      );
    },
    detailActionHint() {
      if (!this.detailItem || this.detailAction.ok) return "";
      return this.$t(
        `vtt.table.characterStats.inventory.errors.${this.detailAction.code}`,
      );
    },
    detailConsumeAction() {
      if (!this.detailItem?.CONSUMPTION) {
        return { ok: false, code: "consumption_unavailable" };
      }
      return {
        ok: !this.detailItem.CONSUMPTION.disabled,
        code:
          this.detailItem.CONSUMPTION.disabledReason ||
          "consumption_unavailable",
      };
    },
    detailConsumeActionLabel() {
      if (!this.detailItem?.CONSUMPTION) return "";
      return this.moving
        ? this.$t(
            "vtt.table.characterStats.inventory.itemDialog.actions.working",
          )
        : this.detailItem.CONSUMPTION.actionLabel;
    },
    detailConsumeActionHint() {
      return this.detailConsumeAction.ok ? "" : this.detailConsumeAction.code;
    },
    tooltipId() {
      return this.tooltipSlot
        ? `character-stats-item-tooltip-${this.tooltipSlot.replace(
            /[^a-zA-Z0-9]/gu,
            "-",
          )}`
        : undefined;
    },
    tooltipDescription() {
      return String(
        this.tooltipItem?.PERSONAL_DESC ||
          this.tooltipItem?.DESCRIPTION ||
          this.tooltipItem?.DETAILS ||
          "",
      ).trim();
    },
    equippedEncumbranceCurrent() {
      return calculateInventoryEncumbrance(
        this.encumbranceGroups.equipment,
        this.templateItemsMap,
      );
    },
    backpackContentsEncumbranceCurrent() {
      return calculateInventoryEncumbrance(
        this.encumbranceGroups.backpack,
        this.templateItemsMap,
      );
    },
    backpackEncumbranceLimit() {
      return BG_CARRY_LIMIT;
    },
    backpackEncumbranceUnit() {
      return BG_CARRY_UNIT_SHORT;
    },
    backpackEncumbranceStatus() {
      return resolveEncumbranceStatus(
        this.backpackEncumbranceCurrent,
        this.backpackEncumbranceLimit,
      );
    },
    backpackEncumbranceStatusLabel() {
      return this.$t(
        `vtt.table.characterStats.inventory.encumbrance.status.${this.backpackEncumbranceStatus}`,
      );
    },
    backpackEncumbranceAriaLabel() {
      return this.$t(
        "vtt.table.characterStats.inventory.encumbrance.ariaLabel",
        {
          current: this.backpackEncumbranceCurrent,
          limit: this.backpackEncumbranceLimit,
          unit: this.backpackEncumbranceUnit,
          status: this.backpackEncumbranceStatusLabel,
        },
      );
    },
    backpackEncumbranceMeters() {
      return [
        {
          id: "equipment",
          label: this.$t(
            "vtt.table.characterStats.inventory.encumbrance.equipment",
          ),
          current: this.equippedEncumbranceCurrent,
        },
        {
          id: "backpack",
          label: this.$t(
            "vtt.table.characterStats.inventory.encumbrance.backpackContents",
          ),
          current: this.backpackContentsEncumbranceCurrent,
        },
      ].map((meter) => ({
        ...meter,
        percent: this.encumbrancePercent(meter.current),
        ariaValue: Math.min(
          this.backpackEncumbranceLimit,
          Math.max(0, meter.current),
        ),
        ariaLabel: this.$t(
          "vtt.table.characterStats.inventory.encumbrance.meterAriaLabel",
          {
            label: meter.label,
            current: meter.current,
            limit: this.backpackEncumbranceLimit,
            unit: this.backpackEncumbranceUnit,
          },
        ),
      }));
    },
  },
  watch: {
    inventoryItems() {
      this.clearTooltip();
    },
    moving(value) {
      if (value) this.clearTooltip();
    },
  },
  beforeUnmount() {
    if (this.movementMessageTimer) clearTimeout(this.movementMessageTimer);
  },
  methods: {
    encumbrancePercent(load) {
      return Math.min(
        100,
        Math.max(0, Math.round((load * 100) / this.backpackEncumbranceLimit)),
      );
    },
    equipmentRows(side) {
      const cells = new Map(this.layout[side].map((cell) => [cell.slot, cell]));
      return EQUIPMENT_SLOT_ROWS[side].map((row) =>
        row.map((slot) => cells.get(slot)).filter(Boolean),
      );
    },
    placementState(slot) {
      return this.placementPreview.slots[slot] || {};
    },
    allCells() {
      return [
        ...this.layout.right,
        ...this.layout.left,
        ...this.layout.handy,
        ...this.layout.personal,
        ...this.layout.ground,
      ];
    },
    beginDrag(slot) {
      if (!this.canEditInventory || this.moving) return;
      this.clearTooltip();
      this.draggedSlot = slot;
      this.selectedSlot = slot;
    },
    endDrag() {
      this.draggedSlot = "";
      this.selectedSlot = "";
    },
    activateCell(slot) {
      if (!this.canEditInventory || this.moving) {
        const item = this.allCells().find((cell) => cell.slot === slot)?.item;
        if (item) this.openDetails(item, slot);
        return;
      }
      const sourceSlot = this.draggedSlot || this.selectedSlot;
      const sourceItem = sourceSlot
        ? this.allCells().find((cell) => cell.slot === sourceSlot)?.item
        : null;
      if (!sourceItem) {
        const item = this.allCells().find((cell) => cell.slot === slot)?.item;
        this.selectedSlot = item ? slot : "";
        this.clearMovementMessage();
        this.movementMessage = item
          ? this.$t("vtt.table.characterStats.inventory.selected", {
              name: item.NAME,
            })
          : "";
        return;
      }
      this.move(sourceSlot, slot);
    },
    dropItem(slot) {
      if (!this.draggedSlot || !this.canEditInventory || this.moving) return;
      this.move(this.draggedSlot, slot);
    },
    move(sourceSlot, targetSlot) {
      this.clearTooltip();
      if (sourceSlot === targetSlot) {
        this.draggedSlot = "";
        this.selectedSlot = "";
        return;
      }
      const plan = createMovePlan(this.layout, sourceSlot, targetSlot);
      if (!plan.ok) {
        const blockedItem = this.placementState(targetSlot).blockedItem;
        this.showMovementError(
          blockedItem ? "inactive_hand_slot" : plan.code,
          blockedItem
            ? {
                name:
                  this.allCells().find((cell) => cell.slot === sourceSlot)?.item
                    ?.NAME || "—",
                blocking: blockedItem.NAME || blockedItem.PERSONAL_PSEU || "—",
              }
            : {},
        );
      } else {
        this.$emit("move-items", plan.moves, {
          activeWeaponSet: this.weaponSetFromSlot(targetSlot) || null,
        });
        this.showMovementSuccess(plan, { sourceSlot, targetSlot });
      }
      this.draggedSlot = "";
      this.selectedSlot = "";
    },
    performPrimaryAction(sourceSlot) {
      if (!this.canEditInventory || this.moving) return;
      this.clearTooltip();
      const plan = createItemPrimaryAction(this.layout, sourceSlot);
      if (!plan.ok) {
        const sourceItem = this.allCells().find(
          (cell) => cell.slot === sourceSlot,
        )?.item;
        this.showMovementError(
          plan.code,
          plan.blockedItem
            ? {
                name: sourceItem?.NAME || sourceItem?.PERSONAL_PSEU || "—",
                blocking:
                  plan.blockedItem.NAME ||
                  plan.blockedItem.PERSONAL_PSEU ||
                  "—",
              }
            : {},
        );
        return;
      }
      this.$emit("move-items", plan.moves, {
        activeWeaponSet: this.weaponSetFromSlot(plan.targetSlot) || null,
      });
      this.showMovementSuccess(plan, {
        sourceSlot,
        targetSlot: plan.targetSlot,
        action: plan.action,
      });
      this.draggedSlot = "";
      this.selectedSlot = "";
      if (this.detailSlot === sourceSlot) this.closeDetails();
    },
    performDetailPrimaryAction() {
      if (!this.detailSlot) return;
      this.performPrimaryAction(this.detailSlot);
    },
    performDetailConsumeAction() {
      if (!this.detailItem?.CONSUMPTION || !this.detailConsumeAction.ok) return;
      this.$emit("consume-item", this.detailItem);
    },
    weaponSetFromSlot(slot) {
      const match = /^arm[RL]([12])$/u.exec(String(slot || ""));
      return match ? Number(match[1]) : 0;
    },
    requestWeaponSet(set, { announce = true } = {}) {
      const normalized = Number(set) === 2 ? 2 : 1;
      if (
        !this.canEditInventory ||
        this.weaponSetSaving ||
        normalized === this.normalizedActiveWeaponSet
      ) {
        return;
      }
      this.$emit("activate-weapon-set", normalized);
      if (announce) this.showWeaponSetSuccess(normalized);
    },
    showWeaponSetSuccess(set) {
      this.clearMovementMessage();
      this.movementSuccess = true;
      this.movementMessage = this.$t(
        "vtt.table.characterStats.inventory.success.weaponSet",
        { set },
      );
      this.movementMessageTimer = setTimeout(() => {
        this.movementMessage = "";
        this.movementSuccess = false;
        this.movementMessageTimer = null;
      }, 3600);
    },
    clearMovementMessage() {
      if (this.movementMessageTimer) {
        clearTimeout(this.movementMessageTimer);
        this.movementMessageTimer = null;
      }
      this.movementError = false;
      this.movementSuccess = false;
      this.movementMessage = "";
    },
    inferredSuccessAction(sourceSlot, targetSlot) {
      if (targetSlot.startsWith("quiver")) return "quiver";
      if (targetSlot.startsWith("handy")) return "ready";
      const sourceKind = slotKind(sourceSlot);
      const targetKind = slotKind(targetSlot);
      if (targetKind === "equipment") return "equip";
      if (targetKind === "personal" && sourceKind === "ground") {
        return "pickup";
      }
      if (
        targetKind === "personal" &&
        ["equipment", "handy"].includes(sourceKind)
      ) {
        return "store";
      }
      if (targetKind === "ground") return "drop";
      return "move";
    },
    showMovementSuccess(
      plan,
      { sourceSlot = "", targetSlot = "", action = "" } = {},
    ) {
      const movedItem = plan.moves?.[0]?.item;
      if (!movedItem) return;
      const replacedItem = plan.moves?.[1]?.item;
      const resolvedAction =
        action || this.inferredSuccessAction(sourceSlot, targetSlot);
      const messageKey = replacedItem
        ? resolvedAction === "equip"
          ? "equipExchange"
          : "exchange"
        : resolvedAction;

      this.clearMovementMessage();
      this.movementSuccess = true;
      this.movementMessage = this.$t(
        `vtt.table.characterStats.inventory.success.${messageKey}`,
        {
          name: movedItem.NAME || movedItem.PERSONAL_PSEU || "—",
          replaced: replacedItem?.NAME || replacedItem?.PERSONAL_PSEU || "—",
          target: targetSlot ? this.$t(slotLabelKey(targetSlot)) : "",
        },
      );
      this.movementMessageTimer = setTimeout(() => {
        this.movementMessage = "";
        this.movementSuccess = false;
        this.movementMessageTimer = null;
      }, 4600);
    },
    showMovementError(code, params = {}) {
      this.clearMovementMessage();
      this.movementError = true;
      this.movementMessage = this.$t(
        `vtt.table.characterStats.inventory.errors.${code}`,
        params,
      );
      this.movementMessageTimer = setTimeout(() => {
        this.movementMessage = "";
        this.movementError = false;
        this.movementMessageTimer = null;
      }, 4200);
    },
    openDetails(item, slot) {
      this.clearTooltip();
      this.detailItem = item;
      this.detailSlot = slot;
    },
    closeDetails() {
      this.detailItem = null;
      this.detailSlot = "";
    },
    showTooltip({ slot, item, rect } = {}) {
      if (!slot || !item || !rect || typeof window === "undefined") return;
      const width = Math.min(480, window.innerWidth - 16);
      const opensLeft = rect.right + width + 12 > window.innerWidth - 8;
      const top = Math.max(8, Math.min(rect.top, window.innerHeight - 184));
      this.tooltipSlot = slot;
      this.tooltipItem = item;
      this.tooltipStyle = {
        left: `${opensLeft ? rect.left - 10 : rect.right + 10}px`,
        top: `${top}px`,
        transform: opensLeft ? "translateX(-100%)" : "none",
        width: `${width}px`,
      };
    },
    hideTooltip(slot) {
      if (!slot || slot === this.tooltipSlot) this.clearTooltip();
    },
    clearTooltip() {
      this.tooltipSlot = "";
      this.tooltipItem = null;
      this.tooltipStyle = {};
    },
  },
};
</script>
