<!-- Komponent modułu Sklep - konfiguracja polityki cenowej. -->
<template>
  <ShopEditorSectionCard :title="$t('shop.shopEditor.pricing.title')">
    <div class="shop-editor-pricing">
      <div class="shop-editor-pricing__intro">
        <span>{{ $t("shop.shopEditor.pricing.description") }}</span>
        <ShopHelpTooltip
          :label="$t('shop.shopEditor.pricing.title')"
          :text="$t('shop.shopEditor.pricing.help.engine')"
        />
      </div>
      <div class="shop-editor-pricing__workspace">
        <div class="shop-editor-pricing__controls">
          <nav
            class="shop-editor-pricing__tabs"
            role="tablist"
            :aria-label="$t('shop.shopEditor.pricing.tabs.label')"
          >
            <button
              v-for="tab in pricingTabs"
              :key="tab"
              type="button"
              role="tab"
              :class="{ active: activePricingPanel === tab }"
              :aria-selected="activePricingPanel === tab"
              :aria-controls="`pricing-panel-${tab}`"
              @click="activePricingPanel = tab"
            >
              {{ $t(`shop.shopEditor.pricing.tabs.${tab}`) }}
              <span v-if="tab === 'exceptions' && pricingRules.length">
                {{ pricingRules.length }}
              </span>
            </button>
          </nav>
          <div
            :id="`pricing-panel-${activePricingPanel}`"
            class="shop-editor-pricing__tab-panel"
            role="tabpanel"
          >
            <ShopPricingPresets v-if="activePricingPanel === 'presets'" />
            <ShopPricingBasics v-else-if="activePricingPanel === 'basics'" />
            <ShopPricingCurrency
              v-else-if="activePricingPanel === 'currency'"
            />
            <ShopPricingGuardrails
              v-else-if="activePricingPanel === 'guardrails'"
            />
            <ShopPricingModifiers
              v-else-if="activePricingPanel === 'modifiers'"
            />
            <ShopPricingExceptions v-else />
          </div>
        </div>
        <ShopPricingPreview />
      </div>
    </div>
  </ShopEditorSectionCard>
</template>

<script setup>
import { provide, ref } from "vue";
import ShopEditorSectionCard from "./ShopEditorSectionCard.vue";
import ShopPricingPresets from "./pricing/ShopPricingPresets.vue";
import ShopPricingBasics from "./pricing/ShopPricingBasics.vue";
import ShopPricingCurrency from "./pricing/ShopPricingCurrency.vue";
import ShopPricingGuardrails from "./pricing/ShopPricingGuardrails.vue";
import ShopPricingModifiers from "./pricing/ShopPricingModifiers.vue";
import ShopPricingExceptions from "./pricing/ShopPricingExceptions.vue";
import ShopPricingPreview from "./pricing/ShopPricingPreview.vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { createPricingSectionContext } from "../composables/useShopPricingSection";
import { shopPricingContextKey } from "../shopPricingContext";

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  modifierOptions: {
    type: Array,
    default: () => [],
  },
  pricingRules: {
    type: Array,
    default: () => [],
  },
  itemClassOptions: {
    type: Array,
    default: () => [],
  },
  itemGenreOptions: {
    type: Array,
    default: () => [],
  },
  currencyOptions: {
    type: Array,
    default: () => [],
  },
  currencyDefinitions: {
    type: Object,
    default: () => ({}),
  },
  previewTemplateId: {
    type: [Number, String],
    default: null,
  },
  previewTemplateOptions: {
    type: Array,
    default: () => [],
  },
  pricePreview: {
    type: Object,
    default: null,
  },
  previewQuantity: { type: Number, default: 1 },
  previewCondition: { type: String, default: "good" },
  previewReputation: { type: String, default: "neutralna" },
  previewMode: { type: String, default: "buy" },
  previewTemporaryModifier: { type: Number, default: 0 },
  previewQuickMode: { type: Boolean, default: false },
  previewLoading: { type: Boolean, default: false },
  previewError: { type: String, default: "" },
});
const emit = defineEmits([
  "toggle-modifier",
  "update-preview-template",
  "update-preview-input",
  "update-policy-field",
  "apply-preset",
  "add-rule",
  "update-rule",
  "remove-rule",
  "save-policy-preset",
  "apply-policy-preset",
  "remove-policy-preset",
]);
const activePricingPanel = ref("basics");
const pricingTabs = [
  "basics",
  "presets",
  "currency",
  "guardrails",
  "modifiers",
  "exceptions",
];
provide(shopPricingContextKey, createPricingSectionContext(props, emit));
</script>

<style src="../styles/ShopEditorPricingSection.1.css"></style>
<style src="../styles/ShopEditorPricingSection.2.css"></style>
<style src="../styles/ShopEditorPricingSimulator.css"></style>
<style src="../styles/ShopEditorPricingTabs.css"></style>
