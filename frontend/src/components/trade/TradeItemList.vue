<!-- Responsibility: TradeItemList shop interface component. -->
<template>
  <div
    ref="viewport"
    class="trade-modal__list"
    :class="listClass"
    :aria-busy="String(loading)"
    role="listbox"
    tabindex="0"
    @scroll="handleScroll"
    @scrollend="handleScrollEnd"
  >
    <div
      v-if="!loading && !errorText"
      class="trade-virtual-spacer"
      :style="{ height: `${virtualContentHeight}px` }"
    >
      <div
        class="trade-virtual-window"
        :style="{
          gap: `${rowGap}px`,
          transform: `translateY(${visibleStart * rowStride}px)`,
        }"
      >
        <TradeItemRow
          v-for="item in visibleItems"
          :key="`${mode}-${item.ID}`"
          :item="item"
          :mode="mode"
          :row-class="rowClass"
          :is-act="isAct(item)"
          :is-active="isActive(item)"
          :selected-quantity="selectedQuantityFor(item)"
          :can-adjust-quantity="canAdjustQuantityFor(item)"
          :show-price="showPrice"
          :show-temp-hidden="showTempHidden"
          :icon-class="iconClassForItem(item)"
          :image-src="imageSrcForItem(item)"
          @select="$emit('select', $event)"
          @qty-step="(delta) => $emit('qty-step', { item, delta })"
          @qty-input="(quantity) => $emit('qty-input', { item, quantity })"
          @open-detail="handleOpenDetail"
        />
      </div>
    </div>
    <div v-if="showEmptyState" class="trading-empty">
      {{ resolvedEmptyText }}
    </div>
    <transition name="trade-window-state-fade">
      <div
        v-if="loading || errorText"
        class="trade-window-state"
        :class="{ 'trade-window-state--error': errorText }"
      >
        <div v-if="loading" class="trade-window-state__content" role="status">
          <div class="trade-window-state__sigil" aria-hidden="true">
            <span class="trade-window-state__ring"></span>
            <span
              class="trade-window-state__spark trade-window-state__spark--a"
            ></span>
            <span
              class="trade-window-state__spark trade-window-state__spark--b"
            ></span>
            <span
              class="trade-window-state__spark trade-window-state__spark--c"
            ></span>
          </div>
          <div class="trade-window-state__text">{{ resolvedLoadingText }}</div>
        </div>
        <div v-else class="trade-window-state__content" role="alert">
          <div class="trade-window-state__error-title">{{ errorText }}</div>
          <button
            type="button"
            class="trade-window-state__retry"
            @click="$emit('retry')"
          >
            {{ resolvedRetryLabel }}
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script src="./TradeItemList.options.js"></script>

<style scoped src="./TradeItemList.css"></style>
