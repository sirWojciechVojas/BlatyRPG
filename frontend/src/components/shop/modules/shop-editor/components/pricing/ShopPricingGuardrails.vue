<!-- Edytor cen sklepu: ShopPricingGuardrails. -->
<template>
  <section class="shop-editor-pricing__policy-block">
    <header class="shop-editor-pricing__section-header">
      <div>
        <strong>
          {{ $t("shop.shopEditor.pricing.guardrails.title") }}
          <ShopHelpTooltip
            :text="$t('shop.shopEditor.pricing.help.guardrails')"
          />
        </strong>
        <small>{{
          $t("shop.shopEditor.pricing.guardrails.description")
        }}</small>
      </div>
      <label class="shop-editor-pricing__inline-toggle">
        <input
          type="checkbox"
          :checked="pricingConfig.guardrails.enabled"
          @change="emitPolicyField('guardrails.enabled', $event.target.checked)"
        />
        <span>
          {{ $t("shop.shopEditor.pricing.guardrails.enabled") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.guardrailsEnabled', {
                value: pricingConfig.guardrails.enabled
                  ? $t('shop.workspace.active')
                  : $t('shop.workspace.inactive'),
              })
            "
          />
        </span>
      </label>
    </header>
    <div
      class="shop-editor-pricing__field-grid"
      :class="{
        'shop-editor-pricing__field-grid--disabled':
          !pricingConfig.guardrails.enabled,
      }"
    >
      <label
        v-for="field in guardrailFields"
        :key="field.path"
        class="shop-editor-pricing__field"
      >
        <span>
          {{ field.label }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.guardrail', {
                label: field.label,
                value: toPercent(valueAt(field.path)),
                hint: field.hint,
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          :max="field.path.endsWith('maxBuybackRatio') ? 100 : 1000"
          step="1"
          :disabled="!pricingConfig.guardrails.enabled"
          :value="toPercent(valueAt(field.path))"
          @change="updatePercentField(field.path, $event)"
        />
        <small>{{ field.hint }}</small>
      </label>
      <label
        v-for="field in guardrailDirectFields"
        :key="field.path"
        class="shop-editor-pricing__field"
      >
        <span>
          {{ field.label }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.guardrail', {
                label: field.label,
                value: valueAt(field.path),
                hint: field.hint,
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          :max="field.path.endsWith('maxTemporaryPercent') ? 500 : 100"
          step="1"
          :disabled="!pricingConfig.guardrails.enabled"
          :value="valueAt(field.path)"
          @change="updateNumberField(field.path, $event, 0)"
        />
        <small>{{ field.hint }}</small>
      </label>
    </div>
  </section>
</template>
<script>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopPricingContext } from "../../shopPricingContext";
export default {
  name: "ShopPricingGuardrails",
  components: { ShopHelpTooltip },
  setup() {
    return useShopPricingContext();
  },
};
</script>
