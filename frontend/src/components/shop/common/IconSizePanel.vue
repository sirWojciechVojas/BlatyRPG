<!-- Responsibility: IconSizePanel shop interface component. -->
<template>
  <div class="icon-size-panel">
    <div class="icon-size-panel__head">
      <span class="icon-size-panel__label">{{ $t("shop.common.icons") }}</span>
      <span class="icon-size-panel__value">{{ ctx.iconSizeModel }}px</span>
    </div>
    <input
      v-model.number="ctx.iconSizeModel"
      type="range"
      class="icon-size-panel__slider"
      :min="ctx.iconSizeMin"
      :max="ctx.iconSizeMax"
      step="1"
    />
    <div class="icon-size-panel__presets">
      <button
        type="button"
        class="btn btn-outline-light btn-sm"
        @click="ctx.setIconSize(28)"
      >
        28
      </button>
      <button
        type="button"
        class="btn btn-outline-light btn-sm"
        @click="ctx.setIconSize(ctx.iconSizeDefault)"
      >
        34
      </button>
      <button
        type="button"
        class="btn btn-outline-light btn-sm"
        @click="ctx.setIconSize(44)"
      >
        44
      </button>
    </div>
  </div>
</template>

<script setup>
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();
</script>
