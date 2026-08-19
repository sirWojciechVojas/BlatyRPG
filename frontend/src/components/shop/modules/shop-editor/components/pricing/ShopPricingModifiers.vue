<!-- Edytor cen sklepu: ShopPricingModifiers. -->
<template>
  <section class="shop-editor-pricing__policy-block">
    <header class="shop-editor-pricing__section-header">
      <div>
        <strong>
          {{ $t("shop.shopEditor.pricing.modifiersTitle") }}
          <ShopHelpTooltip
            :text="$t('shop.shopEditor.pricing.help.modifiers')"
          />
        </strong>
        <small>{{ $t("shop.shopEditor.pricing.modifiersDescription") }}</small>
      </div>
    </header>

    <div class="shop-editor-pricing__toggle-list">
      <label
        v-for="option in modifierOptions"
        :key="`shop-pricing-modifier-${option.key}`"
        class="shop-editor-pricing__toggle"
      >
        <input
          type="checkbox"
          :checked="isModifierEnabled(option.key)"
          @change="
            emit('toggle-modifier', {
              key: option.key,
              enabled: $event.target.checked,
            })
          "
        />
        <span class="shop-editor-pricing__toggle-copy">
          <strong>
            {{ option.label }}
            <ShopHelpTooltip
              :text="
                $t('shop.shopEditor.pricing.help.modifier', {
                  label: option.label,
                  description: option.description,
                  value: isModifierEnabled(option.key)
                    ? $t('shop.workspace.active')
                    : $t('shop.workspace.inactive'),
                })
              "
            />
          </strong>
          <small>{{ option.description }}</small>
        </span>
      </label>
    </div>
  </section>
</template>
<script>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopPricingContext } from "../../shopPricingContext";
export default {
  name: "ShopPricingModifiers",
  components: { ShopHelpTooltip },
  setup() {
    return useShopPricingContext();
  },
};
</script>
