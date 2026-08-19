<!-- Komponent modułu Sklep - ustal asortyment. Ten plik odpowiada za panel źródłowy, czyli wybór i przygotowanie pozycji do transferu do asortymentu sklepu. -->
<template>
  <ShopModeTemplateFrame
    layout-class="shop-assortment-layout shop-assortment-layout--source"
    content-id="tradingBuy"
    header-value="Źródło asortymentu"
    content-class="shop-mode-content trade-panel-content assortment-mode-content"
    notification-zone="buy"
  >
    <template #filters>
      <div
        class="input-group input-group-md flex-shrink-0 shop-assortment-selector-bar"
      >
        <label
          class="input-group-text shop-assortment-selector-label"
          for="assort-left-container"
        >
          Źródło (Stos / Kosz)
        </label>
        <select
          id="assort-left-container"
          v-model.number="ctx.assortmentLeftContainerModel"
          class="form-select shop-assortment-selector-input"
        >
          <option
            v-for="opt in ctx.assortmentSourceOptions"
            :key="`assort-left-${opt.id}`"
            :value="opt.id"
          >
            {{ opt.label }}
          </option>
        </select>
        <span class="input-group-text shop-assortment-selector-meta">
          {{ $t("shop.assortment.historyPrefix") }}:
          {{ ctx.containerUndoStack.length }}
        </span>
      </div>
    </template>

    <div class="col-md-12 trade-form-section assortment-list-panel">
      <div class="assort-panel-header">
        <span class="assort-panel-title">
          {{ ctx.containerLabelById(ctx.assortmentLeftContainerId) }}
        </span>
        <span class="assort-panel-count">{{
          ctx.assortmentLeftItems.length
        }}</span>
      </div>
      <AssortmentCardList side="left" :items="ctx.assortmentLeftItems" />
      <div class="assort-footer">
        <div class="assort-selection-info">
          {{ $t("shop.assortment.selectedPrefix") }}:
          {{ ctx.assortmentLeftSelectedKeys.length }}
        </div>
      </div>
    </div>

    <template #actions>
      <div
        class="shop-assortment-footer-actions shop-assortment-footer-actions--left"
        role="group"
        :aria-label="$t('shop.assortment.sourceLabel')"
      >
        <div class="shop-assortment-footer-left">
          <button
            type="button"
            class="shop-assortment-action-btn shop-assortment-action-btn--secondary"
            :disabled="!ctx.containerUndoStack.length"
            @click="ctx.undoContainerAction"
          >
            <span
              class="bi bi-arrow-counterclockwise shop-assortment-action-btn__icon"
              aria-hidden="true"
            ></span>
            <span class="shop-assortment-action-btn__label">
              {{ $t("shop.assortment.undo") }}
            </span>
          </button>
          <button
            type="button"
            class="shop-assortment-action-btn shop-assortment-action-btn--primary"
            :disabled="!ctx.canMoveAssortmentToShop"
            @click="ctx.moveContainerSelection('leftToRight')"
          >
            <span
              class="bi bi-box-arrow-in-right shop-assortment-action-btn__icon"
              aria-hidden="true"
            ></span>
            <span class="shop-assortment-action-btn__label">
              {{ $t("shop.assortment.moveToShop") }}
            </span>
          </button>
        </div>
      </div>
    </template>
  </ShopModeTemplateFrame>
</template>

<script setup>
import { useTradeModalContext } from "@/components/shop/shopContext";
import ShopModeTemplateFrame from "@/components/shop/layouts/ShopModeTemplateFrame.vue";
import AssortmentCardList from "@/components/shop/modules/assortment/components/AssortmentCardList.vue";

const ctx = useTradeModalContext();
</script>
