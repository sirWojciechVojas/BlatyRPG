<!-- Responsibility: ShopActivationDialog shop interface component. -->
<template>
  <div
    v-if="ctx.showShopActivationDialog"
    class="img-dialog-backdrop"
    role="dialog"
    aria-modal="true"
    :aria-label="$t('modals.shopActivation.ariaLabel')"
    @click.self="closeDialog"
  >
    <div class="shop-activation-dialog">
      <div class="img-dialog-header">
        <div class="img-dialog-title">
          {{ $t("modals.shopActivation.title") }}
        </div>
        <button
          type="button"
          class="btn btn-outline-light btn-sm"
          :aria-label="$t('common.actions.close')"
          @click="closeDialog"
        >
          {{ $t("common.actions.close") }}
        </button>
      </div>

      <div class="img-dialog-purpose">
        {{ $t("modals.shopActivation.purpose") }}
      </div>

      <div class="shop-activation-dialog__meta">
        <div class="shop-activation-dialog__subtitle">
          {{ $t("modals.shopActivation.subtitle") }}
        </div>
        <div class="shop-activation-dialog__counter">
          {{
            $t("modals.shopActivation.activeCount", {
              active: activeCount,
              total: shopOptions.length,
            })
          }}
        </div>
      </div>

      <div class="shop-activation-grid">
        <button
          v-for="shop in shopOptions"
          :key="`shop-activation-${shop.id}`"
          type="button"
          class="shop-activation-card"
          :class="{
            'shop-activation-card--active': shop.isActive,
            'shop-activation-card--inactive': !shop.isActive,
            'shop-activation-card--current': shop.isCurrent,
          }"
          :title="''"
          @click="toggleShop(shop)"
        >
          <span class="shop-activation-card__shine"></span>
          <span class="shop-activation-card__badge">{{ shop.badge }}</span>
          <span class="shop-activation-card__name">{{ shop.label }}</span>
          <span class="shop-activation-card__state">
            {{
              shop.isActive
                ? $t("modals.shopActivation.statusActive")
                : $t("modals.shopActivation.statusInactive")
            }}
          </span>
          <span v-if="shop.isCurrent" class="shop-activation-card__current">
            {{ $t("modals.shopActivation.currentShop") }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();

const shopOptions = computed(() =>
  Array.isArray(ctx.shopActivationOptions) ? ctx.shopActivationOptions : [],
);

const activeCount = computed(() => Number(ctx.shopActivationActiveCount || 0));

const closeDialog = () => {
  if (typeof ctx.closeShopActivationDialog === "function") {
    ctx.closeShopActivationDialog();
  }
};

const toggleShop = (shop) => {
  if (!shop) {
    return;
  }
  if (typeof ctx.toggleShopActivationForEditor === "function") {
    ctx.toggleShopActivationForEditor(shop.id);
  }
};
</script>
