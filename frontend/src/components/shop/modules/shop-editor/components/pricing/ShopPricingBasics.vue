<!-- Edytor cen sklepu: ShopPricingBasics. -->
<template>
  <section class="shop-editor-pricing__policy-block">
    <header class="shop-editor-pricing__section-header">
      <div>
        <strong>
          {{ $t("shop.shopEditor.pricing.basics.title") }}
          <ShopHelpTooltip :text="$t('shop.shopEditor.pricing.help.basics')" />
        </strong>
        <small>{{ $t("shop.shopEditor.pricing.basics.description") }}</small>
      </div>
    </header>
    <div class="shop-editor-pricing__field-grid">
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.basics.saleRate") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.saleRate', {
                value: toPercent(pricingConfig.baseMultipliers.buy),
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          max="1000"
          step="1"
          :value="toPercent(pricingConfig.baseMultipliers.buy)"
          @change="updatePercentField('baseMultipliers.buy', $event)"
        />
        <small>{{ $t("shop.shopEditor.pricing.basics.saleRateHint") }}</small>
      </label>
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.basics.buybackRate") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.buybackRate', {
                value: toPercent(pricingConfig.baseMultipliers.sell),
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          max="1000"
          step="1"
          :value="toPercent(pricingConfig.baseMultipliers.sell)"
          @change="updatePercentField('baseMultipliers.sell', $event)"
        />
        <small>{{
          $t("shop.shopEditor.pricing.basics.buybackRateHint")
        }}</small>
      </label>
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.basics.minimumPrice") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.minimumPrice', {
                value: pricingConfig.minimumPrice,
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          step="1"
          :value="pricingConfig.minimumPrice"
          @change="updateNumberField('minimumPrice', $event, 0)"
        />
        <small>{{
          $t("shop.shopEditor.pricing.basics.minimumPriceHint")
        }}</small>
      </label>
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.basics.roundingStep") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.roundingStep', {
                value: pricingConfig.roundingStep,
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="1"
          step="1"
          :value="pricingConfig.roundingStep"
          @change="updateNumberField('roundingStep', $event, 1)"
        />
        <small>{{
          $t("shop.shopEditor.pricing.basics.roundingStepHint")
        }}</small>
      </label>
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.basics.roundingMode") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.roundingMode', {
                value: $t(
                  `shop.shopEditor.pricing.basics.roundingModes.${pricingConfig.roundingMode}`,
                ),
              })
            "
          />
        </span>
        <select
          class="form-control-sm trade-input"
          :value="pricingConfig.roundingMode"
          @change="emitPolicyField('roundingMode', String($event.target.value))"
        >
          <option value="nearest">
            {{ $t("shop.shopEditor.pricing.basics.roundingModes.nearest") }}
          </option>
          <option value="up">
            {{ $t("shop.shopEditor.pricing.basics.roundingModes.up") }}
          </option>
          <option value="down">
            {{ $t("shop.shopEditor.pricing.basics.roundingModes.down") }}
          </option>
        </select>
      </label>
      <label
        v-for="field in priceBandFields"
        :key="field.path"
        class="shop-editor-pricing__field"
      >
        <span>
          {{ field.label }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.priceBand', {
                label: field.label,
                value: valueAt(field.path),
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          step="1"
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
  name: "ShopPricingBasics",
  components: { ShopHelpTooltip },
  setup() {
    return useShopPricingContext();
  },
};
</script>
