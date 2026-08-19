<template>
  <fieldset class="shop-editor-pricing__rule-panel">
    <legend>{{ $t("shop.shopEditor.pricing.exceptions.when") }}</legend>
    <div class="shop-editor-pricing__mode-row">
      <label v-for="mode in modeOptions" :key="`${rule.id}-${mode.id}`">
        <input
          type="checkbox"
          :checked="rule.match.modes.includes(mode.id)"
          @change="toggleRuleMode(rule, mode.id, $event.target.checked)"
        />
        <span>{{ mode.label }}</span>
      </label>
      <ShopHelpTooltip
        :text="$t('shop.shopEditor.pricing.help.exceptionModes')"
      />
    </div>
    <div class="shop-editor-pricing__criteria-grid">
      <label class="shop-editor-pricing__field">
        <span>
          {{ $t("shop.shopEditor.pricing.exceptions.item") }}
          <ShopHelpTooltip
            :text="criterionTooltip('item', firstValue(rule.match.templateIds))"
          />
        </span>
        <select
          class="form-control-sm trade-input"
          :value="firstValue(rule.match.templateIds)"
          @change="
            updateRuleMatch(
              rule,
              'templateIds',
              numberList($event.target.value),
            )
          "
        >
          <option value="">{{ anyLabel }}</option>
          <option
            v-for="option in previewTemplateOptions"
            :key="`${rule.id}-template-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </label>
      <PricingRuleSelect
        v-for="field in criteriaFields"
        :key="field.matchKey"
        :label="$t(`shop.shopEditor.pricing.exceptions.${field.labelKey}`)"
        :model-value="firstValue(rule.match[field.matchKey])"
        :options="field.options"
        :any-label="anyLabel"
        :tooltip="
          criterionTooltip(
            field.labelKey,
            firstValue(rule.match[field.matchKey]),
          )
        "
        @update:model-value="
          updateRuleMatch(rule, field.matchKey, stringList($event))
        "
      />
    </div>
  </fieldset>
</template>

<script>
import { computed } from "vue";
import PricingRuleSelect from "@/components/shop/modules/shop-editor/components/PricingRuleSelect.vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopPricingContext } from "../../shopPricingContext";

export default {
  name: "ShopPricingRuleCriteria",
  components: { PricingRuleSelect, ShopHelpTooltip },
  props: { rule: { type: Object, required: true } },
  setup() {
    const context = useShopPricingContext();
    const criteriaFields = computed(() => [
      {
        labelKey: "itemClass",
        matchKey: "itemClasses",
        options: context.itemClassOptions.value,
      },
      {
        labelKey: "itemGenre",
        matchKey: "itemGenres",
        options: context.itemGenreOptions.value,
      },
      {
        labelKey: "currency",
        matchKey: "currencyCodes",
        options: context.currencyOptions.value,
      },
      {
        labelKey: "priceTier",
        matchKey: "priceTiers",
        options: context.priceTierOptions.value,
      },
      {
        labelKey: "legality",
        matchKey: "legalities",
        options: context.legalityOptions.value,
      },
      {
        labelKey: "availability",
        matchKey: "availabilityBands",
        options: context.availabilityOptions.value,
      },
    ]);
    const criterionTooltip = (labelKey, value) =>
      context.t("shop.shopEditor.pricing.help.exceptionCriterion", {
        field: context.t(`shop.shopEditor.pricing.exceptions.${labelKey}`),
        value: value || context.anyLabel.value,
      });
    return { ...context, criteriaFields, criterionTooltip };
  },
};
</script>
