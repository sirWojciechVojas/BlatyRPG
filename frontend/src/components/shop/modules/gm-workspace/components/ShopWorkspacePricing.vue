<!-- Panel GM sklepu: ShopWorkspacePricing. -->
<template>
  <section class="pricing-editor-workspace">
    <ShopEditorPricingSection
      :form="profileDraft"
      :modifier-options="pricingModifierOptions"
      :pricing-rules="pricingRules"
      :item-class-options="pricingItemClassOptions"
      :item-genre-options="pricingItemGenreOptions"
      :currency-options="pricingCurrencyOptions"
      :currency-definitions="currencyContext"
      :preview-template-id="pricingPreviewTemplateId"
      :preview-template-options="pricingPreviewTemplateOptions"
      :price-preview="pricingPricePreview"
      :preview-quantity="pricingPreviewQuantity"
      :preview-condition="pricingPreviewCondition"
      :preview-reputation="pricingPreviewReputation"
      :preview-mode="pricingPreviewMode"
      :preview-temporary-modifier="pricingPreviewTemporaryModifier"
      :preview-quick-mode="pricingPreviewQuickMode"
      :preview-loading="pricingPreviewLoading"
      :preview-error="pricingPreviewError"
      @toggle-modifier="handlePricingModifierToggle"
      @update-policy-field="updatePricingPolicyField"
      @apply-preset="applyPricingPreset"
      @add-rule="addPricingRule"
      @update-rule="updatePricingRule"
      @remove-rule="removePricingRule"
      @save-policy-preset="savePolicyPreset"
      @apply-policy-preset="applyPolicyPreset"
      @remove-policy-preset="removePolicyPreset"
      @update-preview-template="updatePricingPreviewTemplate"
      @update-preview-input="updatePricingPreviewInput"
    />
  </section>
</template>
<script>
import ShopEditorPricingSection from "@/components/shop/modules/shop-editor/components/ShopEditorPricingSection.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";
export default {
  name: "ShopWorkspacePricing",
  components: { ShopEditorPricingSection },
  setup() {
    return useShopWorkspaceContext();
  },
};
</script>
