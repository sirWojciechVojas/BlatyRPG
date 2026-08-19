<!-- Moduł GM: Szablony - dodaj/edytuj. Ten plik jest wejściem modułu odpowiedzialnego za tworzenie oraz edycję szablonów przedmiotów. -->
<template>
  <BaseShopLayout
    shell-class="trade-list-shell trade-list-shell--sell"
    content-id="tradingSell"
    content-class="tab-content outline w-100 text-light bg-transparent trade-list"
    notification-zone="sell"
    :toolbar-inside-shell="true"
    :show-toolbar="!ctx.showSellForm && !ctx.showSellAddForm"
  >
    <template #header>
      <div class="input-group input-group-md">
        <input
          type="text"
          class="form-control bg-transparent text-light ih-95"
          :value="ctx.sellListTitle"
          readonly
          disabled
        />
      </div>
    </template>

    <template #toolbar>
      <div class="trade-category-search">
        <input
          id="template-category-search"
          v-model.trim="ctx.sellTypeSearch"
          type="search"
          class="form-control-sm w-100 trade-input"
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
      <div v-if="ctx.showSellForm" class="trade-form template-editor-workbench">
        <div
          class="template-editor-actionbar sticky-top d-flex align-items-center justify-content-between gap-2"
        >
          <div class="template-editor-heading min-w-0">
            <div class="template-editor-title text-truncate">
              {{ $t("shop.templateEditor.editTemplate") }}
            </div>
            <small class="template-editor-status text-uppercase">
              ID {{ activeTemplateId }}
            </small>
          </div>
          <div
            class="template-editor-actions d-flex align-items-center justify-content-end gap-2 flex-wrap"
          >
            <button
              type="button"
              class="btn btn-outline-danger btn-sm"
              @click="ctx.handleDeleteTemplate"
            >
              {{ $t("shop.templateEditor.deleteToTrash") }}
            </button>
            <button
              type="button"
              class="btn btn-outline-light btn-sm"
              @click="ctx.startTemplateCreate"
            >
              {{ $t("shop.templateEditor.newTemplate") }}
            </button>
          </div>
        </div>
        <TemplateRecordForm variant="edit" />
      </div>

      <div
        v-else-if="ctx.showSellAddForm"
        class="trade-form template-editor-workbench"
      >
        <div
          class="template-editor-actionbar sticky-top d-flex align-items-center justify-content-between gap-2"
        >
          <div class="template-editor-heading min-w-0">
            <div class="template-editor-title text-truncate">
              {{ $t("shop.templateEditor.newTemplate") }}
            </div>
            <small class="template-editor-status text-uppercase">
              ID {{ activeTemplateId }}
            </small>
          </div>
          <div
            class="template-editor-actions d-flex align-items-center justify-content-end gap-2 flex-wrap"
          >
            <button
              type="button"
              class="btn btn-outline-light btn-sm"
              @click="ctx.resetNewTemplateForm"
            >
              {{ $t("shop.templateEditor.clearForm") }}
            </button>
          </div>
        </div>
        <TemplateRecordForm variant="create" />
      </div>

      <TradeItemList
        v-else
        mode="sell"
        :items="ctx.filteredSellItems"
        :row-class="ctx.sellItemClass"
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
import TradeTypeFilterRail from "@/components/shop/common/TradeTypeFilterRail.vue";
import BaseShopLayout from "@/components/shop/layouts/BaseShopLayout.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import TemplateRecordForm from "@/components/shop/modules/template-editor/components/TemplateRecordForm.vue";
import TradeCommandButton from "@/components/shop/common/TradeCommandButton.vue";
import TradeItemList from "@/components/trade/TradeItemList.vue";
import TradeSummaryPanel from "@/components/trade/TradeSummaryPanel.vue";

const ctx = useTradeModalContext();

const activeTemplateId = computed(() => {
  const form = ctx.showSellAddForm
    ? ctx.localNewTemplateForm
    : ctx.localTemplateForm;
  const id = form?.ID;

  return id === undefined || id === null || id === "" ? "-" : id;
});
</script>
