<!-- Transfer and suggestion operations for the assortment workspace. -->
<template>
  <footer
    class="gm-shop-assortment-layout__operations"
    :class="{
      'gm-shop-assortment-layout__operations--suggestions':
        ctx.assortmentRightTab === 'suggestions',
    }"
    aria-label="Operacje asortymentu"
  >
    <section
      class="shop-assortment-footer-section shop-assortment-footer-section--transfer"
    >
      <div class="shop-assortment-footer-heading">
        <span
          class="bi bi-arrow-left-right shop-assortment-footer-heading__icon"
          aria-hidden="true"
        ></span>
        <span>Transfer</span>
      </div>

      <div class="gm-shop-assortment-transfer-meta">
        <span>
          {{ $t("shop.assortment.historyPrefix") }}:
          <strong>{{ ctx.containerUndoStack.length }}</strong>
        </span>
        <span>
          {{ $t("shop.assortment.selectedPrefix") }}:
          <strong>{{ totalSelected }}</strong>
        </span>
      </div>

      <div class="shop-assortment-action-row">
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
    </section>

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
  </footer>
</template>

<script setup>
import { computed } from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import AssortmentSuggestionActions from "@/components/shop/modules/assortment/components/AssortmentSuggestionActions.vue";

const ctx = useTradeModalContext();
const totalSelected = computed(
  () =>
    ctx.assortmentLeftSelectedKeys.length +
    ctx.assortmentRightSelectedKeys.length,
);
</script>
