<!-- Edytor cen sklepu: wybór i nagłówek pojedynczego wyjątku. -->
<template>
  <label
    v-if="pricingRules.length > 1"
    class="shop-editor-pricing__rule-picker"
  >
    <span>
      {{ $t("shop.shopEditor.pricing.exceptions.choose") }}
      <ShopHelpTooltip
        :text="$t('shop.shopEditor.pricing.help.exceptionPicker')"
      />
    </span>
    <select v-model="activeRuleId" class="form-control-sm trade-input">
      <option
        v-for="entry in pricingRules"
        :key="entry.id"
        :value="String(entry.id)"
      >
        {{ entry.name }}
      </option>
    </select>
  </label>
  <article
    v-if="activeRule"
    class="shop-editor-pricing__rule"
    :class="{ 'shop-editor-pricing__rule--disabled': !activeRule.enabled }"
  >
    <header class="shop-editor-pricing__rule-header">
      <label class="shop-editor-pricing__inline-toggle">
        <input
          type="checkbox"
          :checked="activeRule.enabled"
          @change="updateRule(activeRule, { enabled: $event.target.checked })"
        />
        <span>
          {{ $t("shop.shopEditor.pricing.exceptions.active") }}
          <ShopHelpTooltip
            :text="$t('shop.shopEditor.pricing.help.exceptionActive')"
          />
        </span>
      </label>
      <input
        class="form-control-sm trade-input shop-editor-pricing__rule-name"
        type="text"
        :value="activeRule.name"
        :placeholder="$t('shop.shopEditor.pricing.exceptions.name')"
        @change="updateRule(activeRule, { name: $event.target.value })"
      />
      <label class="shop-editor-pricing__priority">
        <span>
          {{ $t("shop.shopEditor.pricing.exceptions.priority") }}
          <ShopHelpTooltip
            :text="
              $t('shop.shopEditor.pricing.help.exceptionPriority', {
                value: activeRule.priority,
              })
            "
          />
        </span>
        <input
          class="form-control-sm trade-input"
          type="number"
          min="-1000"
          max="1000"
          step="1"
          :value="activeRule.priority"
          @change="
            updateRule(activeRule, { priority: Number($event.target.value) })
          "
        />
      </label>
      <button
        type="button"
        class="btn btn-sm btn-outline-danger"
        @click="emit('remove-rule', activeRule.id)"
      >
        {{ $t("shop.shopEditor.pricing.exceptions.remove") }}
      </button>
    </header>
    <div class="shop-editor-pricing__rule-grid">
      <ShopPricingRuleCriteria :rule="activeRule" />
      <ShopPricingRuleEffect :rule="activeRule" />
    </div>
  </article>
</template>

<script>
import { computed, ref, watch } from "vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import ShopPricingRuleCriteria from "./ShopPricingRuleCriteria.vue";
import ShopPricingRuleEffect from "./ShopPricingRuleEffect.vue";
import { useShopPricingContext } from "../../shopPricingContext";

export default {
  name: "ShopPricingRuleEditor",
  components: {
    ShopHelpTooltip,
    ShopPricingRuleCriteria,
    ShopPricingRuleEffect,
  },
  setup() {
    const context = useShopPricingContext();
    const activeRuleId = ref("");
    const activeRule = computed(
      () =>
        context.pricingRules.value.find(
          (rule) => String(rule.id) === activeRuleId.value,
        ) ||
        context.pricingRules.value[0] ||
        null,
    );
    watch(
      context.pricingRules,
      (rules) => {
        if (!rules.some((rule) => String(rule.id) === activeRuleId.value)) {
          activeRuleId.value = String(rules[0]?.id || "");
        }
      },
      { immediate: true },
    );
    return { ...context, activeRule, activeRuleId };
  },
};
</script>
