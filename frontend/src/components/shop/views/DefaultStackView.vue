<!-- Moduł GM: Przedmioty - spersonalizuj. Ten plik jest wejściem widoku sprzedaży i personalizacji przedmiotów z filtrowaniem po właścicielu oraz listą elementów do obsługi. -->
<template>
  <BaseShopLayout
    layout-class="default-stack-layout"
    :shell-class="sellShellClass"
    content-id="tradingSell"
    :content-class="sellContentClass"
    notification-zone="sell"
    :toolbar-inside-shell="true"
    :show-toolbar="true"
  >
    <template #header>
      <input
        type="text"
        class="form-control bg-transparent text-light ih-95"
        :value="ctx.sellListTitle"
        readonly
        disabled
      />
    </template>

    <template #toolbar>
      <div v-if="showOwnerToolbar" class="trade-category-toolbar">
        <TradeZonePicker
          :hub-ref="ownerZoneHubRef"
          :search-input-ref="ownerZoneSearchInputRef"
          :options="sortedOwnerFilterOptions"
          :hot-options="hotOwnerFilterOptions"
          :filtered-options="filteredOwnerFilterOptions"
          :panel-open="isOwnerPanelOpen"
          :label="$t('shop.filters.sellOwnerLabel')"
          :aria-label="$t('shop.filters.sellOwnerAria')"
          trigger-id="owner-zone-trigger"
          panel-id="owner-zone-panel"
          search-id="owner-zone-search"
          :search-value="ownerZoneSearch"
          :search-placeholder="$t('shop.trashView.zoneFilterPlaceholder')"
          :active-label="activeOwnerFilterOption?.label || '-'"
          :trigger-count-text="`${activeOwnerFilterOption?.count || 0}/${ownerFilterTotalCount}`"
          :active-ordinal="activeOwnerFilterOrdinal"
          :empty-text="
            $t('shop.trashView.noZonesForFilter', {
              value: ownerZoneSearch,
            })
          "
          :disable-nav="sortedOwnerFilterOptions.length <= 1"
          :disable-trigger="!sortedOwnerFilterOptions.length"
          :active-meter-style="ownerMeterStyle(activeOwnerFilterOption)"
          :meter-style="ownerMeterStyle"
          :option-code="ownerCode"
          :option-title="ownerOptionTitle"
          :quick-meta="ownerQuickMeta"
          :row-meta="ownerRowMeta"
          :is-active="isOwnerOptionActive"
          :option-key="ownerOptionKey"
          @update:search-value="ownerZoneSearch = $event"
          @previous="selectPreviousOwnerFilter"
          @next="selectNextOwnerFilter"
          @toggle="toggleOwnerPanel"
          @open="openOwnerPanel"
          @close="closeOwnerPanelSafe"
          @select="selectOwnerFilter"
        />
      </div>
      <div v-else class="trade-compact-searchbar">
        <input
          type="search"
          :value="ctx.sellTypeSearch"
          :placeholder="$t('ui.search')"
          @input="ctx.setTradeSearch('sell', $event.target.value)"
        />
        <select
          :value="ctx.sellSortMode"
          @change="ctx.setTradeSort('sell', $event.target.value)"
        >
          <option value="name">{{ $t("shop.workspace.sort.name") }}</option>
          <option value="price">{{ $t("shop.workspace.sort.price") }}</option>
          <option value="availability">
            {{ $t("shop.workspace.sort.availability") }}
          </option>
        </select>
      </div>
    </template>

    <template #filters>
      <TradeTypeFilterRail
        v-if="ctx.sellTypeFilterOptions.length > 1"
        side="right"
        :options="ctx.sellTypeFilterOptions"
        :active-value="ctx.sellTypeFilter"
        :all-label="$t('shop.common.allCaps')"
        :aria-label="$t('shop.filters.sellTypeAria')"
        @select="ctx.setTradeTypeFilter('sell', $event)"
      />
    </template>

    <template #default>
      <TradeItemList
        mode="sell"
        :items="ctx.filteredSellItems"
        :row-class="ctx.sellItemClass"
        :density="Number(ctx.iconSize) > 30 ? 'comfortable' : 'compact'"
        :visible-rows="10"
        :active-ids="ctx.selectedSellIds"
        :selected-quantity-for-item="ctx.selectedSellQuantityForItem"
        :can-adjust-quantity-for-item="ctx.canAdjustSellSelectionQuantity"
        :act-id="ctx.sellActId"
        :show-temp-hidden="ctx.showTempHidden(ctx.sellItemClass)"
        :icon-class-for-item="ctx.legacyIconClassForItem"
        :image-src-for-item="ctx.itemImageSrcForItem"
        :empty-text="$t('shop.common.emptyItems')"
        :loading="ctx.loadingSell"
        :loading-text="$t('shop.tradeModal.loadingSell')"
        :error-text="ctx.errorSell"
        :retry-label="$t('shop.tradeModal.retryLoad')"
        @select="ctx.handleSellItemClick"
        @qty-step="ctx.handleSellItemQuantityStep"
        @qty-input="ctx.handleSellItemQuantityInput"
        @open-detail="ctx.openItemDetailDialog"
        @retry="ctx.retryTradeDataLoad"
      />
    </template>

    <template #summary>
      <TradeSummaryPanel
        :label="$t('shop.templateEditor.summaryPrice')"
        :brass="ctx.sellTotalBrass"
        :currency-code="ctx.activeSettlementCurrencyCode"
      />
    </template>

    <template #actions>
      <TradeCommandButton
        button-id="tSellBtn"
        variant="sell"
        :label="ctx.sellActionLabel"
        :disabled="!ctx.sellActionEnabled"
        @click="ctx.handleSellAction"
      />
    </template>
  </BaseShopLayout>
</template>

<script setup>
import { computed } from "vue";
import BaseShopLayout from "@/components/shop/layouts/BaseShopLayout.vue";
import TradeCommandButton from "@/components/shop/common/TradeCommandButton.vue";
import TradeTypeFilterRail from "@/components/shop/common/TradeTypeFilterRail.vue";
import TradeZonePicker from "@/components/shop/common/TradeZonePicker.vue";
import { useDefaultStackView } from "@/components/shop/modules/default-stack/composables/useDefaultStackView";
import TradeItemList from "@/components/trade/TradeItemList.vue";
import TradeSummaryPanel from "@/components/trade/TradeSummaryPanel.vue";

const {
  activeOwnerFilterOption,
  activeOwnerFilterOrdinal,
  activeOwnerFilterValue,
  ctx,
  filteredOwnerFilterOptions,
  hotOwnerFilterOptions,
  isOwnerPanelOpen,
  normalizeOwnerFilterValue,
  openOwnerPanel,
  ownerCode,
  ownerFilterTotalCount,
  ownerMeterStyle,
  ownerZoneHubRef,
  ownerZoneSearch,
  ownerZoneSearchInputRef,
  selectNextOwnerFilter,
  selectOwnerFilter,
  selectPreviousOwnerFilter,
  sortedOwnerFilterOptions,
  toggleOwnerPanel,
} = useDefaultStackView();

const showOwnerToolbar = computed(
  () => Boolean(ctx.isGM) && ctx.gmMode === "inventory",
);

const sellShellClass = computed(() =>
  showOwnerToolbar.value
    ? "trade-list-shell trade-list-shell--sell trade-list-shell--sell-default-stack"
    : "trade-list-shell trade-list-shell--sell",
);

const sellContentClass = computed(() =>
  showOwnerToolbar.value
    ? "tab-content outline w-100 text-light bg-transparent trade-list trade-list--virtualized trade-list--sell-default-stack"
    : "tab-content outline w-100 text-light bg-transparent trade-list trade-list--virtualized",
);

function ownerOptionKey(option) {
  return normalizeOwnerFilterValue(option?.value);
}

function isOwnerOptionActive(option) {
  return activeOwnerFilterValue === normalizeOwnerFilterValue(option?.value);
}

function ownerOptionTitle(option) {
  return `${option.label} (${option.count})`;
}

function ownerQuickMeta(option) {
  return String(option.count);
}

function ownerRowMeta(option) {
  return String(option.count);
}

function closeOwnerPanelSafe() {
  if (isOwnerPanelOpen.value) {
    toggleOwnerPanel();
  }
}
</script>

<style scoped>
.trade-compact-searchbar {
  display: grid;
  width: 100%;
  grid-template-columns: 1fr auto;
  gap: 0.3rem;
}
.trade-compact-searchbar input,
.trade-compact-searchbar select {
  min-height: 2rem;
  border: 1px solid rgba(210, 180, 125, 0.3);
  border-radius: 0.25rem;
  background: rgba(8, 8, 8, 0.72);
  color: #f4ead7;
  padding: 0.25rem 0.4rem;
}
</style>

<style
  scoped
  src="../modules/default-stack/styles/DefaultStackView.css"
></style>
