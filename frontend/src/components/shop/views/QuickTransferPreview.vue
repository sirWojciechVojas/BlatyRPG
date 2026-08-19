<!-- Moduł GM: Sklep - szybki transfer/podgląd. Ten plik odpowiada za podgląd i szybkie przenoszenie przedmiotów między kontenerami sklepu oraz postaci. -->
<template>
  <ShopModeTemplateFrame
    :content-id="panelContentId"
    :header-value="$t('shop.quickTransfer.title')"
    :title="panelTitle"
    :notification-zone="notificationZone"
  >
    <template v-if="side === 'left'">
      <div class="col-md-12 trade-form-section">
        <div class="trade-form-section-title">
          {{ $t("shop.quickTransfer.gmTransferTitle") }}
        </div>

        <div class="row">
          <div class="col-md-3">
            <label class="trade-label" for="assort-gm-item">
              {{ $t("shop.quickTransfer.itemLabel") }}
            </label>
          </div>
          <div class="form-row col-md-9 p-0 m-0">
            <select
              id="assort-gm-item"
              v-model="ctx.gmMoveItemKeyModel"
              class="form-control-sm w-100 trade-input"
            >
              <option value="" disabled>
                {{ $t("shop.common.chooseOption") }}
              </option>
              <option
                v-for="opt in ctx.gmMoveItemOptions"
                :key="`gm-opt-${opt.optionKey}`"
                :value="opt.optionKey"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3">
            <label class="trade-label" for="assort-gm-target">
              {{ $t("shop.quickTransfer.moveToLabel") }}
            </label>
          </div>
          <div class="form-row col-md-9 p-0 m-0">
            <select
              id="assort-gm-target"
              v-model.number="ctx.gmMoveTargetContainerModel"
              class="form-control-sm w-100 trade-input"
            >
              <option
                v-for="opt in ctx.containerSelectOptions"
                :key="`gm-target-${opt.id}`"
                :value="opt.id"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <QuantityControl
          v-if="ctx.gmMoveQuantityEnabled"
          id="assort-gm-qty"
          :label="$t('shop.quickTransfer.quantityLabel')"
          :model-value="ctx.gmMoveQuantityModel"
          input-class="trade-input"
          wrapper-class="shop-mode-template-quantity"
          @update:modelValue="ctx.handleSetGmMoveQuantity($event)"
        />

        <div v-if="ctx.gmMoveQuantityEnabled" class="shop-editor-muted">
          {{
            $t("shop.quickTransfer.gmQuantityHint", {
              max: ctx.gmMoveQuantityMax,
            })
          }}
        </div>
      </div>

      <div class="col-md-12 trade-form-section">
        <div class="trade-form-section-title">
          {{ $t("shop.quickTransfer.containersOverview") }}
        </div>
        <div class="assort-overview-list">
          <div
            v-for="entry in ctx.containerOverview"
            :key="`overview-${entry.id}`"
            class="assort-overview-item"
          >
            <div class="assort-overview-title">{{ entry.label }}</div>
            <div class="assort-overview-items">
              <span
                v-for="item in entry.items"
                :key="`overview-${entry.id}-${item.key}`"
                class="assort-overview-chip"
              >
                <span
                  class="assort-chip-icon trade-icon inventory-item legacy-inventory-icon"
                  :class="ctx.legacyIconClassForItem(item)"
                  role="img"
                  :aria-label="item.name"
                ></span>
                {{ ctx.containerItemInlineLabel(item) }}
              </span>
              <span v-if="!entry.items.length" class="assort-overview-empty">
                {{ $t("shop.quickTransfer.emptyShort") }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="col-md-12 trade-form-section">
        <div class="trade-form-section-title">
          {{ $t("shop.quickTransfer.shopPurchaseTitle") }}
        </div>

        <div class="row">
          <div class="col-md-3">
            <label class="trade-label" for="assort-shop-source">
              {{ $t("shop.quickTransfer.shopLabel") }}
            </label>
          </div>
          <div class="form-row col-md-9 p-0 m-0">
            <select
              id="assort-shop-source"
              v-model.number="ctx.shopBuyContainerModel"
              class="form-control-sm w-100 trade-input"
            >
              <option
                v-for="opt in ctx.shopContainerOptions"
                :key="`shop-source-${opt.id}`"
                :value="opt.id"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3">
            <label class="trade-label" for="assort-shop-item">
              {{ $t("shop.quickTransfer.goodsLabel") }}
            </label>
          </div>
          <div class="form-row col-md-9 p-0 m-0">
            <select
              id="assort-shop-item"
              v-model="ctx.shopBuyItemKeyModel"
              class="form-control-sm w-100 trade-input"
            >
              <option value="" disabled>
                {{ $t("shop.common.chooseOption") }}
              </option>
              <option
                v-for="opt in ctx.shopBuyItemOptions"
                :key="`shop-item-${opt.key}`"
                :value="opt.key"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3">
            <label class="trade-label" for="assort-shop-target">
              {{ $t("shop.quickTransfer.buyerLabel") }}
            </label>
          </div>
          <div class="form-row col-md-9 p-0 m-0">
            <select
              id="assort-shop-target"
              v-model.number="ctx.shopBuyTargetContainerModel"
              class="form-control-sm w-100 trade-input"
            >
              <option
                v-for="opt in ctx.characterContainerOptions"
                :key="`shop-target-${opt.id}`"
                :value="opt.id"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <QuantityControl
          v-if="ctx.shopBuyQuantityEnabled"
          id="assort-shop-qty"
          :label="$t('shop.common.quantity')"
          :model-value="ctx.shopBuyQuantityModel"
          input-class="trade-input"
          wrapper-class="shop-mode-template-quantity"
          @update:modelValue="ctx.shopBuyQuantityModel = $event"
        />

        <div v-if="!ctx.shopBuyItemOptions.length" class="assort-empty">
          {{ $t("shop.quickTransfer.noGoodsInShop") }}
        </div>
      </div>
    </template>

    <template #actions>
      <div class="shop-mode-template-actions">
        <div
          class="shop-mode-template-actions__row shop-mode-template-actions__row--end"
        >
          <button
            v-if="side === 'left'"
            type="button"
            class="btn btn-success btn-sm"
            @click="ctx.handleGmMove"
          >
            {{ $t("shop.quickTransfer.moveButton") }}
          </button>
          <button
            v-else
            type="button"
            class="btn btn-success btn-sm"
            @click="ctx.handleShopBuy"
          >
            {{ $t("shop.quickTransfer.buyButton") }}
          </button>
        </div>
      </div>
    </template>
  </ShopModeTemplateFrame>
</template>

<script setup>
import { computed } from "vue";
import ShopModeTemplateFrame from "@/components/shop/layouts/ShopModeTemplateFrame.vue";
import QuantityControl from "@/components/trade/QuantityControl.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import i18n from "@/i18n";

const props = defineProps({
  side: {
    type: String,
    default: "left",
  },
});

const ctx = useTradeModalContext();
const t = (key) => i18n.global.t(key);

const panelContentId = computed(() =>
  props.side === "left" ? "tradingBuy" : "tradingSell",
);

const panelTitle = computed(() =>
  props.side === "left"
    ? t("shop.quickTransfer.title")
    : t("shop.quickTransfer.actionsPreview"),
);

const notificationZone = computed(() =>
  props.side === "left" ? "buy" : "sell",
);
</script>
