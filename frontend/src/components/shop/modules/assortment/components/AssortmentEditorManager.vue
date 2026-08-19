<!-- Komponent modułu Sklep - ustal asortyment. Ten plik obsługuje wariant edytora sugestii i akcji GM dla asortymentu sklepu. -->
<template>
  <ShopModeTemplateFrame
    content-id="tradingSell"
    :header-value="$t('shop.assortment.starterHeader')"
    notification-zone="sell"
  >
    <div
      v-if="hasEditorMeta"
      class="col-md-12 trade-form-section shop-editor-side-tools"
    >
      <div class="shop-mode-template-inline shop-editor-muted">
        <span v-if="ctx.assortmentRollPreviewMeta">{{
          previewSummaryLabel
        }}</span>
        <span v-if="ctx.assortmentRollPreviewMeta">{{
          previewDeltaLabel
        }}</span>
        <span v-if="loadedRangeLabel">{{ loadedRangeLabel }}</span>
        <span v-else-if="visibleItemsLabel">{{ visibleItemsLabel }}</span>
      </div>
    </div>

    <div
      v-if="ctx.assortmentRollPreview.length"
      class="col-md-6 trade-form-section shop-editor-side-panel"
    >
      <div class="trade-form-section-title">
        {{ $t("shop.assortment.rollPreview") }}
      </div>
      <ShopSuggestionList
        :entries="ctx.assortmentRollPreview"
        :show-draft-badge="false"
        use-entry-reason
      />
    </div>

    <div
      class="trade-form-section shop-editor-side-panel"
      :class="ctx.assortmentRollPreview.length ? 'col-md-6' : 'col-md-12'"
    >
      <div class="trade-form-section-title">
        {{ $t("shop.assortment.suggestionsHeader") }}
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
        {{ $t("shop.assortment.noSuggestionsSaveProfile") }}
      </div>
      <ShopSuggestionList
        :entries="ctx.shopSuggestions"
        :selected-ids="ctx.selectedSuggestionIds"
        selectable
        show-draft-badge
        :draft-badge-label="$t('shop.assortment.draftMissingTemplateBadge')"
        open-detail-on-context
        @toggle="ctx.handleToggleShopSuggestion"
        @open-detail="ctx.openSuggestionDetailDialog"
      />
    </div>

    <template #actions>
      <div class="shop-mode-template-actions">
        <AssortmentSuggestionActions mode="editor" />
      </div>
    </template>
  </ShopModeTemplateFrame>
</template>

<script setup>
import { computed } from "vue";
import ShopSuggestionList from "@/components/shop/common/ShopSuggestionList.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import ShopModeTemplateFrame from "@/components/shop/layouts/ShopModeTemplateFrame.vue";
import AssortmentSuggestionActions from "@/components/shop/modules/assortment/components/AssortmentSuggestionActions.vue";
import i18n from "@/i18n";

const ctx = useTradeModalContext();
const t = (key, values = {}) => i18n.global.t(key, values);

const previewSummaryLabel = computed(() =>
  t("shop.assortment.previewSummary", {
    unique: ctx.assortmentRollPreviewMeta?.previewUnique,
    instances: ctx.assortmentRollPreviewMeta?.previewInstances,
    target: ctx.assortmentRollPreviewMeta?.targetInstances,
  }),
);
const previewDeltaLabel = computed(() =>
  t("shop.assortment.previewDelta", {
    deltaUnique: ctx.assortmentRollPreviewMeta?.deltaUnique,
    deltaInstances: ctx.assortmentRollPreviewMeta?.deltaInstances,
  }),
);
const loadedRangeLabel = computed(() => {
  if (!ctx.shopSuggestions.length || !ctx.shopTemplateRecommendations.length) {
    return "";
  }
  return t("shop.assortment.loadedRange", {
    shown: ctx.shopSuggestions.length,
    total: ctx.shopTemplateRecommendations.length,
    units: ctx.suggestionTotalUnits(),
  });
});
const visibleItemsLabel = computed(() => {
  if (!ctx.shopSuggestions.length || ctx.shopTemplateRecommendations.length) {
    return "";
  }
  return t("shop.assortment.visibleItems", {
    count: ctx.shopSuggestions.length,
    units: ctx.suggestionTotalUnits(),
  });
});
const hasEditorMeta = computed(() =>
  Boolean(
    ctx.assortmentRollPreviewMeta ||
    loadedRangeLabel.value ||
    visibleItemsLabel.value,
  ),
);
</script>
