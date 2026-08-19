<!-- Source container and item selection panel. -->
<template>
  <section
    class="gm-shop-assortment-panel gm-shop-assortment-panel--source"
    aria-labelledby="gm-shop-assortment-source-title"
  >
    <div class="gm-shop-assortment-panel__heading">
      <h3 id="gm-shop-assortment-source-title">Źródło asortymentu</h3>
      <span class="gm-shop-assortment-panel__count">
        {{ ctx.assortmentLeftItems.length }}
      </span>
    </div>

    <div class="input-group input-group-md gm-shop-assortment-selector-bar">
      <label
        class="input-group-text shop-assortment-selector-label"
        for="assort-workspace-left-container"
      >
        {{ $t("shop.assortment.sourceLabel") }}
      </label>
      <select
        id="assort-workspace-left-container"
        v-model.number="ctx.assortmentLeftContainerModel"
        class="form-select shop-assortment-selector-input"
      >
        <option
          v-for="opt in ctx.assortmentSourceOptions"
          :key="`assort-workspace-left-${opt.id}`"
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

    <div
      id="tradingBuy"
      class="assortment-list-panel gm-shop-assortment-list-panel"
    >
      <div class="assort-panel-header">
        <span class="assort-panel-title">
          {{ ctx.containerLabelById(ctx.assortmentLeftContainerId) }}
        </span>
        <span class="assort-panel-count">
          {{ ctx.assortmentLeftItems.length }}
        </span>
      </div>
      <AssortmentCardList side="left" :items="ctx.assortmentLeftItems" />
      <div class="assort-footer">
        <div class="assort-selection-info">
          {{ $t("shop.assortment.selectedPrefix") }}:
          {{ ctx.assortmentLeftSelectedKeys.length }}
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import AssortmentCardList from "@/components/shop/modules/assortment/components/AssortmentCardList.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();
</script>
