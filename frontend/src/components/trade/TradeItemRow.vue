<!-- Responsibility: TradeItemRow shop interface component. -->
<template>
  <div
    ref="rowRoot"
    class="tradingGood trade-modal__row outline d-flex align-items-center"
    :class="[
      rowClass,
      {
        act: isAct,
        active: isActive,
        'trade-modal__row--unavailable': isUnavailable,
      },
    ]"
    :aria-disabled="isUnavailable ? 'true' : 'false'"
    :title="
      item.PRICING_WARNING === 'missing_exchange_rate'
        ? $t('shop.alerts.missingExchangeRate')
        : ''
    "
    :aria-selected="String(isActive)"
    role="option"
    tabindex="0"
    @click="handleSelect"
    @contextmenu.prevent="$emit('open-detail', item, mode)"
    @keydown.enter.prevent="handleSelect"
    @keydown.space.prevent="handleSelect"
    @keydown.f2.prevent="$emit('open-detail', item, mode)"
  >
    <div class="flex-shrink-0 outline trade-modal__row-icon-wrap">
      <span class="trade-modal__row-icon-frame">
        <img
          v-if="resolvedImageSrc"
          class="trade-icon trade-modal__row-icon trade-modal__row-image"
          :src="resolvedImageSrc"
          :alt="item.NAME"
          loading="lazy"
          decoding="async"
          @error="handleImageError"
        />
        <span
          v-else
          class="inventory-item legacy-inventory-icon trade-icon trade-modal__row-icon"
          :class="iconClass"
          role="img"
          :aria-label="item.NAME"
        ></span>
      </span>
      <button
        v-if="stackLabel && canShowQtyTrigger"
        type="button"
        class="trade-modal__row-stack"
        :class="{ 'trade-modal__row-stack--interactive': canShowQtyTrigger }"
        :aria-expanded="String(showQtyPopover)"
        @click.stop="toggleQtyPopover"
      >
        {{ stackLabel }}
      </button>
      <span v-else-if="stackLabel" class="trade-modal__row-stack">
        {{ stackLabel }}
      </span>
      <div
        v-if="showQtyPopover"
        class="trade-qty-popover"
        role="dialog"
        :aria-label="$t('shop.tradeItemRow.quantityPickerAria')"
        @click.stop
      >
        <div class="trade-qty-popover__controls">
          <button
            type="button"
            class="trade-qty-popover__btn"
            @click="stepQty(-1)"
          >
            -
          </button>
          <input
            type="number"
            min="1"
            :max="maxQuantity"
            class="trade-qty-popover__input"
            :value="selectedQuantity"
            @input="handleQuantityInput"
          />
          <button
            type="button"
            class="trade-qty-popover__btn"
            @click="stepQty(1)"
          >
            +
          </button>
        </div>
      </div>
    </div>
    <div class="flex-grow-1 list-group-item-text outline trade-modal__row-main">
      <b class="trade-modal__row-name">
        {{ item.NAME }}
      </b>
      <span class="trade-modal__row-desc" :title="shortDesc">
        {{ shortDesc }}
      </span>
    </div>
    <TradeCoinLine
      v-if="showPrice"
      class="tradingBrassLine text-light outline trade-modal__row-price"
      :brass="displayedPrice"
      :currency-code="
        item.ACTIVE_CURRENCY || item.CURRENCY || item.currency || 'wfrp_empire'
      "
      variant="row"
    />
    <input
      v-if="showTempHidden"
      class="idTemp"
      type="hidden"
      :value="item.ID"
    />
    <input v-else class="idInd" type="hidden" :value="item.ID" />
  </div>
</template>

<script src="./TradeItemRow.options.js"></script>

<style scoped src="./TradeItemRow.css"></style>
