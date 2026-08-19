<!-- Edytor cen sklepu: ShopPricingCurrency. -->
<template>
  <section class="shop-editor-pricing__policy-block">
    <header class="shop-editor-pricing__section-header">
      <div>
        <strong>
          {{ $t("shop.shopEditor.pricing.currency.title") }}
          <ShopHelpTooltip
            :text="$t('shop.shopEditor.pricing.help.currency')"
          />
        </strong>
        <small>{{ $t("shop.shopEditor.pricing.currency.description") }}</small>
      </div>
    </header>
    <div class="shop-editor-pricing__field-grid">
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.currency.settlement") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.settlementCurrency', {
                value: settlementCurrencyLabel,
              })
            "
          />
        </span>
        <select
          class="form-control-sm trade-input"
          :value="pricingConfig.currencyPolicy.settlementCurrencyCode"
          @change="updateSettlementCurrency($event.target.value)"
        >
          <option
            v-for="option in currencyOptions"
            :key="`settlement-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <small>{{
          $t("shop.shopEditor.pricing.currency.settlementHint")
        }}</small>
      </label>
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.currency.buyFee") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.buyFee', {
                value: pricingConfig.currencyPolicy.buyFeePercent,
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          max="100"
          step="0.1"
          :value="pricingConfig.currencyPolicy.buyFeePercent"
          @change="updateNumberField('currencyPolicy.buyFeePercent', $event, 0)"
        />
        <small>{{ $t("shop.shopEditor.pricing.currency.buyFeeHint") }}</small>
      </label>
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.currency.sellFee") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.sellFee', {
                value: pricingConfig.currencyPolicy.sellFeePercent,
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          max="100"
          step="0.1"
          :value="pricingConfig.currencyPolicy.sellFeePercent"
          @change="
            updateNumberField('currencyPolicy.sellFeePercent', $event, 0)
          "
        />
        <small>{{ $t("shop.shopEditor.pricing.currency.sellFeeHint") }}</small>
      </label>
      <label class="shop-editor-pricing__field">
        <span>{{
          $t("shop.shopEditor.pricing.currency.paymentExchangeFee")
        }}</span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0"
          max="100"
          step="0.1"
          :value="pricingConfig.currencyPolicy.paymentExchangeFeePercent"
          @change="
            updateNumberField(
              'currencyPolicy.paymentExchangeFeePercent',
              $event,
              5,
            )
          "
        />
        <small>{{
          $t("shop.shopEditor.pricing.currency.paymentExchangeFeeHint")
        }}</small>
      </label>
    </div>
    <div class="shop-editor-pricing__exchange-list">
      <div class="shop-editor-pricing__exchange-heading">
        <strong>{{ $t("shop.shopEditor.pricing.currency.rates") }}</strong>
        <small>{{ $t("shop.shopEditor.pricing.currency.ratesHint") }}</small>
      </div>
      <label
        v-for="option in currencyOptions"
        :key="`exchange-rate-${option.value}`"
        class="shop-editor-pricing__exchange-row"
      >
        <input
          type="checkbox"
          :checked="hasExchangeRate(option.value)"
          :disabled="
            option.value === pricingConfig.currencyPolicy.settlementCurrencyCode
          "
          :aria-label="
            $t('shop.shopEditor.pricing.currency.acceptCurrency', {
              currency: option.label,
            })
          "
          @change="toggleExchangeRate(option.value, $event.target.checked)"
        />
        <span>
          {{ option.label }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.exchangeRate', {
                source: option.label,
                target: settlementCurrencyLabel,
                value: exchangeRateFor(option.value),
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="0.000001"
          step="0.0001"
          :disabled="
            option.value ===
              pricingConfig.currencyPolicy.settlementCurrencyCode ||
            !hasExchangeRate(option.value)
          "
          :value="exchangeRateFor(option.value)"
          @change="updateExchangeRate(option.value, $event.target.value)"
        />
        <small>
          {{
            $t("shop.shopEditor.pricing.currency.rateUnit", {
              source: option.label,
              target: settlementCurrencyLabel,
            })
          }}
        </small>
      </label>
    </div>
  </section>
</template>
<script>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopPricingContext } from "../../shopPricingContext";
export default {
  name: "ShopPricingCurrency",
  components: { ShopHelpTooltip },
  setup() {
    return useShopPricingContext();
  },
};
</script>
