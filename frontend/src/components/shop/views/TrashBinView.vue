<!-- Moduł GM: Otchłań odrzutów (Kosz). Ten plik jest wejściem modułu odpowiedzialnego za przeglądanie stref kosza, filtrowanie i odzyskiwanie odrzuconych przedmiotów. -->
<template>
  <BaseShopLayout
    shell-class="trade-list-shell trade-list-shell--sell"
    content-id="tradingSell"
    content-class="tab-content outline w-100 text-light bg-transparent trade-list"
    notification-zone="buy"
    :toolbar-inside-shell="true"
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
      <div class="trade-category-toolbar">
        <TradeZonePicker
          :hub-ref="trashZoneHubRef"
          :search-input-ref="trashZoneSearchInputRef"
          :options="filteredTrashZoneOptions"
          :hot-options="hotTrashZoneOptions"
          :filtered-options="filteredTrashZoneOptions"
          :panel-open="isZonePanelOpen"
          :label="$t('shop.trashView.trashZone')"
          :aria-label="$t('shop.trashView.trashZone')"
          trigger-id="trash-zone-trigger"
          panel-id="trash-zone-panel"
          search-id="trash-zone-search"
          :search-value="trashZoneSearch"
          :search-placeholder="$t('shop.trashView.zoneFilterPlaceholder')"
          :active-label="activeTrashZoneOption?.label || '-'"
          :trigger-count-text="`${activeTrashZoneOption?.count || 0}/${activeTrashZoneCapacityLabel}`"
          :active-ordinal="activeTrashZoneOrdinal"
          :empty-text="
            $t('shop.trashView.noZonesForFilter', {
              value: trashZoneSearch,
            })
          "
          :disable-nav="filteredTrashZoneOptions.length <= 1"
          :disable-trigger="!filteredTrashZoneOptions.length"
          :active-meter-style="zoneMeterStyle(activeTrashZoneOption)"
          :meter-style="zoneMeterStyle"
          :option-code="zoneCode"
          :option-title="trashOptionTitle"
          :quick-meta="trashQuickMeta"
          :row-meta="trashRowMeta"
          :is-active="isTrashOptionActive"
          :option-key="trashOptionKey"
          :row-extra-class="trashRowExtraClass"
          @update:search-value="trashZoneSearch = $event"
          @previous="selectPreviousTrashZone"
          @next="selectNextTrashZone"
          @toggle="toggleZonePanel"
          @open="openZonePanel"
          @close="closeZonePanelSafe"
          @select="selectTrashZone"
        />
        <input
          id="trash-category-search"
          v-model.trim="ctx.sellTypeSearch"
          type="search"
          class="form-control-sm trade-input trade-category-search__input"
          :placeholder="$t('shop.filters.typeSearchPlaceholder')"
          :aria-label="$t('shop.filters.sellTypeSearchAria')"
        />
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
        :active-ids="ctx.selectedSellIds"
        :selected-quantity-for-item="ctx.selectedSellQuantityForItem"
        :can-adjust-quantity-for-item="ctx.canAdjustSellSelectionQuantity"
        :act-id="ctx.sellActId"
        :show-price="false"
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

    <template #actions>
      <TradeCommandButton
        button-id="tRestoreBtn"
        variant="restore"
        :label="ctx.buyActionLabel"
        :disabled="!ctx.buyActionEnabled"
        @click="ctx.handleBuyAction"
      />
    </template>
  </BaseShopLayout>
</template>

<script setup>
import BaseShopLayout from "@/components/shop/layouts/BaseShopLayout.vue";
import TradeCommandButton from "@/components/shop/common/TradeCommandButton.vue";
import TradeTypeFilterRail from "@/components/shop/common/TradeTypeFilterRail.vue";
import TradeZonePicker from "@/components/shop/common/TradeZonePicker.vue";
import { useTrashBinView } from "@/components/shop/modules/trash-bin/composables/useTrashBinView";
import TradeItemList from "@/components/trade/TradeItemList.vue";

const {
  activeTrashZoneCapacityLabel,
  activeTrashZoneOption,
  activeTrashZoneOrdinal,
  activeTrashZoneValue,
  capacityLabelForZone,
  ctx,
  filteredTrashZoneOptions,
  hotTrashZoneOptions,
  isZonePanelOpen,
  openZonePanel,
  selectNextTrashZone,
  selectPreviousTrashZone,
  selectTrashZone,
  toggleZonePanel,
  trashZoneHubRef,
  trashZoneSearch,
  trashZoneSearchInputRef,
  zoneCode,
  zoneMeterStyle,
} = useTrashBinView();

function trashOptionKey(option) {
  return option?.value ?? option?.label ?? "trash-zone";
}

function isTrashOptionActive(option) {
  return activeTrashZoneValue.value === option?.value;
}

function trashOptionTitle(option) {
  return `${option.label} (${option.count}/${capacityLabelForZone(option)})`;
}

function trashQuickMeta(option) {
  return String(option.count);
}

function trashRowMeta(option) {
  return `${option.count}/${capacityLabelForZone(option)}`;
}

function trashRowExtraClass(option) {
  return {
    full: Boolean(option?.full),
  };
}

function closeZonePanelSafe() {
  if (isZonePanelOpen.value) {
    toggleZonePanel();
  }
}
</script>
