<!-- Compact assortment suggestion actions used by editor panels. -->
<template>
  <div class="assortment-suggestion-actions">
    <div class="shop-mode-template-actions__row">
      <button
        type="button"
        class="btn btn-outline-light btn-sm shop-editor-icon-btn"
        :title="primaryRefreshLabel"
        :aria-label="primaryRefreshLabel"
        @click="ctx.generateShopSuggestions"
      >
        <span class="visually-hidden">{{ primaryRefreshLabel }}</span>
        <span
          class="bi bi-arrow-clockwise assortment-action-icon"
          aria-hidden="true"
        ></span>
      </button>

      <button
        v-if="mode === 'editor'"
        type="button"
        class="btn btn-outline-warning btn-sm shop-editor-icon-btn"
        :disabled="!ctx.selectedSuggestionIds.length"
        :title="$t('shop.assortment.addDraftTemplates')"
        :aria-label="$t('shop.assortment.addDraftTemplates')"
        @click="ctx.handleCreateDraftTemplatesFromSelected"
      >
        <span class="visually-hidden">{{
          $t("shop.assortment.addDraftTemplates")
        }}</span>
        <span
          class="bi bi-file-earmark-plus assortment-action-icon"
          aria-hidden="true"
        ></span>
      </button>

      <button
        type="button"
        class="btn btn-outline-light btn-sm shop-editor-icon-btn"
        :disabled="!ctx.selectedSuggestionIds.length"
        :title="selectedLabel"
        :aria-label="selectedLabel"
        @click="ctx.applySelectedShopSuggestions"
      >
        <span class="visually-hidden">{{ selectedLabel }}</span>
        <span
          class="bi bi-check2-square assortment-action-icon"
          aria-hidden="true"
        ></span>
      </button>

      <button
        type="button"
        class="btn btn-success btn-sm shop-editor-icon-btn"
        :disabled="!ctx.shopSuggestions.length"
        :title="allLabel"
        :aria-label="allLabel"
        @click="ctx.applyAllShopSuggestions"
      >
        <span class="visually-hidden">{{ allLabel }}</span>
        <span
          class="bi bi-list-check assortment-action-icon"
          aria-hidden="true"
        ></span>
      </button>
    </div>

    <div class="shop-mode-template-actions__row">
      <label class="assort-label mb-0" :for="rollTargetId">
        {{ $t("shop.assortment.rollInstancesLabel") }}
      </label>
      <input
        :id="rollTargetId"
        type="number"
        min="8"
        max="20"
        class="form-control form-control-sm shop-editor-roll-target"
        :value="ctx.assortmentRollTarget"
        @input="ctx.handleSetAssortmentRollTarget($event.target.value)"
      />
      <button
        type="button"
        class="btn btn-outline-warning btn-sm shop-editor-icon-btn"
        :disabled="!ctx.shopSuggestions.length"
        :title="$t('shop.assortment.rollPreview')"
        :aria-label="$t('shop.assortment.rollPreview')"
        @click="ctx.handlePreviewShopStarterAssortment"
      >
        <span class="visually-hidden">{{
          $t("shop.assortment.rollPreview")
        }}</span>
        <span
          class="bi bi-eye assortment-action-icon"
          aria-hidden="true"
        ></span>
      </button>
      <button
        type="button"
        class="btn btn-warning btn-sm shop-editor-icon-btn"
        :disabled="!ctx.shopSuggestions.length"
        :title="rollLabel"
        :aria-label="rollLabel"
        @click="ctx.handleRollShopStarterAssortment"
      >
        <span class="visually-hidden">{{ rollLabel }}</span>
        <span
          class="bi bi-dice-5 assortment-action-icon"
          aria-hidden="true"
        ></span>
      </button>
      <button
        type="button"
        class="btn btn-success btn-sm shop-editor-icon-btn"
        :disabled="!ctx.assortmentRollPreview.length"
        :title="$t('shop.assortment.applyPreviewResult')"
        :aria-label="$t('shop.assortment.applyPreviewResult')"
        @click="ctx.handleApplyAssortmentRollPreview"
      >
        <span class="visually-hidden">
          {{ $t("shop.assortment.applyPreviewResult") }}
        </span>
        <span
          class="bi bi-check2-circle assortment-action-icon"
          aria-hidden="true"
        ></span>
      </button>
      <button
        type="button"
        class="btn btn-outline-light btn-sm shop-editor-icon-btn"
        :disabled="!ctx.shopRemainingRecommendations.length"
        :title="loadMoreLabel"
        :aria-label="loadMoreLabel"
        @click="ctx.loadMoreShopSuggestions(30)"
      >
        <span class="visually-hidden">{{ loadMoreLabel }}</span>
        <span
          class="bi bi-chevron-double-down assortment-action-icon"
          aria-hidden="true"
        ></span>
      </button>
    </div>
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
