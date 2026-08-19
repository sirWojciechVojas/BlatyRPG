<!-- Moduł: Sklep. Ten plik jest wejściem głównego widoku sklepu odpowiedzialnego za listę oferty, wybór typu, podsumowanie i akcje handlowe. -->
<template>
  <BaseShopLayout
    layout-class="shop-view-layout"
    shell-class="trade-list-shell trade-list-shell--buy"
    content-id="tradingBuy"
    content-class="outline w-100 text-light bg-transparent trade-list trade-list--virtualized p-1"
    notification-zone="buy"
    :toolbar-inside-shell="true"
  >
    <template #header>
      <div class="input-group input-group-md">
        <input
          type="text"
          class="form-control bg-transparent text-light ih-95"
          :value="ctx.buyListTitle"
          disabled
        />
      </div>
    </template>

    <template #filters>
      <TradeTypeFilterRail
        v-if="!ctx.showBuyForm && ctx.buyTypeFilterOptions.length > 1"
        side="left"
        :options="ctx.buyTypeFilterOptions"
        :active-value="ctx.buyTypeFilter"
        :all-label="$t('shop.common.allCaps')"
        :aria-label="$t('shop.filters.buyTypeAria')"
        @select="ctx.setTradeTypeFilter('buy', $event)"
      />
    </template>

    <template #toolbar>
      <div class="trade-compact-searchbar">
        <input
          type="search"
          :value="ctx.buyTypeSearch"
          :placeholder="$t('ui.search')"
          @input="ctx.setTradeSearch('buy', $event.target.value)"
        />
        <select
          :value="ctx.buySortMode"
          :aria-label="$t('shop.workspace.sort.name')"
          @change="ctx.setTradeSort('buy', $event.target.value)"
        >
          <option value="name">{{ $t("shop.workspace.sort.name") }}</option>
          <option value="price">{{ $t("shop.workspace.sort.price") }}</option>
          <option value="availability">
            {{ $t("shop.workspace.sort.availability") }}
          </option>
        </select>
      </div>
    </template>

    <template #default>
      <div v-if="ctx.showBuyForm" class="trade-form">
        <form
          class="row col-md-12 align-items-stretch trade-form-shell"
          name="invbg"
          @submit.prevent
        >
          <ShopInventoryPrimarySection />
          <ShopInventoryClassificationSection />
        </form>
      </div>

      <TradeItemList
        v-else
        mode="buy"
        :items="ctx.filteredBuyItems"
        :row-class="ctx.buyItemClass"
        :density="Number(ctx.iconSize) > 30 ? 'comfortable' : 'compact'"
        :visible-rows="10"
        :active-ids="ctx.selectedBuyIds"
        :selected-quantity-for-item="ctx.selectedBuyQuantityForItem"
        :can-adjust-quantity-for-item="ctx.canAdjustBuySelectionQuantity"
        :act-id="ctx.buyActId"
        :show-temp-hidden="ctx.showTempHidden(ctx.buyItemClass)"
        :icon-class-for-item="ctx.legacyIconClassForItem"
        :image-src-for-item="ctx.itemImageSrcForItem"
        :empty-text="$t('shop.shopView.emptyGoods')"
        :loading="ctx.loadingBuy"
        :loading-text="$t('shop.tradeModal.loadingBuy')"
        :error-text="ctx.errorBuy"
        :retry-label="$t('shop.tradeModal.retryLoad')"
        @select="ctx.handleBuyItemClick"
        @qty-step="ctx.handleBuyItemQuantityStep"
        @qty-input="ctx.handleBuyItemQuantityInput"
        @open-detail="ctx.openItemDetailDialog"
        @retry="ctx.retryTradeDataLoad"
      />
    </template>

    <template #summary>
      <TradeSummaryPanel
        :label="$t('shop.shopView.summaryCost')"
        :brass="ctx.buyTotalBrass"
        :currency-code="ctx.activeSettlementCurrencyCode"
      />
    </template>

    <template #actions>
      <TradeCommandButton
        button-id="tBuyBtn"
        variant="buy"
        :label="ctx.buyActionLabel"
        :disabled="!ctx.buyActionEnabled"
        @click="ctx.handleBuyAction"
      />
    </template>
  </BaseShopLayout>
</template>

<script setup>
import TradeTypeFilterRail from "@/components/shop/common/TradeTypeFilterRail.vue";
import BaseShopLayout from "@/components/shop/layouts/BaseShopLayout.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import ShopInventoryClassificationSection from "@/components/shop/modules/shop/components/ShopInventoryClassificationSection.vue";
import ShopInventoryPrimarySection from "@/components/shop/modules/shop/components/ShopInventoryPrimarySection.vue";
import TradeCommandButton from "@/components/shop/common/TradeCommandButton.vue";
import TradeItemList from "@/components/trade/TradeItemList.vue";
import TradeSummaryPanel from "@/components/trade/TradeSummaryPanel.vue";

const ctx = useTradeModalContext();
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
