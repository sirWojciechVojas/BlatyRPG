<!-- Composes the GM assortment workspace from focused panels. -->
<template>
  <section
    class="gm-shop-assortment-layout"
    :class="{
      'gm-shop-assortment-layout--suggestions':
        ctx.assortmentRightTab === 'suggestions',
    }"
    aria-labelledby="gm-shop-assortment-title"
  >
    <div class="gm-shop-assortment-layout__notifications" aria-live="polite">
      <ShopNotificationBar zone="buy" />
      <ShopNotificationBar zone="sell" />
    </div>

    <header class="gm-shop-assortment-layout__modebar">
      <div class="gm-shop-assortment-layout__title-wrap">
        <h2 id="gm-shop-assortment-title">
          {{ $t("shop.modules.assortment") }}
        </h2>
        <span class="gm-shop-assortment-layout__active-shop">
          {{ ctx.containerLabelById(ctx.assortmentRightContainerId) }}
        </span>
      </div>
      <div class="gm-shop-assortment-layout__counters">
        <span>
          Źródło:
          <strong>{{ ctx.assortmentLeftItems.length }}</strong>
        </span>
        <span>
          Sklep:
          <strong>{{ ctx.assortmentRightItems.length }}</strong>
        </span>
      </div>
    </header>
    <div class="gm-shop-assortment-layout__columns">
      <AssortmentSourcePanel />
      <AssortmentTargetPanel />
    </div>
    <AssortmentOperationsFooter />
  </section>
</template>

<script setup>
import ShopNotificationBar from "@/components/shop/common/ShopNotificationBar.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import AssortmentSourcePanel from "./workspace/AssortmentSourcePanel.vue";
import AssortmentTargetPanel from "./workspace/AssortmentTargetPanel.vue";
import AssortmentOperationsFooter from "./workspace/AssortmentOperationsFooter.vue";

const ctx = useTradeModalContext();
</script>
