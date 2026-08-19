<!-- Edytor cen sklepu: ShopPricingExceptions. -->
<template>
  <section class="shop-editor-pricing__policy-block">
    <header class="shop-editor-pricing__section-header">
      <div>
        <strong>
          {{ $t("shop.shopEditor.pricing.exceptions.title") }}
          <ShopHelpTooltip
            :text="$t('shop.shopEditor.pricing.help.exceptions')"
          />
        </strong>
        <small>{{
          $t("shop.shopEditor.pricing.exceptions.description")
        }}</small>
      </div>
      <button
        type="button"
        class="btn btn-sm btn-outline-light"
        @click="emit('add-rule')"
      >
        {{ $t("shop.shopEditor.pricing.exceptions.add") }}
      </button>
    </header>
    <div
      v-if="!pricingRules.length"
      class="shop-editor-pricing__exceptions-empty"
    >
      {{ $t("shop.shopEditor.pricing.exceptions.empty") }}
    </div>
    <ShopPricingRuleEditor />
  </section>
</template>
<script>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopPricingContext } from "../../shopPricingContext";
export default {
  name: "ShopPricingExceptions",
  components: { ShopHelpTooltip },
  setup() {
    return useShopPricingContext();
  },
};
</script>
