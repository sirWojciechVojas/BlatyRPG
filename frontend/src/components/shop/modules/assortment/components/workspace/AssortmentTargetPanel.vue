<!-- Target shop assortment and suggestion panel. -->
<template>
  <section
    class="gm-shop-assortment-panel gm-shop-assortment-panel--target"
    :class="{
      'gm-shop-assortment-panel--suggestions':
        ctx.assortmentRightTab === 'suggestions',
    }"
    aria-labelledby="gm-shop-assortment-target-title"
  >
    <div class="gm-shop-assortment-panel__heading">
      <h3 id="gm-shop-assortment-target-title">
        {{ $t("shop.assortment.targetShop") }}
      </h3>
      <span class="gm-shop-assortment-panel__count">
        {{ ctx.assortmentRightItems.length }}
      </span>
    </div>

    <div class="input-group input-group-md gm-shop-assortment-selector-bar">
      <label
        class="input-group-text shop-assortment-selector-label"
        for="assort-workspace-right-container"
      >
        {{ $t("shop.assortment.targetShop") }}
      </label>
      <select
        id="assort-workspace-right-container"
        v-model.number="ctx.assortmentRightContainerModel"
        class="form-select shop-assortment-selector-input"
      >
        <option
          v-for="opt in ctx.assortmentShopOptions"
          :key="`assort-workspace-right-${opt.id}`"
          :value="opt.id"
        >
          {{ opt.label }}
        </option>
      </select>
    </div>

    <div
      v-if="ctx.assortmentRightTab !== 'suggestions'"
      id="tradingSell"
      class="assortment-list-panel gm-shop-assortment-list-panel"
    >
      <div class="assort-panel-header">
        <span class="assort-panel-title">
          {{ ctx.containerLabelById(ctx.assortmentRightContainerId) }}
        </span>
        <span class="assort-panel-count">
          {{ ctx.assortmentRightItems.length }}
        </span>
      </div>
      <AssortmentCardList side="right" :items="ctx.assortmentRightItems" />
      <div class="assort-footer">
        <div class="assort-selection-info">
          {{ $t("shop.assortment.selectedPrefix") }}:
          {{ ctx.assortmentRightSelectedKeys.length }}
        </div>
      </div>
    </div>

    <div
      v-else
      id="tradingSell"
      class="assortment-list-panel assortment-list-panel--suggestions gm-shop-assortment-list-panel gm-shop-assortment-list-panel--suggestions"
    >
      <div class="trade-form-section-title assortment-section-title">
        {{ $t("shop.assortment.suggestionsHeader") }}
      </div>
      <div v-if="ctx.assortmentRollPreviewMeta" class="shop-editor-muted">
        {{
          $t("shop.assortment.previewSummary", {
            unique: ctx.assortmentRollPreviewMeta.previewUnique,
            instances: ctx.assortmentRollPreviewMeta.previewInstances,
            target: ctx.assortmentRollPreviewMeta.targetInstances,
          })
        }}
      </div>
      <div v-if="ctx.assortmentRollPreviewMeta" class="shop-editor-muted">
        {{
          $t("shop.assortment.previewDelta", {
            deltaUnique: ctx.assortmentRollPreviewMeta.deltaUnique,
            deltaInstances: ctx.assortmentRollPreviewMeta.deltaInstances,
          })
        }}
      </div>
      <ShopSuggestionList
        v-if="ctx.assortmentRollPreview.length"
        :entries="ctx.assortmentRollPreview"
        :show-draft-badge="false"
        use-entry-reason
      />
      <div v-if="loadedRangeLabel" class="shop-editor-muted">
        {{ loadedRangeLabel }}
      </div>
      <div v-else-if="visibleItemsLabel" class="shop-editor-muted">
        {{ visibleItemsLabel }}
      </div>
      <div
        v-if="ctx.shopTemplateRecommendations.length"
        class="shop-mode-template-inline shop-editor-muted"
      >
        {{
          ctx.shopRemainingRecommendations.length
            ? $t("shop.assortment.loadMoreSuggestions", { count: 30 })
            : $t("shop.assortment.noMoreSuggestions")
        }}
      </div>
      <div v-if="!ctx.shopSuggestions.length" class="shop-editor-empty">
        {{ $t("shop.assortment.noSuggestionsSetProfile") }}
      </div>
      <ShopSuggestionList
        :entries="ctx.shopSuggestions"
        :selected-ids="ctx.selectedSuggestionIds"
        selectable
        open-detail-on-context
        @toggle="ctx.handleToggleShopSuggestion"
        @open-detail="ctx.openSuggestionDetailDialog"
      />
    </div>
  </section>
</template>

<script setup>
import { computed } from "vue";
import AssortmentCardList from "@/components/shop/modules/assortment/components/AssortmentCardList.vue";
import ShopSuggestionList from "@/components/shop/common/ShopSuggestionList.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import i18n from "@/i18n";

const ctx = useTradeModalContext();
const t = (key, values = {}) => i18n.global.t(key, values);
const loadedRangeLabel = computed(() => {
  if (!ctx.shopSuggestions.length || !ctx.shopTemplateRecommendations.length)
    return "";
  return t("shop.assortment.loadedRange", {
    shown: ctx.shopSuggestions.length,
    total: ctx.shopTemplateRecommendations.length,
    units: ctx.suggestionTotalUnits(),
  });
});
const visibleItemsLabel = computed(() => {
  if (!ctx.shopSuggestions.length || ctx.shopTemplateRecommendations.length)
    return "";
  return t("shop.assortment.visibleItems", {
    count: ctx.shopSuggestions.length,
    units: ctx.suggestionTotalUnits(),
  });
});
</script>
