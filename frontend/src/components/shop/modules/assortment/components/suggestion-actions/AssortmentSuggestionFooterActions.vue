<!-- Expanded assortment suggestion actions used in the right workspace footer. -->
<template>
  <div class="shop-assortment-suggestion-actions">
    <section
      class="shop-assortment-footer-section shop-assortment-footer-section--suggestions"
    >
      <div class="shop-assortment-footer-heading">
        <span
          class="bi bi-stars shop-assortment-footer-heading__icon"
          aria-hidden="true"
        ></span>
        <span>{{ $t("shop.assortment.suggestionsTab") }}</span>
      </div>

      <div class="shop-assortment-action-row">
        <button
          type="button"
          class="shop-assortment-action-btn shop-assortment-action-btn--secondary"
          :title="primaryRefreshLabel"
          :aria-label="primaryRefreshLabel"
          @click="ctx.generateShopSuggestions"
        >
          <span
            class="bi bi-arrow-clockwise shop-assortment-action-btn__icon"
            aria-hidden="true"
          ></span>
          <span class="shop-assortment-action-btn__label">
            {{ primaryRefreshLabel }}
          </span>
        </button>

        <button
          type="button"
          class="shop-assortment-action-btn shop-assortment-action-btn--secondary"
          :disabled="!ctx.selectedSuggestionIds.length"
          :title="selectedLabel"
          :aria-label="selectedLabel"
          @click="ctx.applySelectedShopSuggestions"
        >
          <span
            class="bi bi-check2-square shop-assortment-action-btn__icon"
            aria-hidden="true"
          ></span>
          <span class="shop-assortment-action-btn__label">
            {{ selectedLabel }}
          </span>
        </button>

        <button
          type="button"
          class="shop-assortment-action-btn shop-assortment-action-btn--primary"
          :disabled="!ctx.shopSuggestions.length"
          :title="allLabel"
          :aria-label="allLabel"
          @click="ctx.applyAllShopSuggestions"
        >
          <span
            class="bi bi-list-check shop-assortment-action-btn__icon"
            aria-hidden="true"
          ></span>
          <span class="shop-assortment-action-btn__label">
            {{ allLabel }}
          </span>
        </button>

        <button
          type="button"
          class="shop-assortment-action-btn shop-assortment-action-btn--secondary"
          :disabled="!ctx.shopRemainingRecommendations.length"
          :title="loadMoreLabel"
          :aria-label="loadMoreLabel"
          @click="ctx.loadMoreShopSuggestions(30)"
        >
          <span
            class="bi bi-chevron-double-down shop-assortment-action-btn__icon"
            aria-hidden="true"
          ></span>
          <span class="shop-assortment-action-btn__label">
            {{ loadMoreLabel }}
          </span>
        </button>
      </div>
    </section>

    <section
      class="shop-assortment-footer-section shop-assortment-footer-section--roll"
    >
      <label class="shop-assortment-footer-heading" :for="rollTargetId">
        <span
          class="bi bi-dice-5 shop-assortment-footer-heading__icon"
          aria-hidden="true"
        ></span>
        <span>{{ $t("shop.assortment.rollInstancesLabel") }}</span>
      </label>

      <div class="shop-assortment-roll-row">
        <input
          :id="rollTargetId"
          type="number"
          min="8"
          max="20"
          class="form-control form-control-sm shop-assortment-roll-input"
          :value="ctx.assortmentRollTarget"
          @input="ctx.handleSetAssortmentRollTarget($event.target.value)"
        />
        <button
          type="button"
          class="shop-assortment-action-btn shop-assortment-action-btn--secondary"
          :disabled="!ctx.shopSuggestions.length"
          :title="$t('shop.assortment.rollPreview')"
          :aria-label="$t('shop.assortment.rollPreview')"
          @click="ctx.handlePreviewShopStarterAssortment"
        >
          <span
            class="bi bi-eye shop-assortment-action-btn__icon"
            aria-hidden="true"
          ></span>
          <span class="shop-assortment-action-btn__label">
            {{ $t("shop.assortment.rollPreview") }}
          </span>
        </button>
        <button
          type="button"
          class="shop-assortment-action-btn shop-assortment-action-btn--accent"
          :disabled="!ctx.shopSuggestions.length"
          :title="rollLabel"
          :aria-label="rollLabel"
          @click="ctx.handleRollShopStarterAssortment"
        >
          <span
            class="bi bi-dice-5 shop-assortment-action-btn__icon"
            aria-hidden="true"
          ></span>
          <span class="shop-assortment-action-btn__label">
            {{ rollLabel }}
          </span>
        </button>
        <button
          type="button"
          class="shop-assortment-action-btn shop-assortment-action-btn--primary"
          :disabled="!ctx.assortmentRollPreview.length"
          :title="$t('shop.assortment.applyPreviewResult')"
          :aria-label="$t('shop.assortment.applyPreviewResult')"
          @click="ctx.handleApplyAssortmentRollPreview"
        >
          <span
            class="bi bi-check2-circle shop-assortment-action-btn__icon"
            aria-hidden="true"
          ></span>
          <span class="shop-assortment-action-btn__label">
            {{ $t("shop.assortment.applyPreviewResult") }}
          </span>
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { toRef } from "vue";
import { useAssortmentSuggestionActions } from "./useAssortmentSuggestionActions";

const props = defineProps({ mode: { type: String, required: true } });
const {
  ctx,
  primaryRefreshLabel,
  selectedLabel,
  allLabel,
  rollLabel,
  rollTargetId,
  loadMoreLabel,
} = useAssortmentSuggestionActions(toRef(props, "mode"));
</script>
