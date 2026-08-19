<!-- Komponent modułu Sklep - ustal asortyment. Ten plik odpowiada za panel docelowy z podglądem asortymentu, sugestiami i akcjami końcowymi. -->
<template>
  <ShopModeTemplateFrame
    layout-class="shop-assortment-layout shop-assortment-layout--target"
    content-id="tradingSell"
    header-value="Sklep docelowy"
    content-class="shop-mode-content trade-panel-content assortment-mode-content"
    notification-zone="sell"
  >
    <template #filters>
      <div
        class="input-group input-group-md flex-shrink-0 shop-assortment-selector-bar"
      >
        <label
          class="input-group-text shop-assortment-selector-label"
          for="assort-right-container"
        >
          Sklep docelowy
        </label>
        <select
          id="assort-right-container"
          v-model.number="ctx.assortmentRightContainerModel"
          class="form-select shop-assortment-selector-input"
        >
          <option
            v-for="opt in ctx.assortmentShopOptions"
            :key="`assort-right-${opt.id}`"
            :value="opt.id"
          >
            {{ opt.label }}
          </option>
        </select>
      </div>
    </template>

    <div
      v-if="ctx.assortmentRightTab !== 'suggestions'"
      class="col-md-12 trade-form-section assortment-list-panel"
    >
      <div class="assort-panel-header">
        <span class="assort-panel-title">
          {{ ctx.containerLabelById(ctx.assortmentRightContainerId) }}
        </span>
        <span class="assort-panel-count">{{
          ctx.assortmentRightItems.length
        }}</span>
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
      class="col-md-12 trade-form-section assortment-list-panel assortment-list-panel--suggestions"
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

    <template #actions>
      <div
        class="shop-assortment-footer-actions shop-assortment-footer-actions--right"
        role="group"
        :aria-label="$t('shop.assortment.targetShop')"
      >
        <div
          class="shop-assortment-footer-right"
          :class="{
            'shop-assortment-footer-right--suggestions':
              ctx.assortmentRightTab === 'suggestions',
          }"
        >
          <section
            class="shop-assortment-footer-section shop-assortment-footer-section--shop"
          >
            <div class="shop-assortment-footer-heading">
              <span
                class="bi bi-shop-window shop-assortment-footer-heading__icon"
                aria-hidden="true"
              ></span>
              <span>{{ $t("shop.assortment.shopAssortmentTab") }}</span>
            </div>

            <div class="shop-assortment-footer-tabs">
              <button
                type="button"
                class="shop-assortment-action-btn shop-assortment-action-btn--tab"
                :class="{
                  'shop-assortment-action-btn--active':
                    ctx.assortmentRightTab !== 'suggestions',
                }"
                @click="ctx.setAssortmentRightTab('transfer')"
              >
                {{ $t("shop.assortment.shopAssortmentTab") }}
              </button>
              <button
                type="button"
                class="shop-assortment-action-btn shop-assortment-action-btn--tab"
                :class="{
                  'shop-assortment-action-btn--active':
                    ctx.assortmentRightTab === 'suggestions',
                }"
                @click="ctx.setAssortmentRightTab('suggestions')"
              >
                {{ $t("shop.assortment.suggestionsTab") }}
              </button>
            </div>

            <div
              v-if="ctx.assortmentRightTab !== 'suggestions'"
              class="shop-assortment-action-row shop-assortment-action-row--end"
            >
              <button
                type="button"
                class="shop-assortment-action-btn shop-assortment-action-btn--secondary"
                :disabled="!ctx.canMergeAssortmentSelection"
                @click="ctx.openAssortmentMergeDialog"
              >
                <span
                  class="bi bi-intersect shop-assortment-action-btn__icon"
                  aria-hidden="true"
                ></span>
                <span class="shop-assortment-action-btn__label">
                  {{ $t("shop.assortment.mergeSimilarButton") }}
                </span>
              </button>
              <button
                type="button"
                class="shop-assortment-action-btn shop-assortment-action-btn--primary"
                :disabled="!ctx.canMoveAssortmentToStack"
                @click="ctx.moveContainerSelection('rightToLeft')"
              >
                <span
                  class="bi bi-box-arrow-left shop-assortment-action-btn__icon"
                  aria-hidden="true"
                ></span>
                <span class="shop-assortment-action-btn__label">
                  {{ $t("shop.assortment.moveToStack") }}
                </span>
              </button>
            </div>
          </section>

          <AssortmentSuggestionActions
            v-if="ctx.assortmentRightTab === 'suggestions'"
            mode="right"
          />
        </div>
      </div>
    </template>
  </ShopModeTemplateFrame>
</template>

<script setup>
import { computed } from "vue";
import ShopSuggestionList from "@/components/shop/common/ShopSuggestionList.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import ShopModeTemplateFrame from "@/components/shop/layouts/ShopModeTemplateFrame.vue";
import AssortmentCardList from "@/components/shop/modules/assortment/components/AssortmentCardList.vue";
import AssortmentSuggestionActions from "@/components/shop/modules/assortment/components/AssortmentSuggestionActions.vue";
import i18n from "@/i18n";

const ctx = useTradeModalContext();
const t = (key, values = {}) => i18n.global.t(key, values);
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
</script>
