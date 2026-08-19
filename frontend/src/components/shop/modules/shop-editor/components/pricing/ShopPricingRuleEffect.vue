<template>
  <fieldset class="shop-editor-pricing__rule-panel">
    <legend>{{ $t("shop.shopEditor.pricing.exceptions.then") }}</legend>
    <div class="shop-editor-pricing__criteria-grid">
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.exceptions.effect") }}
          <ShopHelpTooltip
            :text="$t('shop.shopEditor.pricing.help.exceptionEffect')"
          />
        </span>
        <select
          class="form-control-sm trade-input"
          :value="rule.effect.type"
          @change="
            updateRuleEffect(rule, {
              type: $event.target.value,
              value: defaultEffectValue($event.target.value),
            })
          "
        >
          <option v-for="type in effectTypes" :key="type" :value="type">
            {{ $t(`shop.shopEditor.pricing.exceptions.effects.${type}`) }}
          </option>
        </select>
      </label>
      <label class="shop-editor-pricing__field">
        <span>
          {{ effectValueLabel(rule.effect.type) }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.exceptionEffectValue', {
                value: displayEffectValue(rule.effect),
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          :min="rule.effect.type === 'multiplier' ? 0 : undefined"
          step="1"
          :value="displayEffectValue(rule.effect)"
          @change="updateRuleEffectValue(rule, $event.target.value)"
        />
      </label>
    </div>
    <div class="shop-editor-pricing__rule-flags">
      <label>
        <input
          type="checkbox"
          :checked="rule.effect.stopProcessing"
          @change="
            updateRuleEffect(rule, { stopProcessing: $event.target.checked })
          "
        />
        <span>{{ $t("shop.shopEditor.pricing.exceptions.stop") }}</span>
        <ShopHelpTooltip
          :text="$t('shop.shopEditor.pricing.help.exceptionStop')"
        />
      </label>
      <label>
        <input
          type="checkbox"
          :checked="rule.effect.ignoreGuardrails"
          @change="
            updateRuleEffect(rule, { ignoreGuardrails: $event.target.checked })
          "
        />
        <span>{{
          $t("shop.shopEditor.pricing.exceptions.ignoreGuardrails")
        }}</span>
        <ShopHelpTooltip
          :text="$t('shop.shopEditor.pricing.help.exceptionIgnoreGuardrails')"
        />
      </label>
    </div>
    <div class="shop-editor-pricing__suppression">
      <strong>
        {{ $t("shop.shopEditor.pricing.exceptions.disableModifiers") }}
        <ShopHelpTooltip
          :text="$t('shop.shopEditor.pricing.help.exceptionSuppress')"
        />
      </strong>
      <small>{{
        $t("shop.shopEditor.pricing.exceptions.disableModifiersHint")
      }}</small>
      <div class="shop-editor-pricing__suppression-grid">
        <label
          v-for="option in modifierOptions"
          :key="`${rule.id}-${option.key}`"
        >
          <input
            type="checkbox"
            :checked="rule.effect.disabledModifiers.includes(option.key)"
            @change="
              toggleDisabledModifier(rule, option.key, $event.target.checked)
            "
          />
          <span>{{ option.label }}</span>
        </label>
      </div>
    </div>
  </fieldset>
</template>

<script>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopPricingContext } from "../../shopPricingContext";

export default {
  name: "ShopPricingRuleEffect",
  components: { ShopHelpTooltip },
  props: { rule: { type: Object, required: true } },
  setup() {
    return {
      ...useShopPricingContext(),
      effectTypes: ["multiplier", "additive", "fixed"],
    };
  },
};
</script>
